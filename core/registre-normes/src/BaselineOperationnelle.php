<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

/**
 * Reconstruit l'index technique à partir d'une photographie versionnée.
 *
 * C'est la seule source d'initialisation contrôlée de l'index : elle ne lit
 * aucun fichier documentaire et ne dépend d'aucun corpus. Son contenu est
 * protégé par une empreinte SHA-256 vérifiée avant toute écriture.
 */
final class BaselineOperationnelle
{
    private const FORMAT = 'gamad-core-index-baseline';
    private const VERSION = 1;
    private const EMPREINTE_SHA256 = '2f0850133e75efb42acc15fc14c4f6781045e57d9c95a701d7beb1aab6ba9ef0';

    /**
     * Ordre d'insertion respectant les dépendances relationnelles du schéma.
     *
     * @var list<string>
     */
    private const TABLES = [
        'rang_normatif',
        'source',
        'norme',
        'adoption',
        'version_norme',
        'statut',
        'etat_capacite',
        'politique',
        'regle',
        'entite',
        'etat_entite',
        'denomination',
        'titulaire',
        'fonction',
        'etat_fonction',
        'mandat',
        'etat_mandat',
        'delegation',
        'relation_evolution',
    ];

    /** @var array<string,mixed>|null */
    private ?array $chargee = null;

    public function __construct(private readonly string $chemin)
    {
    }

    public static function standard(): self
    {
        return new self(dirname(__DIR__).'/resources/index-baseline-v1.json');
    }

    public function chemin(): string
    {
        return $this->chemin;
    }

    public function version(): int
    {
        return self::VERSION;
    }

    public function empreinte(): string
    {
        return self::EMPREINTE_SHA256;
    }

