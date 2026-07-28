<?php

declare(strict_types=1);

namespace Gamad\RegistreAcces;

/**
 * Magasin d'exploitation — troisième espace d'écriture du Core (INV-22).
 *
 * Le Core comporte trois espaces que rien ne doit confondre :
 *
 *   · le CORPUS, écrit par les seuls actes signés (INV-4) ;
 *   · l'INDEX DÉRIVÉ, écrit par l'ingestion et reconstruit à volonté (INV-5) ;
 *   · le MAGASIN D'EXPLOITATION, écrit par l'application.
 *
 * Aucun fait du corpus ne réside ici, et rien de ce qui s'y trouve ne fait foi.
 * Sa perte ne détruit aucune vérité : elle oblige seulement à rétablir des
 * moyens d'accès. C'est cette propriété qui permet d'y écrire sans entamer
 * INV-4.
 *
 * Ce magasin n'est JAMAIS touché par Schema::create(), qui détruit et
 * reconstruit l'index à chaque ingestion. Un identifiant qui résiderait dans
 * l'index serait effacé à la première réindexation.
 */
final class Magasin
{
    public static function creer(\PDO $pdo): void
    {
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        // Aucune colonne de secret : seulement une empreinte non réversible
        // (INV-24). Aucune colonne de profil, de dossier ni de jugement :
        // l'exclusion d'INV-19 vaut ici comme au registre des identités.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS authentificateur (
                reference        TEXT PRIMARY KEY,
                entite_reference TEXT NOT NULL,
                type             TEXT NOT NULL,
                empreinte        TEXT NOT NULL,
                niveau_assurance TEXT NOT NULL,
                etat             TEXT NOT NULL,
                cree_le          TEXT NOT NULL,
                revoque_le       TEXT
            )
        SQL);

        // `expire_le` n'est jamais nul : aucune session ne devient permanente
        // par commodité (INV-25, SECURITY-GOVERNANCE-0001, Art. 90).
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS session_ouverte (
                id                   {$id},
                reference            TEXT NOT NULL UNIQUE,
                authentificateur_ref TEXT NOT NULL REFERENCES authentificateur(reference),
                entite_reference     TEXT NOT NULL,
                niveau_assurance     TEXT NOT NULL,
                ouverte_le           TEXT NOT NULL,
                expire_le            TEXT NOT NULL,
                revoquee_le          TEXT
            )
        SQL);
    }

    /**
     * Le magasin ne partage pas la connexion de l'index dérivé : il vit dans
     * son propre fichier ou son propre schéma, afin qu'aucune reconstruction
     * de l'index ne puisse l'atteindre.
     */
    public static function connecter(?string $chemin = null): \PDO
    {
        $url = getenv('MAGASIN_URL');
        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host'])) {
                throw new \RuntimeException('MAGASIN_URL invalide.');
            }
            $pdo = new \PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $p['host'], $p['port'] ?? 5432, ltrim($p['path'] ?? '', '/')),
                $p['user'] ?? null,
                isset($p['pass']) ? urldecode($p['pass']) : null,
            );
        } else {
            $chemin ??= getenv('MAGASIN_PATH') ?: dirname(__DIR__) . '/var/magasin.sqlite';
            @mkdir(dirname($chemin), 0777, true);
            $pdo = new \PDO('sqlite:' . $chemin);
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        self::creer($pdo);

        return $pdo;
    }
}
