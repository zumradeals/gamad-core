<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Import initial, en lecture seule côté SQLite et transactionnel côté cible.
 *
 * La cible doit être vide : cette contrainte évite les doublons silencieux et
 * rend l'opération mesurable. Les identifiants techniques auto-incrémentés ne
 * sont pas repris; les références métier et tous les faits le sont.
 */
final class ImportateurSqlite
{
    /** @var list<string> */
    private const TABLES_ACCES = [
        'authentificateur',
        'passkey',
        'session_ouverte',
    ];

    /** @var list<string> */
    private const TABLES_IDENTITES = [
        'compteur_reference_identite',
        'identite_inscrite',
        'evenement_cycle_identite',
        'evenement_assurance_identite',
        'relation_produit',
        'relation_organisation',
        'evenement_relation_identite',
        'rapprochement_identite',
    ];

    /** @var list<string> */
    private const TABLES_PRODUITS = [
        'produit',
        'produit_cycle',
        'produit_environnement',
    ];

    /** @var list<string> */
    private const TABLES_SOURCES = [
        'source',
        'source_cycle',
        'source_revision',
        'source_verification',
        'source_finalite',
        'source_lignee',
    ];

    /** @var list<string> */
    private const TABLES_POLITIQUES = [
        'politique',
        'politique_version',
        'regle_politique',
        'politique_version_cycle',
        'politique_simulation',
    ];

    /** @var list<string> */
    private const TABLES_CONTRATS = [
        'contrat',
        'contrat_version',
        'contrat_version_cycle',
        'contrat_partie',
        'contrat_operation',
        'contrat_schema',
        'contrat_erreur',
        'contrat_obligation',
        'contrat_compatibilite',
        'contrat_conformite',
        'contrat_projection',
    ];

    /** @var list<string> */
    private const TABLES_VOCABULAIRE = [
        'vocabulaire',
        'vocabulaire_version',
        'vocabulaire_version_cycle',
        'terme',
        'terme_libelle',
        'terme_alias',
        'terme_relation',
        'terme_mapping_externe',
        'terme_usage',
        'vocabulaire_compatibilite',
        'vocabulaire_conformite',
        'vocabulaire_projection',
    ];

    /** @return array<string,int> */
    public function importerAcces(string $source, \PDO $cible): array
    {
        // Les exports antérieurs à la migration WebAuthn ne portent pas
        // encore `passkey`. Ils restent importables ; si la table existe,
        // les credentials publics sont repris avant les sessions.
        return $this->importer($source, $cible, self::TABLES_ACCES, ['passkey']);
    }

    /** @return array<string,int> */
    public function importerIdentites(string $source, \PDO $cible): array
    {
        return $this->importer($source, $cible, self::TABLES_IDENTITES);
    }

    /** @return array<string,int> */
    public function importerProduits(string $source, \PDO $cible): array
    {
        return $this->importer($source, $cible, self::TABLES_PRODUITS);
    }

    /** @return array<string,int> */
    public function importerSources(string $source, \PDO $cible): array
    {
        return $this->importer($source, $cible, self::TABLES_SOURCES);
    }

    /** @return array<string,int> */
    public function importerPolitiques(string $source, \PDO $cible): array
    {
        return $this->importer($source, $cible, self::TABLES_POLITIQUES);
    }

    /** @return array<string,int> */
    public function importerContrats(string $source, \PDO $cible): array
    {
        return $this->importer($source, $cible, self::TABLES_CONTRATS);
    }

    /** @return array<string,int> */
    public function importerVocabulaire(string $source, \PDO $cible): array
    {
        return $this->importer($source, $cible, self::TABLES_VOCABULAIRE);
    }

