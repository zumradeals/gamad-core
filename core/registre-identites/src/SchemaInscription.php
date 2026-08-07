<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Migration additive du registre persistant de CAP-CORE-001.
 *
 * Ces tables ne font jamais partie de Gamad\RegistreNormes\Schema et ne sont
 * donc jamais supprimées par une réindexation documentaire (INV-73). Le
 * registre persistant peut vivre dans une base distincte ; le mode à une seule
 * connexion reste supporté pour les tests et les migrations progressives.
 */
final class SchemaInscription
{
    public const VERSION = 2;

    /** Tables de données persistantes, hors table de migration. */
    public const TABLES = [
        'compteur_reference_identite',
        'identite_inscrite',
        'identifiant_resolution',
        'evenement_cycle_identite',
        'evenement_assurance_identite',
        'relation_produit',
        'relation_organisation',
        'evenement_relation_identite',
        'rapprochement_identite',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_registre_identites (
                version    INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS compteur_reference_identite (
                type    TEXT PRIMARY KEY,
                dernier INTEGER NOT NULL
            )
        SQL);

        // Fait de création immuable. État et assurance vivent exclusivement
        // dans leurs journaux en ajout seul.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS identite_inscrite (
                reference             TEXT PRIMARY KEY,
                type                  TEXT NOT NULL
                    CHECK (type IN ('personne','organisation','produit','realm','agent','service','INDETERMINE')),
                libelle               TEXT NOT NULL,
                regime                TEXT NOT NULL
                    CHECK (regime = 'INSCRIT_AU_REGISTRE'),
                provisoire            INTEGER NOT NULL CHECK (provisoire IN (0,1)),
                canal                 TEXT NOT NULL,
                producteur            TEXT NOT NULL,
                politique_inscription TEXT NOT NULL,
                source_inscription    TEXT NOT NULL,
                preuve_reference      TEXT NOT NULL,
                classification        TEXT NOT NULL,
                date_creation         TEXT NOT NULL
            )
        SQL);

        // Identifiants humains de résolution. La valeur brute (email, téléphone,
        // username...) n'est pas conservée : seule son empreinte normalisée sert
        // à retrouver la référence canonique. Un identifiant n'est ni une
        // identité, ni un secret d'authentification.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS identifiant_resolution (
                reference          TEXT PRIMARY KEY,
                identite_reference TEXT NOT NULL,
                type               TEXT NOT NULL CHECK (type IN ('EMAIL','TELEPHONE','USERNAME','EXTERNE')),
                empreinte          TEXT NOT NULL,
                etat               TEXT NOT NULL CHECK (etat IN ('NON_VERIFIE','VERIFIE','RETIRE')),
                source             TEXT NOT NULL,
                preuve_reference   TEXT NOT NULL,
                producteur         TEXT NOT NULL,
                date_debut         TEXT NOT NULL,
                classification     TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_identifiant_resolution_lookup
             ON identifiant_resolution(type, empreinte, etat)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_identifiant_resolution_identite
             ON identifiant_resolution(identite_reference, etat)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS evenement_cycle_identite (
                id                    {$id},
                identite_reference    TEXT NOT NULL,
                evenement_type        TEXT NOT NULL,
                etat_avant            TEXT,
                etat_apres            TEXT NOT NULL,
                source                TEXT NOT NULL,
                preuve_reference      TEXT NOT NULL,
                politique_inscription TEXT NOT NULL,
                acteur_reference      TEXT NOT NULL,
                date_effet            TEXT NOT NULL
            )
        SQL);

        // Seule table portant le niveau d'assurance d'une identité.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS evenement_assurance_identite (
                id                 {$id},
                identite_reference TEXT NOT NULL,
                niveau             TEXT NOT NULL CHECK (niveau IN ('A0','A1','A2','A3')),
                preuve_reference   TEXT NOT NULL,
                source             TEXT NOT NULL,
                producteur         TEXT NOT NULL,
                date_effet         TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS relation_produit (
                reference             TEXT PRIMARY KEY,
                identite_reference    TEXT NOT NULL,
                produit_reference     TEXT NOT NULL,
                relation_type         TEXT NOT NULL,
                sujet_local_opaque    TEXT,
                niveau_assurance      TEXT NOT NULL CHECK (niveau_assurance IN ('A0','A1','A2','A3')),
                source                TEXT NOT NULL,
                preuve_reference      TEXT NOT NULL,
                politique_inscription TEXT NOT NULL,
                producteur            TEXT NOT NULL,
                date_debut            TEXT NOT NULL,
                classification        TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS relation_organisation (
                reference                TEXT PRIMARY KEY,
                identite_reference       TEXT NOT NULL,
                organisation_reference   TEXT NOT NULL,
                relation_type            TEXT NOT NULL,
                mandat_reference         TEXT,
                mandat_verifie           INTEGER NOT NULL CHECK (mandat_verifie IN (0,1)),
                niveau_assurance         TEXT NOT NULL CHECK (niveau_assurance IN ('A0','A1','A2','A3')),
                source                   TEXT NOT NULL,
                preuve_reference         TEXT NOT NULL,
                politique_inscription    TEXT NOT NULL,
                producteur               TEXT NOT NULL,
                date_debut               TEXT NOT NULL,
                classification           TEXT NOT NULL
            )
        SQL);

        // Les fermetures s'ajoutent ici : aucune relation initiale n'est
        // modifiée pour recevoir un état ou une date de fin.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS evenement_relation_identite (
                id                 {$id},
                relation_reference TEXT NOT NULL,
                categorie          TEXT NOT NULL CHECK (categorie IN ('PRODUIT','ORGANISATION')),
                evenement_type     TEXT NOT NULL CHECK (evenement_type IN ('RATTACHEMENT','CLOTURE')),
                etat               TEXT NOT NULL CHECK (etat IN ('ACTIVE','CLOSE')),
                source             TEXT NOT NULL,
                preuve_reference   TEXT NOT NULL,
                producteur         TEXT NOT NULL,
                date_effet         TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS rapprochement_identite (
                reference             TEXT PRIMARY KEY,
                reference_a           TEXT NOT NULL,
                reference_b           TEXT NOT NULL,
                qualification         TEXT NOT NULL
                    CHECK (qualification IN ('CORRESPONDANCE_POSSIBLE','CORRESPONDANCE_PROBABLE')),
                preuves               TEXT NOT NULL,
                etat                  TEXT NOT NULL
                    CHECK (etat IN ('VALIDATION_REQUISE','VALIDEE','REJETEE')),
                decideur              TEXT,
                source                TEXT NOT NULL,
                politique_inscription TEXT NOT NULL,
                producteur            TEXT NOT NULL,
                date_effet            TEXT NOT NULL
            )
        SQL);

        $st = $pdo->prepare(
            'INSERT INTO migration_registre_identites(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_registre_identites WHERE version = ?
             )'
        );
        $st->execute([self::VERSION, date('c'), self::VERSION]);
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
