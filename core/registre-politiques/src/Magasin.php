<?php

declare(strict_types=1);

namespace Gamad\RegistrePolitiques;

/**
 * Connexion au registre persistant des politiques (CAP-CORE-007), physiquement
 * distinct de l'index reconstructible et de tout autre magasin.
 *
 * Variables dédiées :
 *   POLICY_REGISTRY_URL   PostgreSQL en exploitation ;
 *   POLICY_REGISTRY_PATH  SQLite en local et en CI.
 *
 * DATABASE_URL et les autres variables de magasin appartiennent à d'autres
 * registres et ne sont volontairement jamais consultées ici. C'est le point
 * central du découplage de CAP-CORE-007 : le moteur de décision (CTR-03) ne
 * doit plus jamais lire `politique`/`regle` depuis l'index documentaire.
 */
final class Magasin
{
    public static function connecter(?string $chemin = null): \PDO
    {
        $pdo = self::ouvrir($chemin);
        SchemaPolitiques::migrer($pdo);

        return $pdo;
    }

    /**
     * Ouvre le registre sans appliquer de migration, pour les diagnostics.
     */
    public static function ouvrir(?string $chemin = null): \PDO
    {
        $url = $chemin === null ? self::environnement('POLICY_REGISTRY_URL') : null;
        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host'])) {
                throw new \RuntimeException('POLICY_REGISTRY_URL invalide.');
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
            $chemin ??= self::environnement('POLICY_REGISTRY_PATH')
                ?: dirname(__DIR__) . '/var/registre.sqlite';
            @mkdir(dirname($chemin), 0777, true);
            $pdo = new \PDO('sqlite:' . $chemin);
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $pdo;
    }

    private static function environnement(string $nom): ?string
    {
        $configuration = match ($nom) {
            'POLICY_REGISTRY_URL' => 'database.connections.gamad_policies.url',
            'POLICY_REGISTRY_PATH' => 'database.connections.gamad_policies.database',
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
