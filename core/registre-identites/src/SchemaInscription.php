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
    public const VERSION = 9;

    /** Tables de données persistantes, hors table de migration. */
    public const TABLES = [
        'compteur_reference_identite',
        'identite_inscrite',
        'profil_compte_gamad',
        'fiche_annuaire_gamad',
        'publication_gamad',
        'commentaire_gamad',
        'blocage_gamad',
        'relation_personnes_gamad',
        'message_reseau_gamad',
        'notification_reseau_gamad',
        'signalement_reseau_gamad',
        'masquage_reseau_gamad',
        'zumra_gamad',
        'projet_gamad',
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

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS publication_gamad (
                reference TEXT PRIMARY KEY,
                auteur_reference TEXT NOT NULL REFERENCES identite_inscrite(reference),
                type TEXT NOT NULL CHECK (type IN ('share', 'knowledge', 'personal_work')),
                visibilite TEXT NOT NULL CHECK (visibilite IN ('public', 'members')),
                contenu VARCHAR(500) NOT NULL CHECK (length(contenu) BETWEEN 1 AND 500),
                cree_le TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS publication_gamad_fil ON publication_gamad(cree_le, reference)');

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
