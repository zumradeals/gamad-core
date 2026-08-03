<?php

declare(strict_types=1);

namespace Gamad\RegistreOrganisations;

/**
 * Migration additive du registre persistant de CAP-CORE-002.
 *
 * Onze tables, physiquement distinctes du registre d'identités
 * (`CAP-CORE-001`), du registre des mandats (`CAP-CORE-003`) et de tout autre
 * magasin. Les tables de cycle sont toutes en ajout seul : aucune ligne n'y
 * est jamais réécrite ni supprimée ; l'état courant est toujours la dernière
 * ligne par `date_effet`.
 */
final class SchemaOrganisations
{
    public const VERSION = 1;

    /** Tables de données persistantes, hors table de migration. */
    public const TABLES = [
        'compteur_reference_organisation',
        'organisation',
        'organisation_revision',
        'organisation_cycle',
        'organisation_identifiant_externe',
        'organisation_unite',
        'organisation_unite_cycle',
        'organisation_relation',
        'organisation_affiliation',
        'organisation_affiliation_cycle',
        'organisation_fonction_interne',
        'organisation_mandat_projection',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_registre_organisations (
                version      INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS compteur_reference_organisation (
                type    TEXT PRIMARY KEY,
                dernier INTEGER NOT NULL
            )
        SQL);

        // Fiche organisationnelle. Une identité canonique de type
        // `organisation` porte au plus une fiche (UNIQUE sur identite_reference).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation (
                reference                      TEXT PRIMARY KEY,
                identite_reference             TEXT NOT NULL UNIQUE,
                type_organisation_reference    TEXT NOT NULL,
                personnalite_juridique         INTEGER NOT NULL CHECK (personnalite_juridique IN (0,1)),
                proprietaire_reference         TEXT NOT NULL,
                source_reference               TEXT NOT NULL,
                politique_inscription_reference TEXT NOT NULL,
                preuve_reference               TEXT NOT NULL,
                cree_par_reference             TEXT NOT NULL,
                cree_le                        TEXT NOT NULL,
                modifie_le                     TEXT NOT NULL
            )
        SQL);

        // Révisions descriptives, en ajout seul : aucune dénomination
        // antérieure n'est jamais réécrite.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_revision (
                id                       {$id},
                organisation_reference   TEXT NOT NULL,
                numero_revision          INTEGER NOT NULL,
                denomination_officielle  TEXT NOT NULL,
                nom_court                TEXT,
                nom_commercial           TEXT,
                description              TEXT,
                forme_reference          TEXT,
                classification_reference TEXT NOT NULL,
                date_effet               TEXT NOT NULL,
                acteur_reference         TEXT NOT NULL,
                source_reference         TEXT NOT NULL,
                preuve_reference         TEXT NOT NULL,
                correlation_id           TEXT,
                cree_le                  TEXT NOT NULL,
                UNIQUE(organisation_reference, numero_revision)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_revision_org
             ON organisation_revision(organisation_reference)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_cycle (
                id                     {$id},
                organisation_reference TEXT NOT NULL,
                etat_reference         TEXT NOT NULL CHECK (etat_reference IN
                    ('PREPARATION','ACTIVE','SUSPENDUE','DISSOUTE','RETIREE')),
                date_effet             TEXT NOT NULL,
                motif                  TEXT,
                acteur_reference       TEXT NOT NULL,
                politique_reference    TEXT NOT NULL,
                preuve_reference       TEXT NOT NULL,
                correlation_id         TEXT,
                cree_le                TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_cycle_org
             ON organisation_cycle(organisation_reference, date_effet, id)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_identifiant_externe (
                id                       {$id},
                organisation_reference   TEXT NOT NULL,
                systeme_reference        TEXT NOT NULL,
                type_identifiant_reference TEXT NOT NULL,
                valeur_normalisee        TEXT NOT NULL,
                valeur_affichage         TEXT,
                pays_ou_realm_reference  TEXT,
                date_debut               TEXT NOT NULL,
                date_fin                 TEXT,
                verifie                  INTEGER NOT NULL CHECK (verifie IN (0,1)),
                source_reference         TEXT NOT NULL,
                preuve_reference         TEXT NOT NULL,
                cree_le                  TEXT NOT NULL,
                UNIQUE(systeme_reference, type_identifiant_reference, valeur_normalisee)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_identifiant_org
             ON organisation_identifiant_externe(organisation_reference)'
        );

        // Une unité appartient à une seule organisation ; la hiérarchie est
        // vérifiée applicativement (acyclicité) à chaque création/déplacement.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_unite (
                reference                TEXT PRIMARY KEY,
                organisation_reference   TEXT NOT NULL,
                unite_parente_reference  TEXT,
                type_unite_reference     TEXT NOT NULL,
                nom                      TEXT NOT NULL,
                code_interne             TEXT,
                realm_reference          TEXT,
                classification_reference TEXT NOT NULL,
                date_debut               TEXT NOT NULL,
                source_reference         TEXT NOT NULL,
                preuve_reference         TEXT NOT NULL,
                cree_le                  TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_unite_org
             ON organisation_unite(organisation_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_unite_parente
             ON organisation_unite(unite_parente_reference)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_unite_cycle (
                id               {$id},
                unite_reference  TEXT NOT NULL,
                etat_reference   TEXT NOT NULL CHECK (etat_reference IN ('ACTIVE','SUSPENDUE','FERMEE')),
                date_effet       TEXT NOT NULL,
                motif            TEXT,
                acteur_reference TEXT NOT NULL,
                preuve_reference TEXT NOT NULL,
                correlation_id   TEXT,
                cree_le          TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_unite_cycle_unite
             ON organisation_unite_cycle(unite_reference, date_effet, id)'
        );

        // Relations organisation-à-organisation. L'acyclicité hiérarchique
        // est contrôlée applicativement pour PARENTE_DE/FILIALE_DE.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_relation (
                reference                       TEXT PRIMARY KEY,
                organisation_source_reference   TEXT NOT NULL,
                organisation_cible_reference    TEXT NOT NULL,
                type_relation_reference         TEXT NOT NULL,
                date_debut                      TEXT NOT NULL,
                date_fin                        TEXT,
                pourcentage                     REAL,
                classification_reference        TEXT NOT NULL,
                source_reference                TEXT NOT NULL,
                preuve_reference                TEXT NOT NULL,
                acteur_reference                TEXT NOT NULL,
                cree_le                         TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_relation_source
             ON organisation_relation(organisation_source_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_relation_cible
             ON organisation_relation(organisation_cible_reference)'
        );

        // Rattachement d'une identité (personne ou organisation) à une
        // organisation. Ne vaut jamais mandat : voir organisation_fonction_interne
        // et organisation_mandat_projection, et CAP-CORE-003.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_affiliation (
                reference                TEXT PRIMARY KEY,
                organisation_reference   TEXT NOT NULL,
                identite_reference       TEXT NOT NULL,
                unite_reference          TEXT,
                type_affiliation_reference TEXT NOT NULL,
                date_debut               TEXT NOT NULL,
                date_fin_prevue          TEXT,
                niveau_assurance_reference TEXT NOT NULL,
                classification_reference TEXT NOT NULL,
                source_reference         TEXT NOT NULL,
                preuve_reference         TEXT NOT NULL,
                producteur_reference     TEXT NOT NULL,
                acteur_reference         TEXT NOT NULL,
                cree_le                  TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_affiliation_org
             ON organisation_affiliation(organisation_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_affiliation_identite
             ON organisation_affiliation(identite_reference)'
        );

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_affiliation_cycle (
                id                     {$id},
                affiliation_reference  TEXT NOT NULL,
                etat_reference         TEXT NOT NULL CHECK (etat_reference IN
                    ('PROPOSEE','ACTIVE','SUSPENDUE','CLOSE','REJETEE')),
                date_effet             TEXT NOT NULL,
                motif                  TEXT,
                acteur_reference       TEXT NOT NULL,
                politique_reference    TEXT NOT NULL,
                preuve_reference       TEXT NOT NULL,
                correlation_id         TEXT,
                cree_le                TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_affiliation_cycle_afl
             ON organisation_affiliation_cycle(affiliation_reference, date_effet, id)'
        );

        // Fonction descriptive uniquement. `mandat_fonction_reference` est une
        // extension technique locale : lorsqu'elle est renseignée, elle porte
        // la référence de la fonction correspondante dans CAP-CORE-003, seule
        // habilitée à en vérifier le mandat (verifierRepresentation()).
        // Une fonction sans cette référence, ou dont CAP-CORE-003 ne connaît
        // pas la fonction, reste strictement descriptive et non opposable.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_fonction_interne (
                reference                 TEXT PRIMARY KEY,
                organisation_reference    TEXT NOT NULL,
                unite_reference           TEXT,
                type_fonction_reference   TEXT NOT NULL,
                libelle                   TEXT NOT NULL,
                mandat_fonction_reference TEXT,
                date_debut                TEXT NOT NULL,
                date_fin                  TEXT,
                source_reference          TEXT NOT NULL,
                preuve_reference          TEXT NOT NULL,
                cree_le                   TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_fonction_org
             ON organisation_fonction_interne(organisation_reference)'
        );

        // Projection locale facultative, jamais la source de vérité : voir
        // CAP-CORE-003. Reconstruite, jamais modifiée directement.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS organisation_mandat_projection (
                id                          {$id},
                mandat_reference            TEXT NOT NULL,
                organisation_reference      TEXT NOT NULL,
                identite_reference          TEXT NOT NULL,
                fonction_interne_reference  TEXT,
                etat                        TEXT NOT NULL,
                date_debut                  TEXT,
                date_fin                    TEXT,
                synchronise_le              TEXT NOT NULL,
                UNIQUE(mandat_reference, organisation_reference, identite_reference)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organisation_mandat_projection_org
             ON organisation_mandat_projection(organisation_reference)'
        );

        $st = $pdo->prepare(
            'INSERT INTO migration_registre_organisations(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_registre_organisations WHERE version = ?
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
