<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Schéma additif des défis de preuve de possession d'un identifiant humain.
 *
 * Le code de vérification n'est jamais persisté : seule une empreinte issue de
 * password_hash() est conservée. Le défi est court, borné en tentatives et à
 * usage unique.
 */
final class SchemaVerificationIdentifiants
{
    public const VERSION = 1;

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_verification_identifiants (
                version INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS verification_identifiant (
                id                    {$id},
                reference             TEXT NOT NULL UNIQUE,
                identifiant_reference TEXT NOT NULL,
                secret_empreinte      TEXT NOT NULL,
                etat                  TEXT NOT NULL CHECK (etat IN ('EN_ATTENTE','CONSOMMEE','EXPIREE')),
                tentatives            INTEGER NOT NULL DEFAULT 0,
                expire_le             TEXT NOT NULL,
                source                TEXT NOT NULL,
                preuve_reference      TEXT NOT NULL,
                producteur            TEXT NOT NULL,
                cree_le               TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_verification_identifiant_active
             ON verification_identifiant(identifiant_reference, etat, expire_le)'
        );

        $st = $pdo->prepare(
            'INSERT INTO migration_verification_identifiants(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_verification_identifiants WHERE version = ?
             )'
        );
        $st->execute([self::VERSION, gmdate('c'), self::VERSION]);
    }

    private static function driver(\PDO $pdo): string
    {
        return (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
