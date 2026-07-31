<?php

declare(strict_types=1);

/**
 * Épreuve d'intégration de la première migration runtime hors Genesis II.
 *
 * Exécution depuis la racine :
 *   php apps/console-laravel/tests/Integration/reindexation_baseline_p1.php
 */

use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-baseline-'.getmypid().'.sqlite';
@unlink($temp);
register_shutdown_function(static fn () => @unlink($temp));

putenv('DATABASE_URL=');
putenv('SQLITE_PATH='.$temp);
$_ENV['DATABASE_URL'] = '';
$_SERVER['DATABASE_URL'] = '';
$_ENV['SQLITE_PATH'] = $temp;
$_SERVER['SQLITE_PATH'] = $temp;

require $application.'/vendor/autoload.php';

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) {
        $echecs++;
    }
};

echo "INTÉGRATION — RÉINDEXATION PAR BASELINE P1\n\n";

$baseline = BaselineOperationnelle::standard();
$pdo = Db::connect();
$premier = $baseline->reconstruire($pdo);

$verifier(
    $premier === [
        'adoptions' => 66,
        'normes' => 95,
        'versions' => 95,
        'statuts' => 95,
        'etats' => 40,
        'rangs' => 9,
        'sources' => 26,
        'fonctions' => 24,
        'entites' => 7,
        'regles' => 14,
        'mandats' => 1,
        'indetermines' => 95,
    ],
    'la baseline reconstruit les données techniques attendues',
);

$verifier(
    (int) $pdo->query("SELECT count(*) FROM entite WHERE reference = 'AUT-GAMAD-001'")->fetchColumn() === 1
        && (int) $pdo->query("SELECT count(*) FROM politique WHERE reference = 'POL-INSCRIPTION-IDENTITES-V1'")->fetchColumn() === 1,
    'les ancrages nécessaires à l’identité et à l’autorisation sont présents',
);

$second = $baseline->reconstruire($pdo);
$verifier(
    $second === $premier
        && (int) $pdo->query('SELECT count(*) FROM version_norme')->fetchColumn() === 95,
    'la reconstruction est idempotente',
);

$commande = (string) file_get_contents(
    $application.'/app/Console/Commands/ReindexerCommand.php'
);
$verifier(
    ! str_contains($commande, 'Ingestion')
        && ! str_contains($commande, 'CORPUS_PATH')
        && ! str_contains($commande, 'Process')
        && ! str_contains(mb_strtolower($commande, 'UTF-8'), 'genesis-ii/'),
    'la commande ne dépend plus du parseur ni du chemin du corpus',
);

$brut = (string) file_get_contents($baseline->chemin());
$verifier(
    hash('sha256', $brut) === $baseline->empreinte(),
    'la source versionnée est protégée par une empreinte vérifiée',
);

$copie = sys_get_temp_dir().'/gamad-baseline-corrompue-'.getmypid().'.json';
file_put_contents($copie, $brut." ");
try {
    (new BaselineOperationnelle($copie))->reconstruire($pdo);
    $corruptionRefusee = false;
} catch (RuntimeException) {
    $corruptionRefusee = true;
} finally {
    @unlink($copie);
}
$verifier(
    $corruptionRefusee
        && (int) $pdo->query('SELECT count(*) FROM version_norme')->fetchColumn() === 95,
    'une baseline altérée est refusée sans détruire l’index existant',
);

$verifier(
    ! str_contains($brut, 'genesis-ii/'),
    'la source de réindexation ne référence plus aucun fichier du corpus historique',
);

echo "\n";
if ($echecs === 0) {
    echo "Réindexation par baseline P1 : ÉTABLIE.\n";
    exit(0);
}

echo "Réindexation par baseline P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
