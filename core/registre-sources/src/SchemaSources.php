<?php

declare(strict_types=1);

namespace Gamad\RegistreSources;

/**
 * Migration additive du registre persistant de CAP-CORE-006.
 *
 * Ces tables ne font jamais partie de Gamad\RegistreNormes\Schema et ne sont
 * donc jamais supprimées par une réindexation documentaire. Le cycle, les
 * révisions, les vérifications et la lignée sont des journaux en ajout seul ;
 * seule la fermeture d'une finalité modifie sa propre ligne (`actif`,
 * `date_fin`), jamais son passé.
 */
final class SchemaSources
{
    public const VERSION = 1;

    /** Tables de données persistantes, hors table de migration. */
    public const TABLES = [
        'source',
        'source_cycle',
        'source_revision',
        'source_verification',
        'source_finalite',
        'source_lignee',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_registre_sources (
                version      INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        // Identité stable et immuable de la source. Les métadonnées
        // descriptives (nom d'affichage, catégorie, description, propriétaire,
        // produit producteur, réserve) vivent dans `source_revision` : la
        // référence, le nom canonique, le type et la valeur historique
        // d'authenticité ne changent jamais après inscription.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS source (
                reference             TEXT PRIMARY KEY,
                nom_canonique         TEXT NOT NULL,
                type_source           TEXT NOT NULL
                    CHECK (type_source IN
                        ('PRODUIT_GAMAD','SERVICE_CORE','ORGANISATION','INSTITUTION',
                         'PARTENAIRE','SYSTEME_EXTERNE','IMPORT_GOUVERNE','CANAL_DECLARATIF')),
                authenticite_legacy   TEXT,
                politique_inscription TEXT NOT NULL,
                producteur            TEXT NOT NULL,
                preuve_reference      TEXT NOT NULL,
                cree_le               TEXT NOT NULL,
                modifie_le            TEXT NOT NULL
            )
        SQL);

        // Cycle en ajout seul. L'état courant est toujours la dernière ligne
        // par date_effet ; le passé n'est jamais réécrit.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS source_cycle (
                id                {$id},
                source_reference  TEXT NOT NULL,
                etat              TEXT NOT NULL
                    CHECK (etat IN ('PREPARATION','ACTIVE','SUSPENDUE','RETIREE')),
                date_effet        TEXT NOT NULL,
                motif             TEXT,
                acteur_reference  TEXT NOT NULL,
                politique_reference TEXT NOT NULL,
                preuve_reference  TEXT NOT NULL,
                correlation_id    TEXT,
                cree_le           TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS source_cycle_source
             ON source_cycle(source_reference, date_effet, id)'
        );

        // Révisions en ajout seul : une correction produit toujours une
        // nouvelle ligne, jamais une réécriture de la précédente.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS source_revision (
                id                          {$id},
                source_reference            TEXT NOT NULL,
                numero_revision             INTEGER NOT NULL,
                nom_affichage               TEXT NOT NULL,
                categorie                   TEXT,
                description                 TEXT,
                proprietaire_reference      TEXT NOT NULL,
                produit_producteur_reference TEXT,
                reserve                     TEXT,
                date_effet                  TEXT NOT NULL,
                acteur_reference            TEXT NOT NULL,
                preuve_reference            TEXT NOT NULL,
                correlation_id              TEXT,
                cree_le                     TEXT NOT NULL,
                UNIQUE(source_reference, numero_revision)
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS source_revision_source
             ON source_revision(source_reference, date_effet, id)'
        );

        // Vérifications en ajout seul : une nouvelle vérification n'efface
        // jamais les précédentes. La vérification courante est la plus
        // récente par date_effet.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS source_verification (
                id                     {$id},
                source_reference       TEXT NOT NULL,
                niveau                 TEXT NOT NULL
                    CHECK (niveau IN ('NON_VERIFIEE','DECLAREE','CONTROLEE','ATTESTEE')),
                resultat               TEXT NOT NULL
                    CHECK (resultat IN ('VALIDE','INVALIDE','EXPIREE')),
                verifie_par_reference  TEXT NOT NULL,
                preuve_reference       TEXT NOT NULL,
                verifie_le             TEXT NOT NULL,
                expire_le              TEXT,
                motif                  TEXT,
                correlation_id         TEXT,
                cree_le                TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS source_verification_source
             ON source_verification(source_reference, verifie_le, id)'
        );

        // `actif`/`date_fin` sont les seules colonnes mutables : fermer une
        // finalité ne supprime rien et ne touche aucune autre ligne.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS source_finalite (
                id                           {$id},
                source_reference             TEXT NOT NULL,
                finalite_reference           TEXT NOT NULL,
                produit_consommateur_reference TEXT,
                date_debut                   TEXT NOT NULL,
                date_fin                     TEXT,
                restriction                  TEXT,
                actif                        INTEGER NOT NULL DEFAULT 1 CHECK (actif IN (0,1)),
                acteur_reference             TEXT NOT NULL,
                politique_reference          TEXT NOT NULL,
                preuve_reference             TEXT NOT NULL,
                correlation_id               TEXT,
                cree_le                      TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS source_finalite_source
             ON source_finalite(source_reference, finalite_reference, produit_consommateur_reference)'
        );

        // Lignée en ajout seul : l'ajout d'une relation ne supprime jamais une
        // relation antérieure.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS source_lignee (
                id                        {$id},
                source_reference          TEXT NOT NULL,
                source_parente_reference  TEXT NOT NULL,
                type_relation             TEXT NOT NULL
                    CHECK (type_relation IN ('DERIVEE_DE','AGREGE','REMPLACE','CORRIGE')),
                date_effet                TEXT NOT NULL,
                acteur_reference          TEXT NOT NULL,
                preuve_reference          TEXT NOT NULL,
                correlation_id            TEXT,
                cree_le                   TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS source_lignee_source
             ON source_lignee(source_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS source_lignee_parente
             ON source_lignee(source_parente_reference)'
        );

        $st = $pdo->prepare(
            'INSERT INTO migration_registre_sources(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_registre_sources WHERE version = ?
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
