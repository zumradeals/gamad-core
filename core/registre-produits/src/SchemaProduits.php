<?php

declare(strict_types=1);

namespace Gamad\RegistreProduits;

/**
 * Migration additive du registre persistant de CAP-CORE-011.
 *
 * Ces tables ne font jamais partie de Gamad\RegistreNormes\Schema et ne sont
 * donc jamais supprimées par une réindexation documentaire. Le cycle de vie et
 * les environnements sont des journaux en ajout seul ; seule la fermeture d'un
 * environnement modifie sa propre ligne (`actif`, `date_fin`), jamais son
 * passé.
 */
final class SchemaProduits
{
    public const VERSION = 1;

    /** Tables de données persistantes, hors table de migration. */
    public const TABLES = [
        'produit',
        'produit_cycle',
        'produit_environnement',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_registre_produits (
                version      INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        // La référence n'est jamais réattribuée et n'est jamais fournie par
        // une séquence : elle est choisie par l'appelant gouverné (INV-17
        // s'applique par convention, pas par contrainte technique ici).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS produit (
                reference             TEXT PRIMARY KEY,
                identite_reference    TEXT NOT NULL UNIQUE,
                nom_canonique         TEXT NOT NULL,
                nom_affichage         TEXT NOT NULL,
                type_produit          TEXT NOT NULL
                    CHECK (type_produit IN
                        ('PORTAIL','SATELLITE','SERVICE_CORE','PARTENAIRE','APPLICATION_INTERNE')),
                proprietaire_reference TEXT NOT NULL,
                source_reference      TEXT NOT NULL,
                federation_autorisee  INTEGER NOT NULL DEFAULT 0 CHECK (federation_autorisee IN (0,1)),
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
            CREATE TABLE IF NOT EXISTS produit_cycle (
                id                {$id},
                produit_reference TEXT NOT NULL,
                etat              TEXT NOT NULL
                    CHECK (etat IN ('PREPARATION','ACTIF','SUSPENDU','RETIRE')),
                date_effet        TEXT NOT NULL,
                motif             TEXT,
                acteur_reference  TEXT NOT NULL,
                preuve_reference  TEXT NOT NULL,
                correlation_id    TEXT,
                cree_le           TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS produit_cycle_produit
             ON produit_cycle(produit_reference, date_effet, id)'
        );

        // `actif`/`date_fin` sont les seules colonnes mutables : fermer un
        // environnement ne supprime rien et ne touche aucune autre ligne.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS produit_environnement (
                id                   {$id},
                produit_reference    TEXT NOT NULL,
                environnement        TEXT NOT NULL
                    CHECK (environnement IN ('DEVELOPPEMENT','RECETTE','PRODUCTION')),
                api_base_url         TEXT NOT NULL,
                health_url           TEXT,
                audience_federation  TEXT NOT NULL,
                actif                INTEGER NOT NULL DEFAULT 1 CHECK (actif IN (0,1)),
                date_debut           TEXT NOT NULL,
                date_fin             TEXT,
                source_reference     TEXT NOT NULL,
                producteur           TEXT NOT NULL,
                preuve_reference     TEXT NOT NULL,
                cree_le              TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS produit_environnement_produit
             ON produit_environnement(produit_reference, environnement)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS produit_environnement_audience
             ON produit_environnement(audience_federation)'
        );

        $st = $pdo->prepare(
            'INSERT INTO migration_registre_produits(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_registre_produits WHERE version = ?
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
