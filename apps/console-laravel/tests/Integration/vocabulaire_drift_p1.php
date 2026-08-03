<?php

declare(strict_types=1);

/**
 * Contrôle de dérive entre les routes Laravel réelles de
 * `/api/v1/vocabulaires*` et `/api/v1/termes*` et leur description dans
 * `openapi/core-v1.yaml` (CAP-CORE-010).
 *
 * Ce contrôle porte sur le périmètre livré par ce chantier — les routes du
 * registre du vocabulaire canonique — et non sur l'ensemble de l'API :
 * auditer et corriger une dérive préexistante ailleurs dans
 * `openapi/core-v1.yaml` n'appartient pas à ce chantier.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/vocabulaire_drift_p1.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-openapi-vocabulaire-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'vocabulaire' => $temp . '-vocabulaire.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('d', 32)),
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
    'VOCABULARY_REGISTRY_URL' => '',
    'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
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

echo "INTÉGRATION — DÉRIVE OPENAPI DU VOCABULAIRE (CAP-CORE-010)\n\n";

/** @var list<array{methode:string,chemin:string}> $routesReelles */
$routesReelles = [];
foreach (Route::getRoutes() as $route) {
    $uri = '/' . ltrim($route->uri(), '/');
    if (!str_starts_with($uri, '/api/v1/vocabulaires') && !str_starts_with($uri, '/api/v1/termes')) {
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
$verifier(count($routesReelles) > 0, 'des routes Laravel de /api/v1/vocabulaires* et /api/v1/termes* sont enregistrées', (string) count($routesReelles));

$cheminOpenApi = __DIR__ . '/../../openapi/core-v1.yaml';
$operationsFichier = GenerateurOpenApi::extraireOperationsDuFichier($cheminOpenApi);
$operationsVocabulaire = array_values(array_filter(
    $operationsFichier,
    static fn (array $o): bool => str_starts_with($o['chemin'], '/vocabulaires') || str_starts_with($o['chemin'], '/termes'),
));
$verifier(
    count($operationsVocabulaire) === count($routesReelles),
    'openapi/core-v1.yaml décrit autant d’opérations que de routes Laravel sous /vocabulaires* et /termes*',
    sprintf('fichier=%d, routes=%d', count($operationsVocabulaire), count($routesReelles)),
);

$cleRoute = static fn (array $r): string => "{$r['methode']} {$r['chemin']}";
$clesReelles = array_map($cleRoute, $routesReelles);
$clesFichier = array_map($cleRoute, $operationsVocabulaire);

sort($clesReelles);
sort($clesFichier);

$manquantesDansFichier = array_values(array_diff($clesReelles, $clesFichier));
$verifier(
    $manquantesDansFichier === [],
    'toute route Laravel réelle de /api/v1/vocabulaires* et /api/v1/termes* est décrite dans openapi/core-v1.yaml',
    implode(', ', $manquantesDansFichier),
);

$fantomesDansFichier = array_values(array_diff($clesFichier, $clesReelles));
$verifier(
    $fantomesDansFichier === [],
    'openapi/core-v1.yaml ne décrit aucune opération /vocabulaires* ou /termes* sans route Laravel réelle',
    implode(', ', $fantomesDansFichier),
);

$operationIds = array_column($operationsVocabulaire, 'operationId');
$verifier(
    count($operationIds) === count(array_unique($operationIds)),
    'aucun operationId n’est dupliqué parmi les opérations /vocabulaires* et /termes*',
);

echo "\n";
if ($echecs === 0) {
    echo "Dérive OpenAPI du vocabulaire P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Dérive OpenAPI du vocabulaire P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
