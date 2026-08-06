<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

use Gamad\EvenementsSortants\SchemaOutbox;

/**
 * Migration additive du magasin persistant de CAP-CORE-021.
 *
 * Vingt-deux tables (doc de chantier `docs/capacites/chantiers/CAP-CORE-021/
 * 02-modele-matching-et-segments.md`), physiquement distinctes de tout autre
 * magasin du Core. La publication transactionnelle vers CAP-CORE-014 réutilise
 * la table `evenement_sortant` partagée de `Gamad\EvenementsSortants`
 * (`SchemaOutbox`), déjà éprouvée par CAP-CORE-011 — aucune table
 * `matching_outbox` bespoke n'est créée en doublon.
 *
 * Les tables de cycle et d'historique sont en ajout seul : aucune ligne n'y
 * est jamais réécrite ni supprimée.
 */
final class SchemaMatching
{
    public const VERSION = 1;

    /** Tables de données persistantes propres au Matching, hors migration et outbox partagé. */
    public const TABLES = [
        'compteur_reference_matching',
        'matching_contexte',
        'matching_profil_execution',
        'matching_profil_critere',
        'matching_demande',
        'matching_objet',
        'matching_critere_demande',
        'matching_signal',
        'matching_question',
        'matching_execution',
        'matching_candidat',
        'matching_evaluation_critere',
        'matching_resultat',
        'matching_facteur',
        'matching_segment',
        'matching_segment_membre',
        'matching_activation',
        'matching_activation_mesure',
        'matching_contestation',
        'matching_reexamen',
        'matching_jeu_evaluation',
        'matching_comparaison_politique',
        'matching_cycle',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_moteur_matching (
                version      INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS compteur_reference_matching (
                type    TEXT PRIMARY KEY,
                dernier INTEGER NOT NULL
            )
        SQL);

        // Contexte (doc 02 §3) : domaine d'usage autorisé, une seule version
        // active par contexte et realm compatible.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_contexte (
                reference               TEXT PRIMARY KEY,
                code_canonique          TEXT NOT NULL UNIQUE,
                nom                     TEXT NOT NULL,
                finalite                TEXT NOT NULL,
                politique_reference     TEXT NOT NULL,
                politique_version       TEXT NOT NULL,
                classification          TEXT NOT NULL,
                supervision_humaine     TEXT NOT NULL,
                score_autorise          INTEGER NOT NULL DEFAULT 0,
                segment_autorise        INTEGER NOT NULL DEFAULT 0,
                activation_autorisee    INTEGER NOT NULL DEFAULT 0,
                mesure_autorisee        INTEGER NOT NULL DEFAULT 0,
                etat                    TEXT NOT NULL CHECK (etat IN
                    ('PREPARATION','ACTIF','SUSPENDU','RETIRE')),
                valide_depuis           TEXT NOT NULL,
                valide_jusqua           TEXT,
                source_reference        TEXT NOT NULL,
                preuve_reference        TEXT,
                created_at              TEXT NOT NULL
            )
        SQL);

        // Profil d'exécution (doc 02 §4) : compilation reproductible d'une
        // politique active de CAP-CORE-007, jamais souveraine elle-même.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_profil_execution (
                reference             TEXT PRIMARY KEY,
                contexte_reference    TEXT NOT NULL,
                politique_reference   TEXT NOT NULL,
                politique_version     TEXT NOT NULL,
                contrat_reference     TEXT NOT NULL,
                contrat_version       TEXT NOT NULL,
                algorithme_code       TEXT NOT NULL,
                algorithme_version    TEXT NOT NULL,
                plan_canonique_json   TEXT NOT NULL,
                plan_hash             TEXT NOT NULL,
                preuve_reference      TEXT,
                compile_le            TEXT NOT NULL,
                active_le             TEXT,
                retire_le             TEXT,
                etat                  TEXT NOT NULL CHECK (etat IN
                    ('COMPILE','ACTIF','RETIRE','INUTILISABLE'))
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_profil_execution_contexte
             ON matching_profil_execution(contexte_reference, etat)'
        );

        // Critères d'un profil (doc 02 §5).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_profil_critere (
                id                        {$id},
                profil_reference          TEXT NOT NULL,
                critere_reference         TEXT NOT NULL,
                ordre                     INTEGER NOT NULL,
                operateur                 TEXT NOT NULL,
                valeur_type               TEXT NOT NULL,
                obligatoire               INTEGER NOT NULL DEFAULT 0,
                poids                     REAL,
                seuil                     REAL,
                traitement_inconnu        TEXT NOT NULL CHECK (traitement_inconnu IN
                    ('INDETERMINE','IGNORER_AVEC_TRACE','ECHEC_CRITERE','REFUS_EXECUTION')),
                traitement_contradictoire TEXT,
                fraicheur_max_secondes    INTEGER,
                sources_autorisees_json   TEXT NOT NULL,
                explication_code          TEXT,
                facteur_public_autorise   INTEGER NOT NULL DEFAULT 0,
                exclusion_dure            INTEGER NOT NULL DEFAULT 0
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_profil_critere_profil
             ON matching_profil_critere(profil_reference, ordre)'
        );

        // Demande normalisée d'un consommateur (doc 02 §6-7).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_demande (
                reference                   TEXT PRIMARY KEY,
                idempotency_key             TEXT NOT NULL UNIQUE,
                consommateur_produit        TEXT NOT NULL,
                consommateur_organisation   TEXT,
                contexte_reference          TEXT NOT NULL,
                finalite_reference          TEXT NOT NULL,
                realm_reference             TEXT NOT NULL,
                environnement               TEXT NOT NULL,
                politique_reference         TEXT NOT NULL,
                politique_version           TEXT NOT NULL,
                profil_execution_reference  TEXT,
                objet_principal_type        TEXT,
                objet_principal_reference   TEXT,
                population_reference        TEXT,
                mode_resultat               TEXT NOT NULL CHECK (mode_resultat IN
                    ('QUALIFICATION_UNITAIRE','CORRESPONDANCE','CLASSEMENT',
                     'ESTIMATION_AGREGEE','SEGMENT_PROTEGE','APPARTENANCE_SEGMENT',
                     'COMPARAISON_POLITIQUE')),
                limite_resultats            INTEGER,
                classification              TEXT NOT NULL,
                etat                        TEXT NOT NULL CHECK (etat IN
                    ('PREPARATION','SOUMISE','VALIDEE','REFUSEE','EN_ATTENTE_SOURCES',
                     'EN_EXECUTION','TERMINEE','PARTIELLE','EN_ECHEC','ANNULEE','EXPIREE')),
                soumise_par                 TEXT NOT NULL,
                mandat_reference            TEXT,
                autorisation_reference      TEXT,
                contrat_reference           TEXT NOT NULL,
                contrat_version             TEXT NOT NULL,
                correlation_id              TEXT NOT NULL,
                expire_le                   TEXT,
                created_at                  TEXT NOT NULL,
                updated_at                  TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_demande_consommateur
             ON matching_demande(consommateur_produit, contexte_reference, etat)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_demande_realm
             ON matching_demande(realm_reference)'
        );

        // Objet apparié : offre, besoin, entité (doc 02 §8).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_objet (
                reference_interne       TEXT PRIMARY KEY,
                demande_reference       TEXT NOT NULL,
                role_objet              TEXT NOT NULL CHECK (role_objet IN
                    ('OFFRE','BESOIN','ENTITE','PROGRAMME','RESSOURCE','CANDIDAT')),
                objet_type              TEXT NOT NULL,
                objet_reference_externe TEXT,
                source_reference        TEXT NOT NULL,
                contrat_reference       TEXT NOT NULL,
                version_objet           TEXT,
                empreinte_objet         TEXT NOT NULL,
                valide_depuis           TEXT NOT NULL,
                valide_jusqua           TEXT,
                classification          TEXT NOT NULL,
                snapshot_minimal_json   TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_objet_demande
             ON matching_objet(demande_reference)'
        );

        // Critère effectif d'une demande, validé contre le profil (doc 02 §9).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_critere_demande (
                id                       {$id},
                demande_reference        TEXT NOT NULL,
                critere_reference        TEXT NOT NULL,
                operateur                TEXT NOT NULL,
                valeur_normalisee_json   TEXT NOT NULL,
                obligatoire              INTEGER NOT NULL DEFAULT 0,
                poids_effectif           REAL,
                origine                  TEXT NOT NULL CHECK (origine IN
                    ('POLITIQUE','CONSOMMATEUR_AUTORISE','OBJET_SOURCE',
                     'OBLIGATION_REALM','RESTRICTION_RISQUE')),
                source_exigee            TEXT,
                ordre                    INTEGER NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_critere_demande_demande
             ON matching_critere_demande(demande_reference, ordre)'
        );

        // Signal matérialisé, minimal et temporaire (doc 02 §10).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_signal (
                reference               TEXT PRIMARY KEY,
                sujet_type              TEXT NOT NULL,
                sujet_reference         TEXT NOT NULL,
                signal_code             TEXT NOT NULL,
                valeur_type             TEXT NOT NULL,
                valeur_normalisee_json  TEXT NOT NULL,
                source_reference        TEXT NOT NULL,
                source_revision         TEXT,
                finalite_reference      TEXT NOT NULL,
                realm_reference         TEXT NOT NULL,
                contrat_reference       TEXT NOT NULL,
                contrat_version         TEXT NOT NULL,
                observation_le          TEXT NOT NULL,
                valide_jusqua           TEXT NOT NULL,
                confiance_source        REAL,
                preuve_reference        TEXT,
                classification          TEXT NOT NULL,
                statut                  TEXT NOT NULL CHECK (statut IN
                    ('VALIDE','PERIME','REVOQUE','CONTRADICTOIRE','INUTILISABLE',
                     'SUPPRIME_LOGIQUEMENT')),
                recu_le                 TEXT NOT NULL,
                expire_le               TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_signal_sujet
             ON matching_signal(sujet_type, sujet_reference, signal_code, statut)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_signal_expiration
             ON matching_signal(expire_le)'
        );

        // Interrogation à la demande (doc 02 §11) : réponse minimale, jamais
        // le document original.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_question (
                question_reference   TEXT PRIMARY KEY,
                source_reference     TEXT NOT NULL,
                contrat_reference    TEXT NOT NULL,
                question_hash        TEXT NOT NULL,
                reponse_code         TEXT NOT NULL CHECK (reponse_code IN
                    ('OUI','NON','INDETERMINE')),
                niveau_preuve        TEXT,
                valide_jusqua        TEXT NOT NULL,
                preuve_reference     TEXT,
                recu_le              TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_question_source
             ON matching_question(source_reference, question_hash)'
        );

        // Exécution (doc 02 §12) : une exécution terminée est immuable.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_execution (
                reference                  TEXT PRIMARY KEY,
                demande_reference          TEXT NOT NULL,
                profil_execution_reference TEXT NOT NULL,
                algorithme_code            TEXT NOT NULL,
                algorithme_version         TEXT NOT NULL,
                jeu_donnees_hash           TEXT,
                plan_hash                  TEXT NOT NULL,
                demarre_le                 TEXT NOT NULL,
                termine_le                 TEXT,
                etat                       TEXT NOT NULL CHECK (etat IN
                    ('PLANIFIEE','EN_COURS','TERMINEE','PARTIELLE','EN_ECHEC','ANNULEE')),
                candidats_total            INTEGER NOT NULL DEFAULT 0,
                candidats_evalues          INTEGER NOT NULL DEFAULT 0,
                candidats_refuses          INTEGER NOT NULL DEFAULT 0,
                resultats_total            INTEGER NOT NULL DEFAULT 0,
                signaux_utilises           INTEGER NOT NULL DEFAULT 0,
                signaux_inconnus           INTEGER NOT NULL DEFAULT 0,
                signaux_contradictoires    INTEGER NOT NULL DEFAULT 0,
                preuve_reference           TEXT,
                correlation_id             TEXT NOT NULL,
                erreur_code                TEXT
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_execution_demande
             ON matching_execution(demande_reference, etat)'
        );

        // Candidat évalué dans une exécution (doc 02 §13) — table restreinte,
        // jamais exposée telle quelle par API.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_candidat (
                id                     {$id},
                execution_reference    TEXT NOT NULL,
                candidat_reference     TEXT NOT NULL,
                sujet_type             TEXT NOT NULL,
                sujet_reference        TEXT NOT NULL,
                realm_reference        TEXT NOT NULL,
                admis_evaluation       INTEGER NOT NULL DEFAULT 0,
                motif_refus_code       TEXT,
                donnees_snapshot_hash  TEXT,
                UNIQUE(execution_reference, candidat_reference)
            )
        SQL);

        // Évaluation d'un critère pour un candidat (doc 02 §14).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_evaluation_critere (
                id                      {$id},
                execution_reference     TEXT NOT NULL,
                candidat_reference      TEXT NOT NULL,
                critere_reference       TEXT NOT NULL,
                etat_evaluation         TEXT NOT NULL CHECK (etat_evaluation IN
                    ('SATISFAIT','DEFAVORABLE','NON_ETABLI','CONTRADICTOIRE',
                     'INTERDIT','NON_APPLICABLE')),
                valeur_observee_hash    TEXT,
                source_reference        TEXT,
                observation_le          TEXT,
                fraicheur               INTEGER,
                confiance_source        REAL,
                contribution_score      REAL,
                motif_code              TEXT,
                preuve_reference        TEXT,
                UNIQUE(execution_reference, candidat_reference, critere_reference)
            )
        SQL);

        // Résultat (doc 02 §15) : pertinence et confiance distinctes,
        // non_decisionnel obligatoire.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_resultat (
                reference                    TEXT PRIMARY KEY,
                execution_reference          TEXT NOT NULL,
                candidat_reference           TEXT,
                resultat_type                TEXT NOT NULL,
                classe_resultat              TEXT NOT NULL CHECK (classe_resultat IN
                    ('CORRESPONDANCE_FORTE','CORRESPONDANCE_PROBABLE',
                     'CORRESPONDANCE_PARTIELLE','NON_CORRESPONDANT','INDETERMINE',
                     'INTERDIT','POPULATION_TROP_REDUITE')),
                pertinence                   REAL,
                confiance                    REAL,
                rang                         INTEGER,
                facteurs_favorables_nombre   INTEGER NOT NULL DEFAULT 0,
                facteurs_defavorables_nombre INTEGER NOT NULL DEFAULT 0,
                facteurs_inconnus_nombre     INTEGER NOT NULL DEFAULT 0,
                obligations_json             TEXT NOT NULL,
                non_decisionnel              INTEGER NOT NULL DEFAULT 1,
                politique_reference          TEXT NOT NULL,
                politique_version            TEXT NOT NULL,
                source_set_hash              TEXT,
                preuve_reference             TEXT,
                expire_le                    TEXT NOT NULL,
                created_at                   TEXT NOT NULL,
                CHECK (non_decisionnel = 1)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_resultat_execution
             ON matching_resultat(execution_reference, rang)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_resultat_expiration
             ON matching_resultat(expire_le)'
        );

        // Facteur explicatif d'un résultat (doc 02 §16).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_facteur (
                id                    {$id},
                resultat_reference    TEXT NOT NULL,
                critere_reference     TEXT NOT NULL,
                nature                TEXT NOT NULL CHECK (nature IN
                    ('FAVORABLE','DEFAVORABLE','NON_ETABLI','CONTRADICTOIRE','RESTRICTION')),
                code_explication      TEXT NOT NULL,
                importance             REAL,
                source_reference      TEXT,
                public_autorise       INTEGER NOT NULL DEFAULT 0,
                ordre                 INTEGER NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_facteur_resultat
             ON matching_facteur(resultat_reference, ordre)'
        );

        // Segment protégé (doc 02 §17) : expiration obligatoire, export brut
        // refusé par défaut.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_segment (
                reference                            TEXT PRIMARY KEY,
                demande_reference                    TEXT NOT NULL,
                execution_reference                  TEXT NOT NULL,
                contexte_reference                   TEXT NOT NULL,
                consommateur_produit                 TEXT NOT NULL,
                finalite_reference                   TEXT NOT NULL,
                realm_reference                       TEXT NOT NULL,
                politique_reference                  TEXT NOT NULL,
                politique_version                    TEXT NOT NULL,
                population_nombre                    INTEGER NOT NULL,
                membres_hash                          TEXT NOT NULL,
                classification                        TEXT NOT NULL,
                export_brut_autorise                  INTEGER NOT NULL DEFAULT 0,
                verification_appartenance_autorisee   INTEGER NOT NULL DEFAULT 1,
                activation_autorisee                  INTEGER NOT NULL DEFAULT 0,
                etat                                  TEXT NOT NULL CHECK (etat IN
                    ('PREPARATION','ACTIF','SUSPENDU','EXPIRE','REVOQUE')),
                cree_le                               TEXT NOT NULL,
                active_le                             TEXT,
                expire_le                             TEXT NOT NULL,
                suspendu_le                           TEXT,
                revoque_le                            TEXT,
                preuve_reference                      TEXT,
                CHECK (export_brut_autorise = 0)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_segment_consommateur
             ON matching_segment(consommateur_produit, contexte_reference, etat)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_segment_expiration
             ON matching_segment(expire_le)'
        );

        // Membre de segment (doc 02 §18) : token opaque, jamais réutilisable
        // hors de son segment, son consommateur et sa finalité.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_segment_membre (
                id                        {$id},
                segment_reference         TEXT NOT NULL,
                membre_token              TEXT NOT NULL UNIQUE,
                sujet_reference_interne   TEXT NOT NULL,
                ajoute_par_resultat       TEXT,
                valide_depuis             TEXT NOT NULL,
                valide_jusqua             TEXT NOT NULL,
                statut                    TEXT NOT NULL CHECK (statut IN
                    ('ACTIF','RETIRE','EXPIRE','REVOQUE')),
                preuve_reference          TEXT
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_segment_membre_segment
             ON matching_segment_membre(segment_reference, statut)'
        );

        // Activation (doc 02 §20) : usage exact et borné d'un segment.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_activation (
                reference                   TEXT PRIMARY KEY,
                segment_reference           TEXT NOT NULL,
                consommateur_produit        TEXT NOT NULL,
                consommateur_organisation   TEXT,
                contexte_reference          TEXT NOT NULL,
                finalite_reference          TEXT NOT NULL,
                realm_reference             TEXT NOT NULL,
                environnement               TEXT NOT NULL,
                contrat_reference           TEXT NOT NULL,
                contrat_version             TEXT NOT NULL,
                decision_reference          TEXT,
                autorisation_reference      TEXT NOT NULL,
                obligations_json            TEXT NOT NULL,
                quota                       INTEGER,
                usage_autorise              TEXT NOT NULL,
                etat                        TEXT NOT NULL CHECK (etat IN
                    ('DEMANDEE','REFUSEE','AUTORISEE','ACTIVE','SUSPENDUE',
                     'TERMINEE','EXPIREE','REVOQUEE','EN_ECHEC')),
                demande_le                  TEXT NOT NULL,
                autorise_le                 TEXT,
                active_le                   TEXT,
                expire_le                   TEXT NOT NULL,
                termine_le                  TEXT,
                revoque_le                  TEXT,
                preuve_reference            TEXT
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_activation_segment
             ON matching_activation(segment_reference, etat)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_activation_consommateur
             ON matching_activation(consommateur_produit, contexte_reference)'
        );

        // Mesure d'activation (doc 02 §22) : agrégée, contractuelle.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_activation_mesure (
                reference             TEXT PRIMARY KEY,
                activation_reference  TEXT NOT NULL,
                mesure_code           TEXT NOT NULL,
                valeur_numerique      REAL,
                valeur_categorielle   TEXT,
                population_reference  TEXT,
                fenetre_debut         TEXT NOT NULL,
                fenetre_fin           TEXT NOT NULL,
                source_reference      TEXT NOT NULL,
                contrat_reference     TEXT NOT NULL,
                preuve_reference      TEXT,
                classification        TEXT NOT NULL,
                recu_le               TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_activation_mesure_activation
             ON matching_activation_mesure(activation_reference, mesure_code)'
        );

        // Contestation (doc 02 §23).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_contestation (
                reference             TEXT PRIMARY KEY,
                resultat_reference    TEXT,
                segment_reference     TEXT,
                activation_reference  TEXT,
                contestant_reference  TEXT NOT NULL,
                motif_code            TEXT NOT NULL,
                description_minimale  TEXT,
                source_contestee      TEXT,
                ouvert_le             TEXT NOT NULL,
                etat                  TEXT NOT NULL CHECK (etat IN
                    ('OUVERTE','RECEVABLE','NON_RECEVABLE','EN_INSTRUCTION',
                     'CORRECTION_SOURCE_ATTENDUE','REEXECUTION_ATTENDUE',
                     'RESOLUE','REJETEE','CLOTUREE')),
                responsable           TEXT,
                realm_reference       TEXT NOT NULL,
                classification        TEXT NOT NULL,
                preuve_initiale       TEXT
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_contestation_resultat
             ON matching_contestation(resultat_reference)'
        );

        // Réexamen (doc 02 §24).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_reexamen (
                reference               TEXT PRIMARY KEY,
                contestation_reference  TEXT NOT NULL,
                ancienne_execution      TEXT NOT NULL,
                nouvelle_execution      TEXT,
                sources_corrigees_json  TEXT,
                politique_reference     TEXT NOT NULL,
                politique_version       TEXT NOT NULL,
                resultat_ancien         TEXT,
                resultat_nouveau        TEXT,
                ecart_json              TEXT,
                decision_reference      TEXT,
                preuve_reference        TEXT,
                termine_le              TEXT,
                verdict                 TEXT CHECK (verdict IN
                    ('CONFIRME','MODIFIE','ANNULE','DEVENU_INDETERMINE','DEVENU_INTERDIT'))
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_reexamen_contestation
             ON matching_reexamen(contestation_reference)'
        );

        // Jeu d'évaluation synthétique (doc 02 §25) : jamais de production copiée.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_jeu_evaluation (
                reference           TEXT PRIMARY KEY,
                contexte_reference  TEXT NOT NULL,
                version             TEXT NOT NULL,
                source_reference    TEXT NOT NULL,
                population_type     TEXT NOT NULL CHECK (population_type IN
                    ('SYNTHETIQUE','ANONYMISE','AUTORISE_JURIDIQUEMENT')),
                cas_nombre          INTEGER NOT NULL,
                classification      TEXT NOT NULL,
                empreinte           TEXT NOT NULL,
                preuve_reference    TEXT,
                valide_depuis       TEXT NOT NULL,
                valide_jusqua       TEXT,
                etat                TEXT NOT NULL CHECK (etat IN ('ACTIF','RETIRE'))
            )
        SQL);

        // Comparaison de deux versions de politique (doc 02 §26).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_comparaison_politique (
                reference             TEXT PRIMARY KEY,
                contexte              TEXT NOT NULL,
                politique_a           TEXT NOT NULL,
                version_a             TEXT NOT NULL,
                politique_b           TEXT NOT NULL,
                version_b             TEXT NOT NULL,
                jeu_evaluation        TEXT NOT NULL,
                population_nombre     INTEGER NOT NULL,
                resultats_changes     INTEGER NOT NULL DEFAULT 0,
                portee_delta          REAL,
                indetermines_delta    REAL,
                faux_positifs_delta   REAL,
                faux_negatifs_delta   REAL,
                couverture_delta      REAL,
                equite_ecarts_json    TEXT,
                risques_reference     TEXT,
                preuve_reference      TEXT,
                created_at            TEXT NOT NULL
            )
        SQL);

        // Cycle de la demande (doc 02 §6) : transitions en ajout seul.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS matching_cycle (
                id                 {$id},
                demande_reference  TEXT NOT NULL,
                etat_reference     TEXT NOT NULL,
                date_effet         TEXT NOT NULL,
                motif_reference    TEXT,
                motif_detail       TEXT,
                acteur_reference   TEXT NOT NULL,
                preuve_reference   TEXT,
                cree_le            TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS matching_cycle_demande
             ON matching_cycle(demande_reference)'
        );

        // Outbox transactionnelle partagée (CAP-CORE-014), dans le magasin du Matching.
        SchemaOutbox::migrer($pdo);

        $st = $pdo->prepare(
            'INSERT INTO migration_moteur_matching(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_moteur_matching WHERE version = ?
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
