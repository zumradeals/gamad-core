<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

/**
 * Connexion PDO à l'index dérivé.
 *
 * Deux cibles, un seul code : PostgreSQL en déploiement (variable
 * DATABASE_URL fournie par Railway), SQLite en local et en intégration
 * continue. L'index n'est jamais la source de vérité (INV-5) : ce sont les
 * fichiers Git. La base est reconstructible à volonté.
 */
final class Db
{
    public static function connect(): \PDO
    {
        $url = getenv('DATABASE_URL');

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
            $chemin = getenv('SQLITE_PATH');
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
}
