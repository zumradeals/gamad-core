<?php

declare(strict_types=1);

/**
 * Contrôle de dérive entre les routes Laravel réelles de `/api/v1/realms*`
 * et leur description dans `openapi/core-v1.yaml`, et vérification du
 * contrat interne `CTR-12` (CAP-CORE-009) et de la façade `Ctr12` (CAP-CORE-012,
 * fiche §46, §50).
 *
 * Ce contrôle porte sur le périmètre livré par ce chantier — les routes du
 * registre des realms — et non sur l'ensemble de l'API.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/realms_contracts_p1.php
 */

use Gamad\RegistreContrats\GenerateurOpenApi;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;
use Gamad\RegistreRealms\Ctr12;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\PolitiqueRealms;
use Gamad\RegistreRealms\RegistreRealms;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-realms-contracts-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'contrats' => $temp . '-contrats.sqlite',
    'vocabulaire' => $temp . '-vocabulaire.sqlite',
    'organisations' => $temp . '-organisations.sqlite',
    'produits' => $temp . '-produits.sqlite',
    'realms' => $temp . '-realms.sqlite',
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
    'CONTRACT_REGISTRY_URL' => '',
    'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
    'VOCABULARY_REGISTRY_URL' => '',
    'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
    'ORGANIZATION_REGISTRY_URL' => '',
    'ORGANIZATION_REGISTRY_PATH' => $fichiers['organisations'],
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'REALM_REGISTRY_URL' => '',
    'REALM_REGISTRY_PATH' => $fichiers['realms'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';
require $application . '/../../core/registre-contrats/src/GenerateurOpenApi.php';

$app = require $application . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$echecs = 0;
$verifier = static function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "INTÉGRATION — CONTRATS ET DÉRIVE OPENAPI DES REALMS (CAP-CORE-012)\n\n";

// ------------------------------------------------------------------
// Dérive OpenAPI, scoping sur /realms* (fiche §50)

/** @var list<array{methode:string,chemin:string}> $routesReelles */
$routesReelles = [];
foreach (Route::getRoutes() as $route) {
    $uri = '/' . ltrim($route->uri(), '/');
    if (!str_starts_with($uri, '/api/v1/realms')) {
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
$verifier(count($routesReelles) > 0, 'des routes Laravel de /api/v1/realms* sont enregistrées', (string) count($routesReelles));

$cheminOpenApi = __DIR__ . '/../../openapi/core-v1.yaml';
$operationsFichier = GenerateurOpenApi::extraireOperationsDuFichier($cheminOpenApi);
$operationsRealms = array_values(array_filter(
    $operationsFichier,
    static fn (array $o): bool => str_starts_with($o['chemin'], '/realms'),
));
$verifier(
    count($operationsRealms) === count($routesReelles),
    'openapi/core-v1.yaml décrit autant d’opérations que de routes Laravel sous /realms*',
    sprintf('routes=%d openapi=%d', count($routesReelles), count($operationsRealms)),
);

$cleRoute = static fn (array $r): string => "{$r['methode']} {$r['chemin']}";
$clesReelles = array_map($cleRoute, $routesReelles);
$clesFichier = array_map($cleRoute, $operationsRealms);
sort($clesReelles);
sort($clesFichier);

$manquantesDansFichier = array_values(array_diff($clesReelles, $clesFichier));
$verifier(
    $manquantesDansFichier === [],
    'toute route Laravel réelle de /api/v1/realms* est décrite dans openapi/core-v1.yaml',
    implode(', ', $manquantesDansFichier),
);

$fantomesDansFichier = array_values(array_diff($clesFichier, $clesReelles));
$verifier(
    $fantomesDansFichier === [],
    'openapi/core-v1.yaml ne décrit aucune opération /realms* sans route Laravel réelle',
    implode(', ', $fantomesDansFichier),
);

$operationIds = array_column($operationsRealms, 'operationId');
$verifier(
    count($operationIds) === count(array_unique($operationIds)),
    'aucun operationId n’est dupliqué parmi les opérations /realms*',
);

// ------------------------------------------------------------------
// CTR-12 (CAP-CORE-009) : contrat interne bootstrapé et actif

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$registreIdentites = IdentiteMagasin::connecter();
ContratsMagasin::connecter();
OrganisationsMagasin::connecter();
ProduitsMagasin::connecter();
RealmsMagasin::connecter();

$app->make(Kernel::class)->call('core:politiques:bootstrap');
$app->make(Kernel::class)->call('core:organisations:bootstrap');
$app->make(Kernel::class)->call('core:produits:bootstrap');
$app->make(Kernel::class)->call('core:contrats:bootstrap');
$app->make(Kernel::class)->call('core:vocabulaire:bootstrap');
$app->make(Kernel::class)->call('core:realms:bootstrap');

$ctr01 = new Ctr01($index, $registreIdentites);
$contrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);
$fiche = $contrats->resoudreContrat(PolitiqueRealms::CONTRAT);
$verifier(
    $fiche !== null && ($fiche['version_active'] ?? null) === '1.0.0',
    'CTR-12 est inscrit dans CAP-CORE-009 et sa version 1.0.0 est active après bootstrap',
);
$version = $contrats->resoudreVersion(PolitiqueRealms::CONTRAT, '1.0.0');
$verifier(
    ($version['operations'] ?? []) !== [] && count($version['operations']) >= 8,
    'CTR-12 décrit au moins les huit opérations minimales de la fiche §46',
);

// ------------------------------------------------------------------
// Ctr12 : façade de lecture minimale, fermée en explicabilité (fiche §46)

$organisations = new RegistreOrganisations($index, $registreIdentites, OrganisationsMagasin::connecter(), $ctr01);
$produits = new RegistreProduits($index, $registreIdentites, ProduitsMagasin::connecter(), $ctr01);
$registreRealms = new RegistreRealms($index, $registreIdentites, RealmsMagasin::connecter(), $ctr01, $organisations, $produits, $contrats);
$ctr12 = new Ctr12($registreRealms);

$IDN_RLM = $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE', 'type' => 'realm', 'libelle' => 'Realm Contrats P1',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-CTR12-P1',
    'source' => 'garde CTR-12', 'preuve' => 'EVT-CTR12-P1-IDN',
])['reference'];
$inscription = $registreRealms->inscrireRealm([
    'identite_reference' => $IDN_RLM, 'code_canonique' => 'RLM-CTR12-P1', 'type_realm_reference' => 'TECHNIQUE',
    'source' => 'garde CTR-12', 'nom_affichage' => 'Realm Contrats P1', 'classification_reference' => 'INTERNE',
    'politique' => PolitiqueRealms::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'preuve' => 'EVT-CTR12-P1-INSCRIPTION',
]);
$RLM = (string) $inscription['reference'];

