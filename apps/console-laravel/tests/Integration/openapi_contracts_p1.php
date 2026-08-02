<?php

declare(strict_types=1);

/**
 * Contrôle de dérive entre les routes Laravel réelles de `/api/v1/contrats*`
 * et leur description dans `openapi/core-v1.yaml` (CAP-CORE-009, section 15
 * de la fiche de codage).
 *
 * Ce contrôle porte sur le périmètre livré par ce chantier — les routes du
 * registre des contrats — et non sur l'ensemble de l'API : auditer et
 * corriger une dérive préexistante ailleurs dans `openapi/core-v1.yaml`
 * n'appartient pas à ce chantier.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/openapi_contracts_p1.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-openapi-contrats-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'contrats' => $temp . '-contrats.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('o', 32)),
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
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
    'CONTRACT_REGISTRY_URL' => '',
    'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';
require $application . '/../../core/registre-contrats/src/GenerateurOpenApi.php';

use Gamad\RegistreContrats\GenerateurOpenApi;

$app = require $application . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$echecs = 0;
$verifier = static function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "INTÉGRATION — DÉRIVE OPENAPI DES CONTRATS (CAP-CORE-009)\n\n";

/** @var list<array{methode:string,chemin:string}> $routesReelles */
$routesReelles = [];
foreach (Route::getRoutes() as $route) {
    $uri = '/' . ltrim($route->uri(), '/');
    if (!str_starts_with($uri, '/api/v1/contrats')) {
        continue;
    }
    foreach ($route->methods() as $methode) {
        if ($methode === 'HEAD') {
            continue;
        }
        $chemin = preg_replace('#^/api/v1#', '', $uri) ?? $uri;
        $routesReelles[] = ['methode' => $methode, 'chemin' => $chemin];
    }
}
$verifier(count($routesReelles) === 21, 'les 21 routes Laravel de /api/v1/contrats* sont enregistrées', (string) count($routesReelles));

$cheminOpenApi = __DIR__ . '/../../openapi/core-v1.yaml';
$operationsFichier = GenerateurOpenApi::extraireOperationsDuFichier($cheminOpenApi);
$operationsContrats = array_values(array_filter(
    $operationsFichier,
    static fn (array $o): bool => str_starts_with($o['chemin'], '/contrats'),
));
$verifier(count($operationsContrats) === 21, 'openapi/core-v1.yaml décrit 21 opérations sous /contrats*', (string) count($operationsContrats));

$cleRoute = static fn (array $r): string => "{$r['methode']} {$r['chemin']}";
$clesReelles = array_map($cleRoute, $routesReelles);
$clesFichier = array_map($cleRoute, $operationsContrats);

sort($clesReelles);
sort($clesFichier);

$manquantesDansFichier = array_values(array_diff($clesReelles, $clesFichier));
$verifier(
    $manquantesDansFichier === [],
    'toute route Laravel réelle de /api/v1/contrats* est décrite dans openapi/core-v1.yaml',
    implode(', ', $manquantesDansFichier),
);

$fantomesDansFichier = array_values(array_diff($clesFichier, $clesReelles));
$verifier(
    $fantomesDansFichier === [],
    'openapi/core-v1.yaml ne décrit aucune opération /contrats* sans route Laravel réelle',
    implode(', ', $fantomesDansFichier),
);

$operationIds = array_column($operationsContrats, 'operationId');
$verifier(
    count($operationIds) === count(array_unique($operationIds)),
    'aucun operationId n’est dupliqué parmi les opérations /contrats*',
);

echo "\n";
if ($echecs === 0) {
    echo "Dérive OpenAPI des contrats P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Dérive OpenAPI des contrats P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
