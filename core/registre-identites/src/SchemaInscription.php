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
    public const VERSION = 22;

    /** Tables de données persistantes, hors table de migration. */
    public const TABLES = [
        'compteur_reference_identite',
        'identite_inscrite',
        'profil_compte_gamad',
        'fiche_annuaire_gamad',
        'publication_gamad',
        'favori_publication_gamad',
        'classement_besoin_gamad',
        'emetteur_besoin_gamad',
        'suivi_besoin_gamad',
        'acceptation_aide_gamad',
        'proposition_aide_gamad',
        'message_aide_gamad',
        'commentaire_gamad',
        'blocage_gamad',
        'relation_personnes_gamad',
        'message_reseau_gamad',
        'notification_reseau_gamad',
        'contexte_notification_aide_gamad',
        'signalement_reseau_gamad',
        'masquage_reseau_gamad',
        'zumra_gamad',
        'projet_gamad',
        'etape_projet_gamad',
        'entreprise_gamad',
        'document_entreprise_gamad',
        'revision_entreprise_gamad',
        'opportunite_gamad',
        'candidature_gamad',
        'adhesion_zumra_gamad',
        'responsabilite_zumra_gamad',
        'configuration_zumra_gamad',
        'participation_projet_gamad',
        'contact_reseau_gamad',
        'equipe_projet_gamad',
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

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS profil_compte_gamad (
                identite_reference TEXT PRIMARY KEY REFERENCES identite_inscrite(reference) ON DELETE CASCADE,
                bio VARCHAR(280) CHECK (length(bio) <= 280)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS fiche_annuaire_gamad (
                identite_reference TEXT PRIMARY KEY REFERENCES profil_compte_gamad(identite_reference) ON DELETE CASCADE,
                reference_publique TEXT NOT NULL UNIQUE,
                visibilite TEXT NOT NULL CHECK (visibilite IN ('prive', 'public', 'members')),
                competences_apportees TEXT NOT NULL,
                competences_recherchees TEXT NOT NULL
            )
        SQL);

        self::migrerPublications($pdo);
        $pdo->exec('CREATE INDEX IF NOT EXISTS publication_gamad_fil ON publication_gamad(cree_le, reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS favori_publication_gamad (
                membre_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                publication_reference TEXT NOT NULL REFERENCES publication_gamad(reference) ON DELETE CASCADE,
                cree_le TEXT NOT NULL,
                PRIMARY KEY (membre_reference, publication_reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS classement_besoin_gamad (
                besoin_reference TEXT PRIMARY KEY REFERENCES publication_gamad(reference) ON DELETE CASCADE,
                domaine TEXT,
                territoire TEXT,
                CHECK (domaine IS NOT NULL OR territoire IS NOT NULL)
            )
        SQL);


        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS proposition_aide_gamad (
                reference TEXT PRIMARY KEY,
                besoin_reference TEXT NOT NULL REFERENCES publication_gamad(reference) ON DELETE CASCADE,
                proposant_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                message VARCHAR(280) NOT NULL,
                etat TEXT NOT NULL CHECK (etat IN ('pending', 'withdrawn', 'accepted', 'declined')),
                UNIQUE (besoin_reference, proposant_reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS message_aide_gamad (
                reference TEXT PRIMARY KEY,
                proposition_reference TEXT NOT NULL REFERENCES proposition_aide_gamad(reference) ON DELETE CASCADE,
                auteur_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                texte VARCHAR(1000) NOT NULL CHECK (length(texte) BETWEEN 1 AND 1000),
                cree_le TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS message_aide_gamad_conversation ON message_aide_gamad(proposition_reference, cree_le, reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS suivi_besoin_gamad (
                besoin_reference TEXT PRIMARY KEY REFERENCES publication_gamad(reference) ON DELETE CASCADE,
                interne INTEGER NOT NULL DEFAULT 0 CHECK (interne IN (0, 1)),
                bilan VARCHAR(280)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS acceptation_aide_gamad (
                proposition_reference TEXT PRIMARY KEY REFERENCES proposition_aide_gamad(reference) ON DELETE CASCADE,
                acceptant_reference TEXT NOT NULL REFERENCES identite_inscrite(reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS commentaire_gamad (
                reference TEXT PRIMARY KEY,
                publication_reference TEXT NOT NULL REFERENCES publication_gamad(reference) ON DELETE CASCADE,
                auteur_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                contenu VARCHAR(500) NOT NULL CHECK (length(contenu) BETWEEN 1 AND 500),
                cree_le TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS commentaire_gamad_publication ON commentaire_gamad(publication_reference, cree_le, reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS blocage_gamad (
                auteur_reference TEXT NOT NULL REFERENCES identite_inscrite(reference) ON DELETE CASCADE,
                cible_reference TEXT NOT NULL REFERENCES identite_inscrite(reference) ON DELETE CASCADE,
                PRIMARY KEY (auteur_reference, cible_reference),
                CHECK (auteur_reference <> cible_reference)
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS blocage_gamad_cible ON blocage_gamad(cible_reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS relation_personnes_gamad (
                premiere_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                seconde_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                expediteur_reference TEXT NOT NULL,
                etat TEXT NOT NULL CHECK (etat IN ('pending', 'accepted', 'declined', 'withdrawn', 'removed')),
                note VARCHAR(280),
                revision INTEGER NOT NULL CHECK (revision > 0),
                modifie_le BIGINT NOT NULL,
                PRIMARY KEY (premiere_reference, seconde_reference),
                CHECK (premiere_reference < seconde_reference),
                CHECK (expediteur_reference IN (premiere_reference, seconde_reference))
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS relation_personnes_gamad_seconde ON relation_personnes_gamad(seconde_reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS message_reseau_gamad (
                reference TEXT PRIMARY KEY,
                premiere_reference TEXT NOT NULL,
                seconde_reference TEXT NOT NULL,
                auteur_reference TEXT NOT NULL,
                texte VARCHAR(1000) NOT NULL CHECK (length(texte) BETWEEN 1 AND 1000),
                cree_le TEXT NOT NULL,
                FOREIGN KEY (premiere_reference, seconde_reference)
                    REFERENCES relation_personnes_gamad(premiere_reference, seconde_reference) ON DELETE CASCADE,
                CHECK (auteur_reference IN (premiere_reference, seconde_reference))
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS message_reseau_gamad_paire ON message_reseau_gamad(premiere_reference, seconde_reference, cree_le, reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS notification_reseau_gamad (
                reference TEXT PRIMARY KEY,
                destinataire_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                auteur_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                type TEXT NOT NULL CHECK (type IN ('demande', 'acceptation', 'message')),
                cree_le TEXT NOT NULL,
                lue_le TEXT
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS notification_reseau_gamad_destinataire ON notification_reseau_gamad(destinataire_reference, cree_le, reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contexte_notification_aide_gamad (
                notification_reference TEXT PRIMARY KEY REFERENCES notification_reseau_gamad(reference) ON DELETE CASCADE,
                proposition_reference TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS signalement_reseau_gamad (
                reference TEXT PRIMARY KEY,
                categorie TEXT NOT NULL CHECK (categorie IN ('publication', 'commentaire')),
                cible_reference TEXT NOT NULL,
                auteur_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                motif TEXT NOT NULL CHECK (motif IN ('offensive', 'scam', 'misinformation', 'spam', 'privacy', 'other')),
                etat TEXT NOT NULL CHECK (etat IN ('ouvert', 'masque', 'classe')),
                cree_le TEXT NOT NULL,
                decision_par TEXT,
                decision_le TEXT,
                UNIQUE (categorie, cible_reference, auteur_reference)
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS signalement_reseau_gamad_etat ON signalement_reseau_gamad(etat, cree_le)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS masquage_reseau_gamad (
                categorie TEXT NOT NULL CHECK (categorie IN ('publication', 'commentaire')),
                cible_reference TEXT NOT NULL,
                PRIMARY KEY (categorie, cible_reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS zumra_gamad (
                reference TEXT PRIMARY KEY,
                fondateur_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                nom VARCHAR(100) NOT NULL,
                presentation VARCHAR(500) NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS projet_gamad (
                reference TEXT PRIMARY KEY,
                porteur_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                zumra_reference TEXT NOT NULL REFERENCES zumra_gamad(reference),
                titre VARCHAR(120) NOT NULL,
                dossier TEXT NOT NULL,
                accueil TEXT NOT NULL CHECK (accueil IN ('en_attente', 'accepte', 'refuse')),
                vitrine VARCHAR(500)
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS projet_gamad_zumra ON projet_gamad(zumra_reference, accueil)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS emetteur_besoin_gamad (
                besoin_reference TEXT PRIMARY KEY REFERENCES publication_gamad(reference) ON DELETE CASCADE,
                zumra_reference TEXT REFERENCES zumra_gamad(reference),
                projet_reference TEXT REFERENCES projet_gamad(reference),
                CHECK ((zumra_reference IS NULL) <> (projet_reference IS NULL))
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS entreprise_gamad (
                reference TEXT PRIMARY KEY,
                proprietaire_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                nom VARCHAR(160) NOT NULL,
                pays VARCHAR(2) NOT NULL,
                immatriculation VARCHAR(100) NOT NULL,
                secteur VARCHAR(120) NOT NULL,
                justificatif TEXT NOT NULL,
                etat TEXT NOT NULL CHECK (etat IN ('pending', 'verified', 'declined', 'suspended')),
                decision_par TEXT,
                decision_le TEXT,
                UNIQUE (pays, immatriculation)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS opportunite_gamad (
                reference TEXT PRIMARY KEY,
                entreprise_reference TEXT NOT NULL REFERENCES entreprise_gamad(reference),
                titre VARCHAR(160) NOT NULL,
                description VARCHAR(1800) NOT NULL,
                etat TEXT NOT NULL CHECK (etat IN ('published', 'closed')),
                cree_le TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS opportunite_gamad_entreprise ON opportunite_gamad(entreprise_reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS candidature_gamad (
                opportunite_reference TEXT NOT NULL REFERENCES opportunite_gamad(reference),
                candidat_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                message VARCHAR(1000) NOT NULL,
                cree_le TEXT NOT NULL,
                PRIMARY KEY (opportunite_reference, candidat_reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS adhesion_zumra_gamad (
                espace_reference TEXT NOT NULL REFERENCES zumra_gamad(reference) ON DELETE CASCADE,
                membre_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                etat TEXT NOT NULL CHECK (etat IN ('pending', 'accepted')),
                PRIMARY KEY (espace_reference, membre_reference)
            )
        SQL);
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS equipe_projet_gamad (
                espace_reference TEXT NOT NULL REFERENCES projet_gamad(reference) ON DELETE CASCADE,
                membre_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                etat TEXT NOT NULL CHECK (etat IN ('pending', 'accepted')),
                PRIMARY KEY (espace_reference, membre_reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS etape_projet_gamad (
                reference TEXT PRIMARY KEY,
                projet_reference TEXT NOT NULL REFERENCES projet_gamad(reference) ON DELETE CASCADE,
                titre VARCHAR(80) NOT NULL,
                etat TEXT NOT NULL CHECK (etat IN ('todo', 'in_progress', 'done')),
                position INTEGER NOT NULL CHECK (position >= 0),
                UNIQUE (projet_reference, position)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS responsabilite_zumra_gamad (
                espace_reference TEXT NOT NULL,
                membre_reference TEXT NOT NULL,
                PRIMARY KEY (espace_reference, membre_reference),
                FOREIGN KEY (espace_reference, membre_reference)
                    REFERENCES adhesion_zumra_gamad(espace_reference, membre_reference) ON DELETE CASCADE
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS configuration_zumra_gamad (
                zumra_reference TEXT PRIMARY KEY REFERENCES zumra_gamad(reference) ON DELETE CASCADE,
                mode_adhesion TEXT NOT NULL CHECK (mode_adhesion IN ('open', 'approval'))
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS participation_projet_gamad (
                projet_reference TEXT NOT NULL REFERENCES projet_gamad(reference) ON DELETE CASCADE,
                candidat_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                message VARCHAR(280),
                etat TEXT NOT NULL CHECK (etat IN ('pending', 'accepted', 'declined', 'withdrawn')),
                PRIMARY KEY (projet_reference, candidat_reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS document_entreprise_gamad (
                entreprise_reference TEXT NOT NULL REFERENCES entreprise_gamad(reference) ON DELETE CASCADE,
                type_document TEXT NOT NULL CHECK (type_document IN ('dfe', 'idu')),
                contenu TEXT NOT NULL,
                PRIMARY KEY (entreprise_reference, type_document)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS revision_entreprise_gamad (
                entreprise_reference TEXT PRIMARY KEY REFERENCES entreprise_gamad(reference) ON DELETE CASCADE,
                revision INTEGER NOT NULL CHECK (revision > 0)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contact_reseau_gamad (
                reference TEXT PRIMARY KEY,
                nom VARCHAR(80) NOT NULL,
                email VARCHAR(254) NOT NULL,
                sujet TEXT NOT NULL CHECK (sujet IN ('general', 'account', 'problem', 'partnership', 'other')),
                message VARCHAR(2000) NOT NULL,
                etat TEXT NOT NULL CHECK (etat IN ('new', 'read', 'handled')),
                cree_le TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS contact_reseau_gamad_date ON contact_reseau_gamad(cree_le)');

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

    private static function migrerPublications(\PDO $pdo): void
    {
        if ($pdo->query('SELECT 1 FROM migration_registre_identites WHERE version >= 15')->fetchColumn() !== false) {
            return;
        }

        $definition = <<<'SQL'
            reference TEXT PRIMARY KEY,
            auteur_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
            type TEXT NOT NULL,
            visibilite TEXT NOT NULL CHECK (visibilite IN ('public', 'members')),
            contenu VARCHAR(500) NOT NULL CHECK (length(contenu) BETWEEN 1 AND 500),
            cree_le TEXT NOT NULL,
            etat_besoin TEXT,
            CONSTRAINT publication_gamad_type_check CHECK (type IN ('share', 'knowledge', 'personal_work', 'need')),
            CONSTRAINT publication_gamad_etat_besoin_check CHECK (
                (type = 'need' AND etat_besoin IS NOT NULL AND etat_besoin IN ('open', 'in_progress', 'resolved', 'closed'))
                OR (type <> 'need' AND etat_besoin IS NULL)
            )
        SQL;

        if (self::driver($pdo) === 'pgsql') {
            $pdo->beginTransaction();
            try {
                $pdo->query('SELECT pg_advisory_xact_lock(720113315)');
                $pdo->exec("CREATE TABLE IF NOT EXISTS publication_gamad ({$definition})");
                $pdo->exec('ALTER TABLE publication_gamad ADD COLUMN IF NOT EXISTS etat_besoin TEXT');
                $pdo->exec('ALTER TABLE publication_gamad DROP CONSTRAINT IF EXISTS publication_gamad_type_check,
                    DROP CONSTRAINT IF EXISTS publication_gamad_etat_besoin_check');
                $pdo->exec("ALTER TABLE publication_gamad
                    ADD CONSTRAINT publication_gamad_type_check CHECK (type IN ('share', 'knowledge', 'personal_work', 'need')),
                    ADD CONSTRAINT publication_gamad_etat_besoin_check CHECK (
                        (type = 'need' AND etat_besoin IS NOT NULL AND etat_besoin IN ('open', 'in_progress', 'resolved', 'closed'))
                        OR (type <> 'need' AND etat_besoin IS NULL)
                    )");
                $pdo->commit();
            } catch (\Throwable $erreur) {
                $pdo->rollBack();
                throw $erreur;
            }

            return;
        }

        $cles = (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            $pdo->exec('BEGIN IMMEDIATE');
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS publication_gamad ({$definition})");
                $colonnes = array_column($pdo->query('PRAGMA table_info(publication_gamad)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
                if (!in_array('etat_besoin', $colonnes, true)) {
                    $pdo->exec("CREATE TABLE publication_gamad_v15 ({$definition})");
                    $pdo->exec('INSERT INTO publication_gamad_v15 (reference, auteur_reference, type, visibilite, contenu, cree_le)
                        SELECT reference, auteur_reference, type, visibilite, contenu, cree_le FROM publication_gamad');
                    $pdo->exec('DROP TABLE publication_gamad');
                    $pdo->exec('ALTER TABLE publication_gamad_v15 RENAME TO publication_gamad');
                }
                $pdo->exec('COMMIT');
            } catch (\Throwable $erreur) {
                $pdo->exec('ROLLBACK');
                throw $erreur;
            }
        } finally {
            if ($cles === 1) {
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
        }
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