$verifier(
    ($ctr12->resoudreRealm($RLM)['reference'] ?? null) === $RLM,
    'Ctr12::resoudreRealm() résout le realm réellement inscrit',
);
$verifier(
    ($ctr12->resoudreEtat($RLM)['etat'] ?? null) === 'PREPARATION',
    'Ctr12::resoudreEtat() reflète l’état réel du realm',
);
$verifier(
    $ctr12->resoudreRealm('RLM-GAMAD-INEXISTANT') === null,
    'Ctr12::resoudreRealm() renvoie null pour une référence inconnue, sans exception',
);
$porteeInconnue = $ctr12->verifierPortee(['realm' => 'RLM-GAMAD-INEXISTANT']);
$verifier(
    ($porteeInconnue['dans_portee'] ?? true) === false && ($porteeInconnue['motifs'] ?? []) === ['REALM_INCONNU'],
    'Ctr12::verifierPortee() ferme la portée d’un realm inconnu, explicablement',
);

// Registre indisponible ou magasin distinct : un realm inconnu de CE magasin
// ferme le contrôle de portée sans exception, jamais une portée globale
// supposée (fiche §61, §68). `Ctr12` ne relève JAMAIS une exception vers
// l'appelant : elle referme la réponse.
$magasinIsole = new \PDO('sqlite::memory:');
$registreIsole = new RegistreRealms($index, $registreIdentites, $magasinIsole, $ctr01, null, null, null);
$ctr12Isole = new Ctr12($registreIsole);
$porteeIsolee = $ctr12Isole->verifierPortee(['realm' => $RLM]);
$verifier(
    ($porteeIsolee['dans_portee'] ?? true) === false && ($porteeIsolee['motifs'] ?? []) === ['REALM_INCONNU'],
    'un realm inconnu d’un magasin distinct ferme le contrôle de portée, sans exception ni portée globale supposée',
);

echo "\n";
if ($echecs === 0) {
    echo "Contrats et dérive OpenAPI des realms P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Contrats et dérive OpenAPI des realms P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
