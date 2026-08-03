<?php

declare(strict_types=1);

namespace Gamad\RegistreRealms;

/**
 * Connexion au registre persistant des realms (CAP-CORE-012),
 * physiquement distinct de tout autre magasin — en particulier du registre
 * d'identités (CAP-CORE-001), du registre des organisations (CAP-CORE-002)
 * et du registre des produits (CAP-CORE-011).
 *
 * Variables dédiées :
 *   REALM_REGISTRY_URL   PostgreSQL en exploitation ;
 *   REALM_REGISTRY_PATH  SQLite en local et en CI.
 *
 * Aucune autre variable de magasin n'est consultée ici. Aucun repli
 * silencieux : en production, l'absence de `REALM_REGISTRY_URL` doit échouer
 * bruyamment (voir `EtatFondation::inspecterCible`), jamais retomber sur
 * SQLite sans que quiconque ne le décide explicitement.
 */
final class Magasin
{
    public static function connecter(?string $chemin = null): \PDO
    {
        $pdo = self::ouvrir($chemin);
        SchemaRealms::migrer($pdo);

        return $pdo;
    }

    /**
     * Ouvre le registre sans appliquer de migration, pour les diagnostics.
     */
    public static function ouvrir(?string $chemin = null): \PDO
    {
        $url = $chemin === null ? self::environnement('REALM_REGISTRY_URL') : null;
        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host'])) {
                throw new \RuntimeException('REALM_REGISTRY_URL invalide.');
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
            $chemin ??= self::environnement('REALM_REGISTRY_PATH')
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
            'REALM_REGISTRY_URL' => 'database.connections.gamad_realms.url',
            'REALM_REGISTRY_PATH' => 'database.connections.gamad_realms.database',
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
