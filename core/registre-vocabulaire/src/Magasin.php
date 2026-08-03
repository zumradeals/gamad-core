<?php

declare(strict_types=1);

namespace Gamad\RegistreVocabulaire;

/**
 * Connexion au registre persistant du vocabulaire canonique (CAP-CORE-010),
 * physiquement distinct de tout autre magasin.
 *
 * Variables dédiées :
 *   VOCABULARY_REGISTRY_URL   PostgreSQL en exploitation ;
 *   VOCABULARY_REGISTRY_PATH  SQLite en local et en CI.
 *
 * Aucun repli silencieux : en production, l'absence de
 * `VOCABULARY_REGISTRY_URL` doit échouer bruyamment (voir
 * `EtatFondation::inspecterCible`), jamais retomber sur SQLite sans que
 * quiconque ne le décide explicitement.
 */
final class Magasin
{
    public static function connecter(?string $chemin = null): \PDO
    {
        $pdo = self::ouvrir($chemin);
        SchemaVocabulaire::migrer($pdo);

        return $pdo;
    }

    /**
     * Ouvre le registre sans appliquer de migration, pour les diagnostics.
     */
    public static function ouvrir(?string $chemin = null): \PDO
    {
        $url = $chemin === null ? self::environnement('VOCABULARY_REGISTRY_URL') : null;
        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host'])) {
                throw new \RuntimeException('VOCABULARY_REGISTRY_URL invalide.');
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
            $chemin ??= self::environnement('VOCABULARY_REGISTRY_PATH')
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
            'VOCABULARY_REGISTRY_URL' => 'database.connections.gamad_vocabulary.url',
            'VOCABULARY_REGISTRY_PATH' => 'database.connections.gamad_vocabulary.database',
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
