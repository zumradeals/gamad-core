<?php

declare(strict_types=1);

/**
 * Contre-épreuve du raccordement phpdotenv.
 *
 * Laravel ne garantit pas que les valeurs de .env soient recopiées dans
 * getenv(). Les magasins doivent comprendre $_ENV et $_SERVER pour ne jamais
 * retomber silencieusement sur SQLite en production.
 */

use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;

$application = dirname(__DIR__, 2);
require $application . '/vendor/autoload.php';

$prefixe = sys_get_temp_dir() . '/gamad-laravel-env-' . getmypid();
$variables = [
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $prefixe . '-index.sqlite',
    'MAGASIN_URL' => '',
    'MAGASIN_PATH' => $prefixe . '-acces.sqlite',
    'IDENTITY_REGISTRY_URL' => '',
    'IDENTITY_REGISTRY_PATH' => $prefixe . '-identites.sqlite',
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $prefixe . '-journal.sqlite',
];
$fichiersProcessus = [
    'SQLITE_PATH' => $prefixe . '-process-index.sqlite',
    'MAGASIN_PATH' => $prefixe . '-process-acces.sqlite',
    'IDENTITY_REGISTRY_PATH' => $prefixe . '-process-identites.sqlite',
    'JOURNAL_OPERATIONNEL_PATH' => $prefixe . '-process-journal.sqlite',
];

foreach ($variables as $nom => $valeur) {
    putenv($nom);
    $_ENV[$nom] = $valeur;
    $_SERVER[$nom] = $valeur;
}

register_shutdown_function(static function () use ($variables, $fichiersProcessus): void {
    foreach ($variables as $nom => $fichier) {
        unset($_ENV[$nom], $_SERVER[$nom]);
        if ($fichier !== '') {
            @unlink($fichier);
        }
    }
    foreach ($fichiersProcessus as $fichier) {
        @unlink($fichier);
    }
});

$magasins = [
    'index' => Db::connect(),
    'acces' => AccesMagasin::connecter(),
    'identites' => IdentiteMagasin::connecter(),
    'journal' => JournalMagasin::connecter(),
];

$echecs = 0;
echo "INTÉGRATION — VARIABLES LARAVEL P0\n\n";
foreach ($magasins as $nom => $pdo) {
    $fichier = $variables[match ($nom) {
        'index' => 'SQLITE_PATH',
        'acces' => 'MAGASIN_PATH',
        'identites' => 'IDENTITY_REGISTRY_PATH',
        'journal' => 'JOURNAL_OPERATIONNEL_PATH',
    }];
    $ok = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
        && is_file($fichier);
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', "{$nom} comprend \$_ENV sans getenv()");
    if (!$ok) {
        $echecs++;
    }
}

$magasins = [];
foreach ($fichiersProcessus as $nom => $fichier) {
    putenv("{$nom}={$fichier}");
}
foreach (['DATABASE_URL', 'MAGASIN_URL', 'IDENTITY_REGISTRY_URL', 'JOURNAL_OPERATIONNEL_URL'] as $nom) {
    putenv("{$nom}=");
}

$magasinsProcessus = [
    'index' => Db::connect(),
    'acces' => AccesMagasin::connecter(),
    'identites' => IdentiteMagasin::connecter(),
    'journal' => JournalMagasin::connecter(),
];
foreach ($magasinsProcessus as $nom => $pdo) {
    $fichier = $fichiersProcessus[match ($nom) {
        'index' => 'SQLITE_PATH',
        'acces' => 'MAGASIN_PATH',
        'identites' => 'IDENTITY_REGISTRY_PATH',
        'journal' => 'JOURNAL_OPERATIONNEL_PATH',
    }];
    $ok = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
        && is_file($fichier);
    printf(
        "  %s  %s\n",
        $ok ? '[OK]  ' : '[ÉCHEC]',
        "{$nom} laisse une variable de test explicite isoler \$_ENV",
    );
    if (!$ok) {
        $echecs++;
    }
}

$urlInterdite = 'pgsql://test:test@127.0.0.1:1/ne-jamais-ouvrir';
foreach (['MAGASIN_URL', 'IDENTITY_REGISTRY_URL', 'JOURNAL_OPERATIONNEL_URL'] as $nom) {
    putenv("{$nom}={$urlInterdite}");
}
$fichiersExplicites = [
    'acces' => $prefixe . '-explicit-acces.sqlite',
    'identites' => $prefixe . '-explicit-identites.sqlite',
    'journal' => $prefixe . '-explicit-journal.sqlite',
];
$magasinsExplicites = [
    'acces' => AccesMagasin::connecter($fichiersExplicites['acces']),
    'identites' => IdentiteMagasin::connecter($fichiersExplicites['identites']),
    'journal' => JournalMagasin::connecter($fichiersExplicites['journal']),
];
foreach ($magasinsExplicites as $nom => $pdo) {
    $ok = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
        && is_file($fichiersExplicites[$nom]);
    printf(
        "  %s  %s\n",
        $ok ? '[OK]  ' : '[ÉCHEC]',
        "{$nom} donne la priorité absolue à un chemin explicite",
    );
    if (!$ok) {
        $echecs++;
    }
}
foreach ($fichiersExplicites as $fichier) {
    @unlink($fichier);
}

echo "\n";
if ($echecs === 0) {
    echo "Raccordement Laravel : ÉTABLI.\n";
    exit(0);
}

echo "Raccordement Laravel : NON ÉTABLI ({$echecs} écart(s)).\n";
exit(1);
