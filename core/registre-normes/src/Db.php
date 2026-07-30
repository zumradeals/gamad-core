<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

/**
 * Connexion PDO à l'index dérivé.
 *
 * Deux cibles, un seul code : PostgreSQL sur le serveur d'exploitation
 * (variable DATABASE_URL), SQLite en local et en intégration continue.
 * L'index n'est jamais la source de vérité (INV-5) : ce sont les fichiers Git.
 * La base est reconstructible à volonté.
 */
final class Db
{
    public static function connect(): \PDO
    {
        $url = self::environnement('DATABASE_URL');

        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host'])) {
                throw new \RuntimeException('DATABASE_URL invalide.');
            }
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $p['host'],
                $p['port'] ?? 5432,
                ltrim($p['path'] ?? '', '/')
            );
            $pdo = new \PDO(
                $dsn,
                $p['user'] ?? null,
                isset($p['pass']) ? urldecode($p['pass']) : null
            );
        } else {
            $chemin = self::environnement('SQLITE_PATH');
            if (!is_string($chemin) || $chemin === '') {
                $chemin = dirname(__DIR__) . '/var/index.sqlite';
            }
            @mkdir(dirname($chemin), 0777, true);
            $pdo = new \PDO('sqlite:' . $chemin);
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $pdo;
    }

    public static function driver(\PDO $pdo): string
    {
        return (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Laravel charge .env dans $_ENV/$_SERVER sans nécessairement alimenter
     * getenv(). Les gardes autonomes utilisent aussi de vraies variables de
     * processus : les trois sources doivent donc être comprises.
     */
    private static function environnement(string $nom): ?string
    {
        // Une variable de processus explicite sert notamment à isoler les
        // gardes lancées depuis Artisan. À défaut seulement, lire phpdotenv.
        foreach ([getenv($nom), $_ENV[$nom] ?? null, $_SERVER[$nom] ?? null] as $valeur) {
            if (is_string($valeur)) {
                return $valeur;
            }
        }

        return null;
    }
}
