<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use PDO;
use Throwable;

final class PaireGamad
{
    public static function verrouiller(PDO $registre, string $a, string $b, callable $operation): mixed
    {
        $paire = [$a, $b];
        sort($paire, SORT_STRING);
        $sqlite = $registre->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $sqlite ? $registre->exec('BEGIN IMMEDIATE') : $registre->beginTransaction();

        try {
            if (!$sqlite) {
                $verrou = $registre->prepare('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))');
                $verrou->execute($paire);
            }
            $resultat = $operation($paire);
            $sqlite ? $registre->exec('COMMIT') : $registre->commit();

            return $resultat;
        } catch (Throwable $exception) {
            $sqlite ? $registre->exec('ROLLBACK') : $registre->rollBack();
            throw $exception;
        }
    }
}
