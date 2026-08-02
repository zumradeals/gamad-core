<?php

declare(strict_types=1);

namespace Gamad\RegistrePolitiques;

/**
 * Migration additive du registre persistant de CAP-CORE-007.
 *
 * Ces tables ne font jamais partie de Gamad\RegistreNormes\Schema et ne sont
 * donc jamais supprimées par une réindexation documentaire. Le cycle d'une
 * version et les règles sont des journaux en ajout seul ; une version qui a
 * quitté BROUILLON ne se modifie plus jamais en place.
 */
final class SchemaPolitiques
{
    public const VERSION = 1;

    /** Tables de données persistantes, hors table de migration. */
    public const TABLES = [
        'politique',
        'politique_version',
        'regle_politique',
        'politique_version_cycle',
        'politique_simulation',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_registre_politiques (
                version      INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        // Identité stable et immuable de la politique. Aucune règle n'y vit
        // directement : elles appartiennent à une version précise.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS politique (
                reference              TEXT PRIMARY KEY,
                libelle                TEXT NOT NULL,
                domaine                TEXT,
                proprietaire_reference TEXT NOT NULL,
                source_reference       TEXT NOT NULL,
                description            TEXT,
                politique_inscription  TEXT NOT NULL,
                producteur             TEXT NOT NULL,
                preuve_reference       TEXT NOT NULL,
                cree_le                TEXT NOT NULL,
                modifie_le             TEXT NOT NULL
            )
        SQL);

        // Versions en ajout seul. Une version quitte BROUILLON à la
        // soumission et devient alors immuable ; seul son cycle (table
        // suivante) continue d'évoluer.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS politique_version (
                id                   {$id},
                politique_reference  TEXT NOT NULL,
                version              TEXT NOT NULL,
                schema_version       INTEGER NOT NULL DEFAULT 1,
                description          TEXT,
                date_effet_prevue    TEXT,
                empreinte_contenu    TEXT,
                cree_par_reference   TEXT NOT NULL,
                preuve_reference     TEXT NOT NULL,
                cree_le              TEXT NOT NULL,
                UNIQUE(politique_reference, version)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS politique_version_politique
             ON politique_version(politique_reference)'
        );

        // Règles en ajout seul tant que la version est BROUILLON ; figées dès
        // la soumission. sujet_type/ressource_reference/ressource_type sont
        // réservés à une borne future plus fine ; seul sujet_reference porte
        // aujourd'hui la borne de sujet réellement évaluée par CTR-03.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS regle_politique (
                id                    {$id},
                politique_version_id  INTEGER NOT NULL,
                ordre                 INTEGER NOT NULL,
                effet                 TEXT NOT NULL CHECK (effet IN ('PERMET','REFUSE')),
                action_reference      TEXT NOT NULL,
                sujet_reference       TEXT,
                sujet_type            TEXT,
                ressource_reference   TEXT,
                ressource_type        TEXT,
                motif                 TEXT NOT NULL,
                cree_le               TEXT NOT NULL,
                UNIQUE(politique_version_id, ordre)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS regle_politique_version
             ON regle_politique(politique_version_id)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS regle_politique_action
             ON regle_politique(action_reference)'
        );

        // Cycle de version en ajout seul. L'état courant est toujours la
        // dernière ligne par date_effet ; le passé n'est jamais réécrit.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS politique_version_cycle (
                id                    {$id},
                politique_version_id  INTEGER NOT NULL,
                etat                  TEXT NOT NULL
                    CHECK (etat IN ('BROUILLON','EN_VALIDATION','ACTIVE','SUSPENDUE','REMPLACEE','RETIREE')),
                date_effet            TEXT NOT NULL,
                motif                 TEXT,
                acteur_reference      TEXT NOT NULL,
                preuve_reference      TEXT NOT NULL,
                correlation_id        TEXT,
                cree_le               TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS politique_version_cycle_version
             ON politique_version_cycle(politique_version_id, date_effet, id)'
        );

        // Simulations en ajout seul : une activation exige au moins une
        // simulation REUSSIE de la version exacte.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS politique_simulation (
                reference             TEXT PRIMARY KEY,
                politique_version_id  INTEGER NOT NULL,
                jeu_reference         TEXT NOT NULL,
                resultat              TEXT NOT NULL CHECK (resultat IN ('REUSSIE','ECHEC','INCOMPLETE')),
                resume_json           TEXT,
                acteur_reference      TEXT NOT NULL,
                cree_le               TEXT NOT NULL,
                expire_le             TEXT
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS politique_simulation_version
             ON politique_simulation(politique_version_id)'
        );

        $st = $pdo->prepare(
            'INSERT INTO migration_registre_politiques(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_registre_politiques WHERE version = ?
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
