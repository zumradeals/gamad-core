<?php

declare(strict_types=1);

namespace Gamad\RegistreFederation;

/**
 * Schéma des jetons fédérés, hébergé dans le magasin d'exploitation de
 * CAP-CORE-005.
 *
 * Le choix est délibéré : la validité d'un jeton fédéré dépend en permanence
 * de la session Core qui l'a produit. Les tenir dans la même base permet de
 * joindre `session_ouverte` sans base de données distribuée, donc de fermer
 * réellement les jetons lors d'une déconnexion globale ou d'une révocation
 * d'authentificateur.
 *
 * Aucun jeton n'est conservé en clair : seule son empreinte SHA-256 est
 * persistée, comme pour les sessions Core.
 */
final class SchemaFederation
{
    public const VERSION = 1;

    public const TABLES = ['jeton_federe'];

    public static function migrer(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_registre_federation (
                version      INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        // `produit_reference` est l'audience unique du jeton : un jeton émis
        // pour un satellite n'est jamais présentable à un autre.
        //
        // `session_empreinte` reprend `session_ouverte.jeton_empreinte`. C'est
        // la seule jointure qui rend la déconnexion globale effective, et elle
        // n'expose aucune valeur de session.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS jeton_federe (
                reference          TEXT PRIMARY KEY,
                jeton_empreinte    TEXT NOT NULL UNIQUE,
                produit_reference  TEXT NOT NULL,
                identite_reference TEXT NOT NULL,
                relation_reference TEXT NOT NULL,
                relation_type      TEXT NOT NULL,
                portees            TEXT NOT NULL,
                niveau_assurance   TEXT NOT NULL,
                session_empreinte  TEXT NOT NULL,
                correlation_id     TEXT,
                preuve_reference   TEXT NOT NULL,
                emis_le            TEXT NOT NULL,
                expire_le          TEXT NOT NULL,
                consomme_le        TEXT,
                revoque_le         TEXT,
                motif_revocation   TEXT
            )
        SQL);

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS jeton_federe_session
             ON jeton_federe(session_empreinte)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS jeton_federe_acces
             ON jeton_federe(identite_reference,produit_reference)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS jeton_federe_relation
             ON jeton_federe(relation_reference)'
        );

        $st = $pdo->prepare(
            'INSERT INTO migration_registre_federation(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_registre_federation WHERE version = ?
             )'
        );
        $st->execute([self::VERSION, gmdate('c'), self::VERSION]);
    }

    public static function presente(\PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT reference FROM jeton_federe LIMIT 0');

            return true;
        } catch (\PDOException) {
            return false;
        }
    }
}
