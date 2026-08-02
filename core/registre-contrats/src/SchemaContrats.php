<?php

declare(strict_types=1);

namespace Gamad\RegistreContrats;

/**
 * Migration additive du registre persistant de CAP-CORE-009.
 *
 * Onze tables, toutes distinctes de l'index reconstructible et des autres
 * magasins. Les versions sont immuables dès la soumission (EN_VALIDATION) ;
 * seul leur cycle continue d'évoluer, en ajout seul, comme pour
 * `core/registre-politiques`.
 */
final class SchemaContrats
{
    public const VERSION = 1;

    /** Tables de données persistantes, hors table de migration. */
    public const TABLES = [
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

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_registre_contrats (
                version      INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        // Identité stable et immuable du contrat. Les versions vivent à part.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat (
                reference                      TEXT PRIMARY KEY,
                nom                            TEXT NOT NULL,
                type_contrat                   TEXT NOT NULL,
                finalite_reference             TEXT NOT NULL,
                producteur_capacite_reference  TEXT,
                producteur_produit_reference   TEXT,
                proprietaire_reference         TEXT NOT NULL,
                source_reference               TEXT NOT NULL,
                description                    TEXT,
                cree_le                        TEXT NOT NULL,
                modifie_le                     TEXT NOT NULL
            )
        SQL);