    /**
     * @param  list<string>  $tables
     * @param  list<string>  $optionnelles
     * @return array<string,int>
     */
    private function importer(
        string $source,
        \PDO $cible,
        array $tables,
        array $optionnelles = [],
    ): array {
        if (! is_file($source) || ! is_readable($source)) {
            throw new \RuntimeException("Source SQLite absente ou illisible : {$source}");
        }

        $sqlite = new \PDO('sqlite:'.$source);
        $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $sqlite->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $cible->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $tables = array_values(array_filter(
            $tables,
            fn (string $table): bool => ! in_array($table, $optionnelles, true)
                || $this->tableExiste($sqlite, $table),
        ));

        foreach ($tables as $table) {
            $this->exigerTable($sqlite, $table);
            if ((int) $cible->query("SELECT count(*) FROM {$table}")->fetchColumn() !== 0) {
                throw new \RuntimeException(
                    "Import refusé : la table cible {$table} n’est pas vide.",
                );
            }
        }

        $propreTransaction = ! $cible->inTransaction();
        if ($propreTransaction) {
            $cible->beginTransaction();
        }

        try {
            $resultats = [];
            foreach ($tables as $table) {
                $colonnes = array_values(array_filter(
                    $this->colonnes($sqlite, $table),
                    static fn (string $colonne): bool => $colonne !== 'id'
                        && $colonne !== 'sequence_id',
                ));
                if ($colonnes === []) {
                    throw new \RuntimeException("Aucune colonne importable dans {$table}.");
                }

                $colonnesCible = $colonnes;
                $migrationSession = $table === 'session_ouverte'
                    && ! in_array('jeton_empreinte', $colonnes, true);
                if ($migrationSession) {
                    $colonnesCible[] = 'jeton_empreinte';
                }
                $nomsSource = implode(',', $colonnes);
                $nomsCible = implode(',', $colonnesCible);
                $marqueurs = implode(',', array_fill(0, count($colonnesCible), '?'));
                $inserer = $cible->prepare(
                    "INSERT INTO {$table} ({$nomsCible}) VALUES ({$marqueurs})",
                );
                $nombre = 0;
                foreach ($sqlite->query("SELECT {$nomsSource} FROM {$table}")->fetchAll() as $ligne) {
                    $valeurs = array_map(
                        static fn (string $colonne): mixed => $ligne[$colonne],
                        $colonnes,
                    );
                    if ($migrationSession) {
                        $jeton = (string) $ligne['reference'];
                        $empreinte = hash('sha256', $jeton);
                        $positionReference = array_search('reference', $colonnes, true);
                        $valeurs[$positionReference] = 'SINT-MIG-'.strtoupper(substr($empreinte, 0, 24));
                        $valeurs[] = $empreinte;
                    }
                    $inserer->execute($valeurs);
                    $nombre++;
                }

                $cibleNombre = (int) $cible->query(
                    "SELECT count(*) FROM {$table}",
                )->fetchColumn();
                if ($cibleNombre !== $nombre) {
                    throw new \RuntimeException(
                        "Contrôle de cardinalité échoué pour {$table}: {$nombre}/{$cibleNombre}.",
                    );
                }
                $resultats[$table] = $nombre;
            }

            if ($propreTransaction) {
                $cible->commit();
            }

            return $resultats;
        } catch (\Throwable $e) {
            if ($propreTransaction && $cible->inTransaction()) {
                $cible->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<string> */
    private function colonnes(\PDO $sqlite, string $table): array
    {
        return array_map(
            static fn (array $ligne): string => (string) $ligne['name'],
            $sqlite->query("PRAGMA table_info({$table})")->fetchAll(),
        );
    }

    private function exigerTable(\PDO $sqlite, string $table): void
    {
        if (! $this->tableExiste($sqlite, $table)) {
            throw new \RuntimeException("Table {$table} absente de la source SQLite.");
        }
    }

    private function tableExiste(\PDO $sqlite, string $table): bool
    {
        $st = $sqlite->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?",
        );
        $st->execute([$table]);

        return $st->fetchColumn() !== false;
    }
}
