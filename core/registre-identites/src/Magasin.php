<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Connexion au registre persistant, physiquement distinct de l'index dérivé.
 *
 * Variables dédiées :
 *   IDENTITY_REGISTRY_URL  PostgreSQL en exploitation ;
 *   IDENTITY_REGISTRY_PATH SQLite en local et en CI.
 *
 * DATABASE_URL et SQLITE_PATH appartiennent à l'index reconstructible et ne
 * sont volontairement jamais consultées ici.
 */
final class Magasin
{
    public static function connecter(?string $chemin = null): \PDO
    {
        $pdo = self::ouvrir($chemin);
        SchemaInscription::migrer($pdo);

        return $pdo;
    }

    /**
     * Ouvre le registre sans appliquer de migration, pour les diagnostics.
     */
    public static function ouvrir(?string $chemin = null): \PDO
    {
        $url = self::environnement('IDENTITY_REGISTRY_URL');
        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host'])) {
                throw new \RuntimeException('IDENTITY_REGISTRY_URL invalide.');
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
            $chemin ??= self::environnement('IDENTITY_REGISTRY_PATH')
                ?: dirname(__DIR__) . '/var/registre.sqlite';
            @mkdir(dirname($chemin), 0777, true);
            $pdo = new \PDO('sqlite:' . $chemin);
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $pdo;
    }

    /**
     * Comprend aussi les variables chargées par Laravel via phpdotenv.
     */
    private static function environnement(string $nom): ?string
    {
        foreach ([$_ENV[$nom] ?? null, $_SERVER[$nom] ?? null, getenv($nom)] as $valeur) {
            if (is_string($valeur)) {
                return $valeur;
            }
        }

        return null;
    }
}
