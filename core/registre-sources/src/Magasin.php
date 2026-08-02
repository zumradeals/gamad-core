<?php

declare(strict_types=1);

namespace Gamad\RegistreSources;

/**
 * Connexion au registre persistant des sources (CAP-CORE-006), physiquement
 * distinct de l'index reconstructible et de tout autre magasin.
 *
 * Variables dédiées :
 *   SOURCE_REGISTRY_URL  PostgreSQL en exploitation ;
 *   SOURCE_REGISTRY_PATH SQLite en local et en CI.
 *
 * DATABASE_URL, SQLITE_PATH et les autres variables de magasin appartiennent
 * à d'autres registres et ne sont volontairement jamais consultées ici.
 */
final class Magasin
{
    public static function connecter(?string $chemin = null): \PDO
    {
        $pdo = self::ouvrir($chemin);
        SchemaSources::migrer($pdo);

        return $pdo;
    }

    /**
     * Ouvre le registre sans appliquer de migration, pour les diagnostics.
     */
    public static function ouvrir(?string $chemin = null): \PDO
    {
        // Un chemin explicite impose le magasin isolé, même lorsque le
        // processus parent Laravel expose une URL de production.
        $url = $chemin === null ? self::environnement('SOURCE_REGISTRY_URL') : null;
        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host'])) {
                throw new \RuntimeException('SOURCE_REGISTRY_URL invalide.');
            }
            $pdo = new \PDO(
                sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    $p['host'],
                    $p['port'] ?? 5432,
                    ltrim($p['path'] ?? '', '/'),
                ),
                $p['user'] ?? null,
                isset($p['pass']) ? urldecode($p['pass']) : null,
            );
        } else {
            $chemin ??= self::environnement('SOURCE_REGISTRY_PATH')
                ?: dirname(__DIR__) . '/var/registre.sqlite';
            @mkdir(dirname($chemin), 0777, true);
            $pdo = new \PDO('sqlite:' . $chemin);
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $pdo;
    }

    /**
     * Comprend aussi les variables chargées par Laravel via phpdotenv, y
     * compris lorsque la configuration est mise en cache et que `.env` n'est
     * plus relu.
     */
    private static function environnement(string $nom): ?string
    {
        $configuration = match ($nom) {
            'SOURCE_REGISTRY_URL' => 'database.connections.gamad_sources.url',
            'SOURCE_REGISTRY_PATH' => 'database.connections.gamad_sources.database',
            default => null,
        };
        $valeurLaravel = null;
        if ($configuration !== null
            && function_exists('app')
            && app()->bound('config')) {
            $valeurLaravel = config($configuration);
        }

        foreach ([getenv($nom), $valeurLaravel, $_ENV[$nom] ?? null, $_SERVER[$nom] ?? null] as $valeur) {
            if (is_string($valeur)) {
                return $valeur;
            }
        }

        return null;
    }
}