        // Versions en ajout seul. Immuables dès la soumission : seul
        // `empreinte_contenu` est écrit une fois, à la soumission.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_version (
                id                       {$id},
                contrat_reference        TEXT NOT NULL,
                version                  TEXT NOT NULL,
                schema_version           INTEGER NOT NULL DEFAULT 1,
                compatibilite_annoncee   TEXT NOT NULL,
                description              TEXT,
                date_effet_prevue        TEXT,
                date_fin_prevue          TEXT,
                empreinte_contenu        TEXT,
                cree_par_reference       TEXT NOT NULL,
                preuve_reference         TEXT NOT NULL,
                cree_le                  TEXT NOT NULL,
                UNIQUE(contrat_reference, version)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_version_contrat
             ON contrat_version(contrat_reference)'
        );

        // Cycle en ajout seul, comme politique_version_cycle. L'état courant
        // est toujours la dernière ligne par date_effet.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_version_cycle (
                id                     {$id},
                contrat_version_id     INTEGER NOT NULL,
                etat                   TEXT NOT NULL CHECK (etat IN
                    ('BROUILLON','EN_VALIDATION','ACTIVE','DEPRECIEE','SUSPENDUE','REMPLACEE','RETIREE')),
                date_effet             TEXT NOT NULL,
                motif                  TEXT,
                plan_migration         TEXT,
                date_limite_migration  TEXT,
                acteur_reference       TEXT NOT NULL,
                preuve_reference       TEXT NOT NULL,
                correlation_id         TEXT,
                cree_le                TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_version_cycle_version
             ON contrat_version_cycle(contrat_version_id, date_effet, id)'
        );

        // Parties déclarées par version : un consommateur retiré d'une
        // nouvelle version n'y est simplement pas redéclaré.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_partie (
                id                   {$id},
                contrat_version_id   INTEGER NOT NULL,
                role                 TEXT NOT NULL CHECK (role IN
                    ('PRODUCTEUR','CONSOMMATEUR','OPERATEUR','VERIFICATEUR')),
                partie_type          TEXT NOT NULL CHECK (partie_type IN ('CAPACITE','PRODUIT')),
                partie_reference     TEXT NOT NULL,
                cree_le              TEXT NOT NULL,
                UNIQUE(contrat_version_id, role, partie_reference)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_partie_version
             ON contrat_partie(contrat_version_id)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_operation (
                id                     {$id},
                contrat_version_id     INTEGER NOT NULL,
                reference_operation    TEXT NOT NULL,
                type_operation         TEXT NOT NULL CHECK (type_operation IN
                    ('COMMANDER','INTERROGER','PUBLIER','CONSOMMER','VERIFIER','REVOQUER')),
                methode_http           TEXT,
                chemin_http            TEXT,
                action_autorisation    TEXT,
                duree_secondes         INTEGER,
                idempotente            INTEGER NOT NULL DEFAULT 0,
                audit_obligatoire      INTEGER NOT NULL DEFAULT 1,
                ordre                  INTEGER NOT NULL,
                cree_le                TEXT NOT NULL,
                UNIQUE(contrat_version_id, reference_operation)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_operation_version
             ON contrat_operation(contrat_version_id)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_schema (
                id                     {$id},
                contrat_version_id     INTEGER NOT NULL,
                operation_reference    TEXT,
                sens                   TEXT NOT NULL CHECK (sens IN ('ENTREE','SORTIE','EVENEMENT','ERREUR')),
                format                 TEXT NOT NULL CHECK (format IN
                    ('JSON_SCHEMA','OPENAPI_SCHEMA','TEXTE_STRUCTURE','AUCUN_CORPS')),
                contenu                TEXT,
                empreinte              TEXT NOT NULL,
                cree_le                TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_schema_version
             ON contrat_schema(contrat_version_id)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_erreur (
                id                     {$id},
                contrat_version_id     INTEGER NOT NULL,
                operation_reference    TEXT,
                code                   TEXT NOT NULL,
                statut_http            INTEGER,
                retentable             INTEGER NOT NULL DEFAULT 0,
                detail_exposable       INTEGER NOT NULL DEFAULT 1,
                description            TEXT NOT NULL,
                cree_le                TEXT NOT NULL,
                UNIQUE(contrat_version_id, code)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_erreur_version
             ON contrat_erreur(contrat_version_id)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_obligation (
                id                     {$id},
                contrat_version_id     INTEGER NOT NULL,
                type_obligation        TEXT NOT NULL CHECK (type_obligation IN
                    ('AUTORISATION','AUDIT','FINALITE','SOURCE','EXPIRATION','MINIMISATION',
                     'CONFIDENTIALITE','IDEMPOTENCE','ASSURANCE_SESSION')),
                description            TEXT NOT NULL,
                cree_le                TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_obligation_version
             ON contrat_obligation(contrat_version_id)'
        );

        // Ajout seul : chaque analyse est conservée, jamais réécrite.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_compatibilite (
                id                       {$id},
                contrat_version_id       INTEGER NOT NULL,
                version_comparee_id      INTEGER,
                resultat                 TEXT NOT NULL CHECK (resultat IN
                    ('COMPATIBLE','ADAPTATION_REQUISE','RUPTURE','INDETERMINE')),
                divergences_json         TEXT NOT NULL,
                acteur_reference         TEXT NOT NULL,
                cree_le                  TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_compatibilite_version
             ON contrat_compatibilite(contrat_version_id)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_conformite (
                id                     {$id},
                contrat_version_id     INTEGER NOT NULL,
                partie_reference       TEXT,
                resultat               TEXT NOT NULL CHECK (resultat IN ('CONFORME','NON_CONFORME','INCOMPLET')),
                artefact_reference     TEXT NOT NULL,
                resume                 TEXT,
                acteur_reference       TEXT NOT NULL,
                cree_le                TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_conformite_version
             ON contrat_conformite(contrat_version_id)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contrat_projection (
                id                     {$id},
                contrat_version_id     INTEGER NOT NULL,
                type_projection        TEXT NOT NULL CHECK (type_projection IN
                    ('OPENAPI','JSON_SCHEMA','PHP_INTERFACE','DOCUMENTATION')),
                contenu                TEXT NOT NULL,
                empreinte              TEXT NOT NULL,
                acteur_reference       TEXT NOT NULL,
                cree_le                TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS contrat_projection_version
             ON contrat_projection(contrat_version_id)'
        );

        $st = $pdo->prepare(
            'INSERT INTO migration_registre_contrats(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_registre_contrats WHERE version = ?
             )'
        );
        $st->execute([self::VERSION, gmdate('c'), self::VERSION]);
    }

    public static function presente(\PDO $pdo): bool
    {
        foreach (self::TABLES as $table) {
            try {
                $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            } catch (\PDOException) {
                return false;
            }
        }

        return true;
    }

    private static function driver(\PDO $pdo): string
    {
        return (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
