<?php

declare(strict_types=1);

namespace Gamad\JournalOperationnel;

/**
 * Schéma append-only des événements produits par l'exploitation.
 *
 * Ce journal n'est ni le corpus canonique ni son index. Il conserve les faits
 * d'exécution nécessaires à l'audit, avec une chaîne d'empreintes vérifiable.
 */
final class Schema
{
    public const VERSION = 1;

    public static function migrer(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS migration_journal_operationnel (
                version INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )'
        );

        $version = $pdo->query(
            'SELECT version FROM migration_journal_operationnel WHERE version = 1'
        )->fetchColumn();
        if ($version !== false) {
            return;
        }

        $propreTransaction = !$pdo->inTransaction();
        if ($propreTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $id = self::driver($pdo) === 'pgsql'
                ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
                : 'INTEGER PRIMARY KEY AUTOINCREMENT';

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS evenement_operationnel (
                    sequence_id     {$id},
                    reference       TEXT NOT NULL UNIQUE,
                    categorie       TEXT NOT NULL,
                    type_evenement  TEXT NOT NULL,
                    acteur          TEXT,
                    action          TEXT,
                    ressource       TEXT,
                    decision        TEXT,
                    motif           TEXT,
                    correlation_id  TEXT NOT NULL,
                    donnees         TEXT NOT NULL,
                    empreinte_precedente TEXT,
                    empreinte       TEXT NOT NULL UNIQUE,
                    cree_le         TEXT NOT NULL
                )
            SQL);
            $pdo->exec(
                'CREATE INDEX IF NOT EXISTS evenement_operationnel_cree_le
                 ON evenement_operationnel(cree_le)'
            );
            $pdo->exec(
                'CREATE INDEX IF NOT EXISTS evenement_operationnel_correlation
                 ON evenement_operationnel(correlation_id)'
            );

            self::verrouillerMutations($pdo);

            $st = $pdo->prepare(
                'INSERT INTO migration_journal_operationnel(version, appliquee_le) VALUES(?, ?)'
            );
            $st->execute([self::VERSION, gmdate('c')]);

            if ($propreTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($propreTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function verrouillerMutations(\PDO $pdo): void
    {
        if (self::driver($pdo) === 'pgsql') {
            $pdo->exec(<<<SQL
                CREATE OR REPLACE FUNCTION gamad_refuser_mutation_journal()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'evenement_operationnel est en ajout seul';
                END;
                $$ LANGUAGE plpgsql
            SQL);
            $pdo->exec(
                'DROP TRIGGER IF EXISTS evenement_operationnel_immuable
                 ON evenement_operationnel'
            );
            $pdo->exec(<<<SQL
                CREATE TRIGGER evenement_operationnel_immuable
                BEFORE UPDATE OR DELETE ON evenement_operationnel
                FOR EACH ROW EXECUTE FUNCTION gamad_refuser_mutation_journal()
            SQL);

            return;
        }

        $pdo->exec(<<<SQL
            CREATE TRIGGER IF NOT EXISTS evenement_operationnel_refuser_update
            BEFORE UPDATE ON evenement_operationnel
            BEGIN
                SELECT RAISE(ABORT, 'evenement_operationnel est en ajout seul');
            END
        SQL);
        $pdo->exec(<<<SQL
            CREATE TRIGGER IF NOT EXISTS evenement_operationnel_refuser_delete
            BEFORE DELETE ON evenement_operationnel
            BEGIN
                SELECT RAISE(ABORT, 'evenement_operationnel est en ajout seul');
            END
        SQL);
    }

    private static function driver(\PDO $pdo): string
    {
        return (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
