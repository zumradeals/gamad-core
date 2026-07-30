<?php

declare(strict_types=1);

namespace Gamad\JournalOperationnel;

/**
 * Connexion dédiée au journal d'exploitation et d'audit.
 *
 * JOURNAL_OPERATIONNEL_URL doit désigner PostgreSQL en exploitation.
 * JOURNAL_OPERATIONNEL_PATH n'est qu'un repli local et de test.
 */
final class Magasin
{
    public static function connecter(?string $chemin = null): \PDO
    {
        $pdo = self::ouvrir($chemin);
        Schema::migrer($pdo);

        return $pdo;
    }

    public static function ouvrir(?string $chemin = null): \PDO
    {
        // Un chemin explicite impose le magasin isolé des gardes.
        $url = $chemin === null ? self::environnement('JOURNAL_OPERATIONNEL_URL') : null;
        if (is_string($url) && $url !== '') {
            $p = parse_url($url);
            if ($p === false || !isset($p['host']) || trim((string) ($p['path'] ?? ''), '/') === '') {
                throw new \RuntimeException('JOURNAL_OPERATIONNEL_URL invalide.');
            }
            $pdo = new \PDO(
                sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    $p['host'],
                    $p['port'] ?? 5432,
                    ltrim((string) $p['path'], '/'),
                ),
                $p['user'] ?? null,
                isset($p['pass']) ? urldecode($p['pass']) : null,
            );
        } else {
            $chemin ??= self::environnement('JOURNAL_OPERATIONNEL_PATH')
                ?: dirname(__DIR__) . '/var/journal.sqlite';
            if (!is_dir(dirname($chemin)) && !@mkdir(dirname($chemin), 0770, true)) {
                throw new \RuntimeException('Impossible de créer le dossier du journal opérationnel.');
            }
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
        foreach ([getenv($nom), $_ENV[$nom] ?? null, $_SERVER[$nom] ?? null] as $valeur) {
            if (is_string($valeur)) {
                return $valeur;
            }
        }

        return null;
    }
}