    /**
     * @return array{adoptions:int,normes:int,versions:int,statuts:int,etats:int,
     *               rangs:int,sources:int,fonctions:int,entites:int,regles:int,
     *               mandats:int,indetermines:int}
     */
    public function reconstruire(\PDO $pdo): array
    {
        if ($pdo->inTransaction()) {
            throw new \RuntimeException('La reconstruction exige une connexion hors transaction.');
        }

        $payload = $this->charger();
        $pdo->beginTransaction();

        try {
            Schema::create($pdo);
            foreach (self::TABLES as $table) {
                /** @var array{colonnes:list<string>,lignes:list<array<string,mixed>>} $definition */
                $definition = $payload['tables'][$table];
                $this->inserer($pdo, $table, $definition['colonnes'], $definition['lignes']);
            }

            $compteurs = $this->compteurs($pdo);
            if ($compteurs !== $payload['compteurs']) {
                throw new \RuntimeException(sprintf(
                    'La baseline reconstruite ne correspond pas aux compteurs attendus. Attendu=%s Obtenu=%s',
                    json_encode($payload['compteurs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($compteurs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ));
            }

            $pdo->commit();

            return $compteurs;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Diagnostic opérationnel de l'index : la source d'initialisation est-elle
     * intègre, et l'index en base correspond-il encore à ce qu'elle porte ?
     *
     * Aucune écriture, aucune exception : un diagnostic doit rendre compte
     * d'une panne, non s'y ajouter. Ce que le service ne peut pas constater
     * est déclaré, jamais présumé conforme.
     *
     * @return array{baseline:array<string,mixed>,index:array<string,mixed>,
     *               divergences:list<string>,coherent:bool}
     */
    public function diagnostiquer(\PDO $pdo): array
    {
        $divergences = [];

        $presente = is_file($this->chemin);
        $brut = $presente ? file_get_contents($this->chemin) : false;
        $empreinteReelle = is_string($brut) ? hash('sha256', $brut) : null;
        $baselineIntegre = $empreinteReelle !== null
            && hash_equals(self::EMPREINTE_SHA256, $empreinteReelle);

        if (! $presente) {
            $divergences[] = 'source d’initialisation absente : '.$this->chemin;
        } elseif (! $baselineIntegre) {
            $divergences[] = 'empreinte de la source d’initialisation non concordante';
        }

        $attendus = null;
        if ($baselineIntegre) {
            try {
                /** @var array<string,int> $attendus */
                $attendus = $this->charger()['compteurs'];
            } catch (\Throwable $e) {
                $divergences[] = 'source d’initialisation illisible : '.$e->getMessage();
            }
        }

        $obtenus = null;
        try {
            $obtenus = $this->compteurs($pdo);
        } catch (\Throwable $e) {
            $divergences[] = 'index illisible : '.$e::class;
        }

        if (is_array($attendus) && is_array($obtenus)) {
            foreach ($attendus as $cle => $attendu) {
                $obtenu = $obtenus[$cle] ?? null;
                if ($obtenu !== $attendu) {
                    $divergences[] = sprintf(
                        '%s : attendu %d, présent %s',
                        $cle,
                        $attendu,
                        $obtenu === null ? '(illisible)' : (string) $obtenu,
                    );
                }
            }
        }

        return [
            'baseline' => [
                'chemin' => $this->chemin,
                'version' => self::VERSION,
                'presente' => $presente,
                'empreinte_attendue' => self::EMPREINTE_SHA256,
                'empreinte_reelle' => $empreinteReelle,
                'concorde' => $baselineIntegre,
            ],
            'index' => [
                'lisible' => $obtenus !== null,
                'attendus' => $attendus,
                'obtenus' => $obtenus,
            ],
            'divergences' => array_values($divergences),
            'coherent' => $divergences === [],
        ];
    }

    /** @return array<string,mixed> */
    private function charger(): array
    {
        if ($this->chargee !== null) {
            return $this->chargee;
        }
        if (! is_file($this->chemin)) {
            throw new \RuntimeException("Baseline opérationnelle introuvable : {$this->chemin}");
        }

        $brut = file_get_contents($this->chemin);
        if (! is_string($brut)) {
            throw new \RuntimeException("Baseline opérationnelle illisible : {$this->chemin}");
        }
        $empreinte = hash('sha256', $brut);
        if (! hash_equals(self::EMPREINTE_SHA256, $empreinte)) {
            throw new \RuntimeException(sprintf(
                'Empreinte de baseline invalide : attendu %s, obtenu %s.',
                self::EMPREINTE_SHA256,
                $empreinte,
            ));
        }

        $payload = json_decode($brut, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload)
            || ($payload['format'] ?? null) !== self::FORMAT
            || ($payload['version'] ?? null) !== self::VERSION
            || ! is_array($payload['compteurs'] ?? null)
            || ! is_array($payload['tables'] ?? null)) {
            throw new \RuntimeException('Format de baseline opérationnelle invalide.');
        }

        $tables = array_keys($payload['tables']);
        sort($tables);
        $attendues = self::TABLES;
        sort($attendues);
        if ($tables !== $attendues) {
            throw new \RuntimeException('Ensemble de tables inattendu dans la baseline opérationnelle.');
        }

        foreach (self::TABLES as $table) {
            $definition = $payload['tables'][$table] ?? null;
            if (! is_array($definition)
                || ! is_array($definition['colonnes'] ?? null)
                || ! is_array($definition['lignes'] ?? null)) {
                throw new \RuntimeException("Définition invalide pour la table {$table}.");
            }

            $colonnes = array_values($definition['colonnes']);
            if ($colonnes === [] || count($colonnes) !== count(array_unique($colonnes))) {
                throw new \RuntimeException("Colonnes invalides pour la table {$table}.");
            }
            foreach ($colonnes as $colonne) {
                if (! is_string($colonne) || preg_match('/^[a-z_]+$/', $colonne) !== 1) {
                    throw new \RuntimeException("Colonne non autorisée dans la table {$table}.");
                }
            }

            $idAttendu = 1;
            foreach ($definition['lignes'] as $ligne) {
                if (! is_array($ligne) || array_keys($ligne) !== $colonnes) {
                    throw new \RuntimeException("Ligne invalide dans la table {$table}.");
                }
                if (in_array('id', $colonnes, true)) {
                    if (($ligne['id'] ?? null) !== $idAttendu) {
                        throw new \RuntimeException(
                            "Les identifiants de {$table} doivent être continus à partir de 1."
                        );
                    }
                    $idAttendu++;
                }
            }
        }

        /** @var array<string,mixed> $payload */
        $this->chargee = $payload;

        return $payload;
    }

    /**
     * @param list<string> $colonnes
     * @param list<array<string,mixed>> $lignes
     */
    private function inserer(\PDO $pdo, string $table, array $colonnes, array $lignes): void
    {
        if ($lignes === []) {
            return;
        }

        // Les ID sont régénérés sur une base vide. La validation impose une
        // séquence 1..n, ce qui préserve les références numériques des enfants
        // sur SQLite comme sur PostgreSQL sans contourner GENERATED ALWAYS.
        $colonnesInsertion = array_values(array_filter(
            $colonnes,
            static fn (string $colonne): bool => $colonne !== 'id',
        ));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $colonnesInsertion),
            implode(', ', array_fill(0, count($colonnesInsertion), '?')),
        );
        $requete = $pdo->prepare($sql);

        foreach ($lignes as $ligne) {
            $valeurs = array_map(
                static fn (string $colonne): mixed => $ligne[$colonne],
                $colonnesInsertion,
            );
            $requete->execute($valeurs);
        }
    }

    /**
     * @return array{adoptions:int,normes:int,versions:int,statuts:int,etats:int,
     *               rangs:int,sources:int,fonctions:int,entites:int,regles:int,
     *               mandats:int,indetermines:int}
     */
    private function compteurs(\PDO $pdo): array
    {
        return [
            'adoptions' => $this->nombre($pdo, 'adoption'),
            'normes' => $this->nombre($pdo, 'norme'),
            'versions' => $this->nombre($pdo, 'version_norme'),
            'statuts' => $this->nombre($pdo, 'statut'),
            'etats' => $this->nombre($pdo, 'etat_capacite'),
            'rangs' => (int) $pdo->query(
                "SELECT count(*) FROM rang_normatif WHERE code <> 'INDETERMINE'"
            )->fetchColumn(),
            'sources' => $this->nombre($pdo, 'source'),
            'fonctions' => $this->nombre($pdo, 'fonction'),
            'entites' => $this->nombre($pdo, 'entite'),
            'regles' => $this->nombre($pdo, 'regle'),
            'mandats' => $this->nombre($pdo, 'mandat'),
            'indetermines' => (int) $pdo->query(
                "SELECT count(*) FROM norme WHERE rang_code = 'INDETERMINE'"
            )->fetchColumn(),
        ];
    }

    private function nombre(\PDO $pdo, string $table): int
    {
        return (int) $pdo->query("SELECT count(*) FROM {$table}")->fetchColumn();
    }
}
