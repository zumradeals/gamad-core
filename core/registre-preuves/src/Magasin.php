<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

/**
 * Connexion au registre des preuves d'intégrité (CAP-CORE-015), physiquement
 * distinct de tout autre magasin. Ne conserve jamais de clé privée — voir
 * `core/registre-preuves/README.md`.
 *
 * Variables dédiées :
 *   PROOF_REGISTRY_URL   PostgreSQL en exploitation ;
 *   PROOF_REGISTRY_PATH  SQLite en local et en CI.
 */
final class Magasin
{
    public static function connecter(?string $chemin = null): \PDO
    {
        $pdo = self::ouvrir($chemin);
        SchemaPreuves::migrer($pdo);

        return $pdo;
    }

    public static function ouvrir(?string $chemin = null): \PDO
    {
        $url = $chemin === null ? self::environnement('PROOF_REGISTRY_URL') : null;
        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host'])) {
                throw new \RuntimeException('PROOF_REGISTRY_URL invalide.');
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
            $chemin ??= self::environnement('PROOF_REGISTRY_PATH')
                ?: dirname(__DIR__) . '/var/preuves.sqlite';
            @mkdir(dirname($chemin), 0777, true);
            $pdo = new \PDO('sqlite:' . $chemin);
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $pdo;
    }

    private static function environnement(string $nom): ?string
    {
        $configuration = match ($nom) {
            'PROOF_REGISTRY_URL' => 'database.connections.gamad_preuves.url',
            'PROOF_REGISTRY_PATH' => 'database.connections.gamad_preuves.database',
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
