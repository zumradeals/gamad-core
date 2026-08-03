<?php

declare(strict_types=1);

namespace Gamad\RegistreRealms;

/**
 * Migration additive du registre persistant de CAP-CORE-012.
 *
 * Onze tables, physiquement distinctes du registre d'identités
 * (`CAP-CORE-001`), du registre des organisations (`CAP-CORE-002`), du
 * registre des produits (`CAP-CORE-011`), du registre des contrats
 * (`CAP-CORE-009`) et de tout autre magasin. Les tables de cycle et
 * d'historique sont toutes en ajout seul : aucune ligne n'y est jamais
 * réécrite ni supprimée ; l'état courant est toujours la dernière ligne par
 * date d'effet.
 *
 * `realm_capacite` (fiche §21) n'est volontairement pas créée dans ce
 * chantier : la fiche l'autorise explicitement à rester absente tant
 * qu'aucun consommateur réel ne l'utilise (« Ne créer cette table que si au
 * moins un consommateur réel l'utilise pendant le chantier »). Aucun
 * consommateur de ce type n'existe aujourd'hui dans `main`.
 */
final class SchemaRealms
{
    public const VERSION = 1;

    /** Tables de données persistantes, hors table de migration. */
    public const TABLES = [
        'compteur_reference_realm',
        'realm',
        'realm_revision',
        'realm_cycle',
        'realm_relation',
        'realm_perimetre',
        'realm_identifiant_externe',
        'realm_organisation',
        'realm_produit',
        'realm_contrat',
        'realm_franchissement',
        'realm_verification',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_registre_realms (
                version      INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS compteur_reference_realm (
                type    TEXT PRIMARY KEY,
                dernier INTEGER NOT NULL
            )
        SQL);

        // Fiche de realm. Une identité canonique de type `realm` porte au
        // plus une fiche (UNIQUE sur identite_reference). La référence n'est
        // jamais réattribuée, y compris après retrait (fiche §11, §12).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm (
                reference                        TEXT PRIMARY KEY,
                identite_reference               TEXT NOT NULL UNIQUE,
                code_canonique                   TEXT NOT NULL UNIQUE,
                type_realm_reference              TEXT NOT NULL,
                source_reference                 TEXT NOT NULL,
                politique_inscription_reference   TEXT NOT NULL,
                producteur_reference              TEXT NOT NULL,
                preuve_reference                  TEXT NOT NULL,
                cree_le                           TEXT NOT NULL,
                modifie_le                        TEXT NOT NULL
            )
        SQL);

        // Révisions descriptives, en ajout seul : aucun nom d'affichage ou
        // classification antérieure n'est jamais réécrite (fiche §14).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_revision (
                id                                {$id},
                realm_reference                    TEXT NOT NULL,
                numero_revision                    INTEGER NOT NULL,
                nom_affichage                      TEXT NOT NULL,
                description                        TEXT,
                organisation_responsable_reference TEXT,
                classification_reference           TEXT NOT NULL,
                date_debut_validite                TEXT NOT NULL,
                date_fin_validite                  TEXT,
                acteur_reference                   TEXT NOT NULL,
                source_reference                   TEXT NOT NULL,
                preuve_reference                   TEXT NOT NULL,
                correlation_id                     TEXT,
                cree_le                             TEXT NOT NULL,
                UNIQUE(realm_reference, numero_revision)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_revision_realm
             ON realm_revision(realm_reference)'
        );

        // Cycle de vie, en ajout seul (fiche §15) : PREPARATION → ACTIF →
        // SUSPENDU → FERME → RETIRE. RETIRE est terminal et irréversible.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_cycle (
                id               {$id},
                realm_reference  TEXT NOT NULL,
                etat_reference   TEXT NOT NULL CHECK (etat_reference IN
                    ('PREPARATION','ACTIF','SUSPENDU','FERME','RETIRE')),
                date_effet       TEXT NOT NULL,
                motif_reference  TEXT,
                motif_detail     TEXT,
                acteur_reference TEXT NOT NULL,
                politique_reference TEXT NOT NULL,
                preuve_reference TEXT NOT NULL,
                correlation_id   TEXT,
                cree_le          TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_cycle_realm
             ON realm_cycle(realm_reference, date_effet, id)'
        );

        // Relations entre realms (fiche §16). La hiérarchie canonique
        // s'exprime uniquement en `PARENT_DE` : `INCLUS_DANS` est dérivée en
        // lecture, jamais enregistrée en double sens.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_relation (
                reference                    TEXT PRIMARY KEY,
                realm_source_reference       TEXT NOT NULL,
                realm_cible_reference        TEXT NOT NULL,
                type_relation_reference      TEXT NOT NULL,
                date_debut                   TEXT NOT NULL,
                date_fin                     TEXT,
                acteur_reference             TEXT NOT NULL,
                source_reference             TEXT NOT NULL,
                preuve_reference             TEXT NOT NULL,
                correlation_id                TEXT,
                cree_le                       TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_relation_source
             ON realm_relation(realm_source_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_relation_cible
             ON realm_relation(realm_cible_reference)'
        );

        // Dimensions bornant le realm (fiche §17). Aucune dimension ou
        // valeur libre n'est jamais utilisée par le moteur de portée : seules
        // les références canoniques comptent.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_perimetre (
                id                        {$id},
                realm_reference            TEXT NOT NULL,
                dimension_reference        TEXT NOT NULL,
                valeur_reference           TEXT NOT NULL,
                valeur_externe             TEXT,
                systeme_externe_reference  TEXT,
                date_debut                 TEXT NOT NULL,
                date_fin                   TEXT,
                acteur_reference           TEXT NOT NULL,
                source_reference           TEXT NOT NULL,
                preuve_reference           TEXT NOT NULL,
                cree_le                    TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_perimetre_realm
             ON realm_perimetre(realm_reference, dimension_reference)'
        );

        // Identifiants externes (fiche §18) — code ISO pays, référence
        // institutionnelle, etc. Le couple système/valeur est unique pendant
        // une période active.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_identifiant_externe (
                id               {$id},
                realm_reference   TEXT NOT NULL,
                systeme_reference TEXT NOT NULL,
                valeur            TEXT NOT NULL,
                date_debut        TEXT NOT NULL,
                date_fin          TEXT,
                source_reference  TEXT NOT NULL,
                preuve_reference  TEXT NOT NULL,
                cree_le           TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_identifiant_realm
             ON realm_identifiant_externe(realm_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_identifiant_systeme_valeur
             ON realm_identifiant_externe(systeme_reference, valeur)'
        );

        // Rattachement d'une organisation à un realm (fiche §19). Ne vaut
        // jamais autorisation ; `RESPONSABLE` ne donne pas de mandat.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_organisation (
                reference                TEXT PRIMARY KEY,
                realm_reference           TEXT NOT NULL,
                organisation_reference    TEXT NOT NULL,
                role_reference            TEXT NOT NULL,
                date_debut                TEXT NOT NULL,
                date_fin                  TEXT,
                classification_reference  TEXT NOT NULL,
                acteur_reference          TEXT NOT NULL,
                politique_reference       TEXT NOT NULL,
                source_reference          TEXT NOT NULL,
                preuve_reference          TEXT NOT NULL,
                correlation_id            TEXT,
                cree_le                   TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_organisation_realm
             ON realm_organisation(realm_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_organisation_org
             ON realm_organisation(organisation_reference)'
        );

        // Rattachement d'un produit à un realm (fiche §20). Aucune URL ni
        // secret d'environnement n'est jamais recopié ici.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_produit (
                reference                TEXT PRIMARY KEY,
                realm_reference           TEXT NOT NULL,
                produit_reference         TEXT NOT NULL,
                role_reference            TEXT NOT NULL,
                environnement_reference   TEXT,
                date_debut                TEXT NOT NULL,
                date_fin                  TEXT,
                acteur_reference          TEXT NOT NULL,
                politique_reference       TEXT NOT NULL,
                source_reference          TEXT NOT NULL,
                preuve_reference          TEXT NOT NULL,
                correlation_id            TEXT,
                cree_le                   TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_produit_realm
             ON realm_produit(realm_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_produit_produit
             ON realm_produit(produit_reference)'
        );

        // Association d'un contrat actif à un realm (fiche §22). Ne duplique
        // jamais le schéma du contrat, propriété exclusive de CAP-CORE-009.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_contrat (
                id                 {$id},
                realm_reference     TEXT NOT NULL,
                contrat_reference   TEXT NOT NULL,
                version_reference   TEXT,
                role_reference      TEXT NOT NULL,
                date_debut          TEXT NOT NULL,
                date_fin            TEXT,
                acteur_reference    TEXT NOT NULL,
                politique_reference TEXT NOT NULL,
                preuve_reference    TEXT NOT NULL,
                cree_le             TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_contrat_realm
             ON realm_contrat(realm_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_contrat_contrat
             ON realm_contrat(contrat_reference)'
        );

        // Franchissements explicites entre realms (fiche §23). Refus par
        // défaut ; un REFUSE applicable l'emporte toujours sur un PERMET.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_franchissement (
                id                        {$id},
                realm_source_reference     TEXT NOT NULL,
                realm_cible_reference      TEXT NOT NULL,
                objet_reference            TEXT NOT NULL,
                type_objet_reference       TEXT NOT NULL,
                effet_reference            TEXT NOT NULL CHECK (effet_reference IN ('PERMET','REFUSE')),
                finalite_reference         TEXT NOT NULL,
                contrat_reference          TEXT,
                date_debut                 TEXT NOT NULL,
                date_fin                   TEXT,
                politique_reference        TEXT NOT NULL,
                source_reference           TEXT NOT NULL,
                preuve_reference           TEXT NOT NULL,
                acteur_reference           TEXT NOT NULL,
                cree_le                    TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_franchissement_source_cible
             ON realm_franchissement(realm_source_reference, realm_cible_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_franchissement_finalite
             ON realm_franchissement(finalite_reference)'
        );

        // Vérifications de realm (fiche §24), en ajout seul. Une vérification
        // n'est jamais un score universel de confiance.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS realm_verification (
                id                            {$id},
                realm_reference                TEXT NOT NULL,
                type_verification_reference    TEXT NOT NULL,
                resultat_reference              TEXT NOT NULL,
                verifie_par_reference           TEXT NOT NULL,
                preuve_reference                TEXT NOT NULL,
                verifie_le                      TEXT NOT NULL,
                expire_le                       TEXT,
                motif                           TEXT,
                cree_le                         TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS realm_verification_realm
             ON realm_verification(realm_reference, verifie_le)'
        );

        $st = $pdo->prepare(
            'INSERT INTO migration_registre_realms(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_registre_realms WHERE version = ?
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
