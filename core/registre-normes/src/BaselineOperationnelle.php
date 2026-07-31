<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

/**
 * Reconstruit l'index technique à partir d'une photographie versionnée.
 *
 * Cette source est transitoire : elle remplace la lecture directe du corpus
 * Markdown pour les consommateurs déjà migrés, sans prétendre être le modèle
 * final des capacités GAMAD Core.
 */
final class BaselineOperationnelle
{
    private const FORMAT = 'gamad-core-index-baseline';
    private const VERSION = 1;
    private const EMPREINTE_SHA256 = 'f08f5b728942e38b00af3523d664f8589a134cfa9370fbd461ea232b01414b57';

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
