<?php

declare(strict_types=1);

/**
 * Vérifie que CTR-02 initialise un index absent depuis la baseline opérationnelle,
 * sans lecture du corpus Genesis II ni appel au parseur Ingestion.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/ctr02_baseline_p1.php
 */

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$racine = dirname($application, 2);
$temp = sys_get_temp_dir().'/gamad-ctr02-baseline-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'journal' => $temp.'-journal.sqlite',
];
foreach ($fichiers as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $fichier) {
        @unlink($fichier);
    }
});

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('m', 32)),
    'APP_CONFIG_CACHE' => $temp.'-config.php',
    'APP_EVENTS_CACHE' => $temp.'-events.php',
    'APP_PACKAGES_CACHE' => $temp.'-packages.php',
    'APP_ROUTES_CACHE' => $temp.'-routes.php',
    'APP_SERVICES_CACHE' => $temp.'-services.php',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $fichiers['index'],
    'MAGASIN_URL' => '',
    'MAGASIN_PATH' => $fichiers['acces'],
    'IDENTITY_REGISTRY_URL' => '',
    'IDENTITY_REGISTRY_PATH' => $fichiers['identites'],
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';

$secret = 'Secret-CTR02-Baseline-2026!';
(new Ctr16(AccesMagasin::connecter()))->inscrireAuthentificateur('AUT-GAMAD-001', $secret);

$app = require $application.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) {
        $echecs++;
    }
};
$requete = static function (
    string $methode,
    string $uri,
    ?array $json = null,
    ?string $jeton = null,
) use ($kernel): array {
    $serveur = [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ];
    if ($jeton !== null) {
        $serveur['HTTP_AUTHORIZATION'] = 'Bearer '.$jeton;
    }
    $request = Request::create(
        $uri,
        $methode,
        [],
        [],
        [],
        $serveur,
        $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR),
    );
    $response = $kernel->handle($request);
    $corps = json_decode((string) $response->getContent(), true);
    $resultat = [
        'statut' => $response->getStatusCode(),
        'corps' => is_array($corps) ? $corps : [],
    ];
    $kernel->terminate($request, $response);

    return $resultat;
};

echo "INTÉGRATION — CTR-02 PAR BASELINE P1\n\n";

$verifier(! is_file($fichiers['index']), 'le scénario démarre sans index préconstruit');

$connexion = $requete('POST', '/api/v1/sessions', [
    'entite' => 'AUT-GAMAD-001',
    'secret' => $secret,
]);
$jeton = (string) ($connexion['corps']['jeton'] ?? '');
$verifier(
    $connexion['statut'] === 201 && $jeton !== '',
    'une session API est ouverte sans initialiser l’index des normes',
);
$verifier(! is_file($fichiers['index']), 'l’index reste absent avant la première lecture CTR-02');

$mandat = $requete('GET', '/api/v1/mandats/FCT-CORE-001?date=2026-07-27', null, $jeton);
$verifier(
    $mandat['statut'] === 200
        && ($mandat['corps']['mandat'] ?? null) === 'MANDAT-GENESIS-II-0001'
        && ($mandat['corps']['fonction'] ?? null) === 'FCT-CORE-001'
        && str_starts_with((string) ($mandat['corps']['etat'] ?? ''), 'ACTIF'),
    'CTR-02 résout le mandat canonique depuis un index initialement vide',
);

$pdo = Db::connect();
$mandats = (int) $pdo->query('SELECT count(*) FROM mandat')->fetchColumn();
$fonctions = (int) $pdo->query('SELECT count(*) FROM fonction')->fetchColumn();
$verifier(
    $mandats === 1 && $fonctions === 24,
    'la baseline restaure le mandat et les fonctions techniques attendus',
);

$seconde = $requete('GET', '/api/v1/mandats/FCT-CORE-001?date=2026-07-27', null, $jeton);
$mandatsApres = (int) $pdo->query('SELECT count(*) FROM mandat')->fetchColumn();
$verifier(
    $seconde['statut'] === 200 && $mandatsApres === $mandats,
    'une seconde lecture réutilise l’index sans reconstruction destructive',
);

$controleur = file_get_contents(
    $racine.'/apps/console-laravel/app/Http/Controllers/Ctr02Controller.php'
);
$verifier(
    is_string($controleur)
        && str_contains($controleur, 'BaselineOperationnelle::standard()->reconstruire')
        && ! str_contains($controleur, 'Ingestion')
        && ! str_contains($controleur, 'genesis-ii'),
    'le contrôleur ne dépend plus du parseur ni du corpus historique',
);

echo "\n";
if ($echecs === 0) {
    echo "CTR-02 par baseline P1 : ÉTABLI.\n";
    exit(0);
}

echo "CTR-02 par baseline P1 : NON ÉTABLI ({$echecs} écart(s)).\n";
exit(1);
