<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

/**
 * Connexion au journal central des événements (CAP-CORE-014),
 * physiquement distinct de tout autre magasin — en particulier du journal
 * d'audit privé (CAP-CORE-013, `core/journal-operationnel`) et de tout
 * magasin producteur.
 *
 * Variables dédiées :
 *   EVENT_JOURNAL_URL   PostgreSQL en exploitation ;
 *   EVENT_JOURNAL_PATH  SQLite en local et en CI.
 *
 * Aucun repli silencieux : cette classe ne décide pas elle-même que
 * PostgreSQL est obligatoire en production — cette décision revient à la
 * readiness applicative (`EtatFondation`), qui n'est pas encore raccordée à
 * ce magasin dans ce chantier (limite documentée dans le rapport final).
 */
final class Magasin
{
    public static function connecter(?string $chemin = null): \PDO
    {
        $pdo = self::ouvrir($chemin);
        SchemaEvenements::migrer($pdo);

        return $pdo;
    }

    public static function ouvrir(?string $chemin = null): \PDO
    {
        $url = $chemin === null ? self::environnement('EVENT_JOURNAL_URL') : null;
        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host'])) {
                throw new \RuntimeException('EVENT_JOURNAL_URL invalide.');
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
            $chemin ??= self::environnement('EVENT_JOURNAL_PATH')
                ?: dirname(__DIR__) . '/var/journal.sqlite';
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
            'EVENT_JOURNAL_URL' => 'database.connections.gamad_evenements.url',
            'EVENT_JOURNAL_PATH' => 'database.connections.gamad_evenements.database',
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
