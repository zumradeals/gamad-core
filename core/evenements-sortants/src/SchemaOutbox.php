<?php

declare(strict_types=1);

namespace Gamad\EvenementsSortants;

/**
 * Migration additive de l'outbox transactionnelle d'un magasin producteur
 * (CAP-CORE-014, partie 2 §5).
 *
 * Cette table vit dans le MÊME magasin PDO que l'état métier du producteur —
 * jamais dans le journal central — pour que son insertion partage la
 * transaction métier qui la produit. Plusieurs producteurs peuvent migrer
 * cette même table dans leur propre magasin sans collision : chaque magasin
 * producteur est physiquement distinct.
 */
final class SchemaOutbox
{
    public const VERSION = 1;
    public const TABLE = 'evenement_sortant';

    public static function migrer(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS migration_evenement_sortant (
                version INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )'
        );
        $deja = $pdo->query('SELECT version FROM migration_evenement_sortant WHERE version = 1')->fetchColumn();
        if ($deja !== false) {
            return;
        }

        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS evenement_sortant (
                id                            {$id},
                idempotence_reference          TEXT NOT NULL UNIQUE,
                type_evenement                 TEXT NOT NULL,
                contrat_reference               TEXT NOT NULL,
                contrat_version                 TEXT NOT NULL,
                producteur_capacite_reference    TEXT,
                producteur_produit_reference     TEXT,
                source_reference                 TEXT NOT NULL,
                realm_reference                  TEXT NOT NULL,
                finalite_reference                TEXT NOT NULL,
                sujet_type                        TEXT,
                sujet_reference                   TEXT,
                correlation_id                    TEXT NOT NULL,
                causation_reference               TEXT,
                survenu_le                        TEXT NOT NULL,
                classification                     TEXT NOT NULL,
                charge_json                        TEXT NOT NULL,
                schema_empreinte                   TEXT,
                charge_empreinte                   TEXT NOT NULL,
                etat                                TEXT NOT NULL CHECK (etat IN
                    ('EN_ATTENTE','EN_COURS','PUBLIE','ECHEC_TEMPORAIRE','ECHEC_DEFINITIF')),
                tentatives                          INTEGER NOT NULL DEFAULT 0,
                prochaine_tentative_le              TEXT,
                derniere_erreur_code                TEXT,
                evenement_reference                 TEXT,
                cree_le                             TEXT NOT NULL,
                publie_le                           TEXT
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS evenement_sortant_etat ON evenement_sortant(etat, prochaine_tentative_le)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS evenement_sortant_cree_le ON evenement_sortant(cree_le)');

        $st = $pdo->prepare('INSERT INTO migration_evenement_sortant(version, appliquee_le) VALUES(?, ?)');
        $st->execute([self::VERSION, gmdate('c')]);
    }

    public static function presente(\PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM evenement_sortant LIMIT 1');

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    private static function driver(\PDO $pdo): string
    {
        return (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
