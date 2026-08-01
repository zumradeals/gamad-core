<?php

declare(strict_types=1);

/**
 * Vérifie que le contrôleur CTR-03 initialise un index absent depuis la
 * baseline opérationnelle, sans lecture de Genesis II ni appel à Ingestion.
 */

use App\Http\Controllers\Ctr03Controller;
use Gamad\RegistreNormes\Db;

$application = dirname(__DIR__, 2);
$racine = dirname($application, 2);
$temp = sys_get_temp_dir().'/gamad-ctr03-baseline-'.getmypid();
$fichier = $temp.'-index.sqlite';
@unlink($fichier);
register_shutdown_function(static function () use ($fichier): void { @unlink($fichier); });

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
    'APP_CONFIG_CACHE' => $temp.'-config.php',
    'APP_EVENTS_CACHE' => $temp.'-events.php',
    'APP_PACKAGES_CACHE' => $temp.'-packages.php',
    'APP_ROUTES_CACHE' => $temp.'-routes.php',
    'APP_SERVICES_CACHE' => $temp.'-services.php',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $fichier,
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';
$app = require $application.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) { $echecs++; }
};

echo "INTÉGRATION — CTR-03 PAR BASELINE P1\n\n";
$verifier(! is_file($fichier), 'le scénario démarre sans index préconstruit');

$controleur = new Ctr03Controller();
$methode = new ReflectionMethod($controleur, 'ctr03');
$methode->setAccessible(true);
$ctr03 = $methode->invoke($controleur);

$pdo = Db::connect();
$regles = (int) $pdo->query('SELECT count(*) FROM regle')->fetchColumn();
$verifier($regles === 18, 'la première résolution restaure les 18 règles techniques');

$permise = $ctr03->simuler('AUT-GAMAD-001', 'inscrire une identité', 'personne');
$verifier(
    ($permise['decision'] ?? null) === 'PERMIS'
        && ($permise['politique'] ?? null) === 'POL-INSCRIPTION-IDENTITES-V1',
    'la décision historiquement permise reste permise',
);

$refusee = $ctr03->simuler('SUJET-INCONNU', 'action inconnue', null);
$verifier(
    ($refusee['decision'] ?? null) !== 'PERMIS',
    'une action inconnue reste refusée par défaut',
);

$ctr03Seconde = $methode->invoke($controleur);
$reglesApres = (int) $pdo->query('SELECT count(*) FROM regle')->fetchColumn();
$verifier($reglesApres === $regles && $ctr03Seconde !== null, 'une seconde résolution reste non destructive');

$source = file_get_contents($racine.'/apps/console-laravel/app/Http/Controllers/Ctr03Controller.php');
$verifier(
    is_string($source)
        && str_contains($source, 'BaselineOperationnelle::standard()->reconstruire')
        && ! str_contains($source, 'Ingestion')
        && ! str_contains($source, 'genesis-ii'),
    'le contrôleur ne dépend plus du parseur ni du corpus historique',
);

echo "\n";
if ($echecs === 0) {
    echo "CTR-03 par baseline P1 : ÉTABLI.\n";
    exit(0);
}

echo "CTR-03 par baseline P1 : NON ÉTABLI ({$echecs} écart(s)).\n";
exit(1);
