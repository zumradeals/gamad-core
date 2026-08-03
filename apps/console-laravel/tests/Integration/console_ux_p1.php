<?php

declare(strict_types=1);

/**
 * Épreuve d'intégration de la console quotidienne.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/console_ux_p1.php
 */

use App\Application\Identites\InscrireIdentite;
use App\Http\Controllers\Ctr04Controller;
use App\Http\Controllers\IdentiteConsoleController;
use App\Support\EtatFondation;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreFederation\SchemaFederation;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-console-ux-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'produits' => $temp.'-produits.sqlite',
    'sources' => $temp.'-sources.sqlite',
    'politiques' => $temp.'-politiques.sqlite',
    'contrats' => $temp.'-contrats.sqlite',
    'vocabulaire' => $temp.'-vocabulaire.sqlite',
    'organisations' => $temp.'-organisations.sqlite',
    'journal' => $temp.'-journal.sqlite',
    'config' => $temp.'-config.php',
    'events' => $temp.'-events.php',
    'packages' => $temp.'-packages.php',
    'routes' => $temp.'-routes.php',
    'services' => $temp.'-services.php',
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('u', 32)),
    'APP_URL' => 'https://console.test',
    'APP_CONFIG_CACHE' => $fichiers['config'],
    'APP_EVENTS_CACHE' => $fichiers['events'],
    'APP_PACKAGES_CACHE' => $fichiers['packages'],
    'APP_ROUTES_CACHE' => $fichiers['routes'],
    'APP_SERVICES_CACHE' => $fichiers['services'],
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $fichiers['index'],
    'MAGASIN_URL' => '',
    'MAGASIN_PATH' => $fichiers['acces'],
    'IDENTITY_REGISTRY_URL' => '',
    'IDENTITY_REGISTRY_PATH' => $fichiers['identites'],
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'SOURCE_REGISTRY_URL' => '',
    'SOURCE_REGISTRY_PATH' => $fichiers['sources'],
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
    'CONTRACT_REGISTRY_URL' => '',
    'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
    'VOCABULARY_REGISTRY_URL' => '',
    'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
    'ORGANIZATION_REGISTRY_URL' => '',
    'ORGANIZATION_REGISTRY_PATH' => $fichiers['organisations'],
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
SchemaFederation::migrer(AccesMagasin::connecter());
IdentiteMagasin::connecter();
ProduitsMagasin::connecter();
SourcesMagasin::connecter();
PolitiquesMagasin::connecter();
ContratsMagasin::connecter();
VocabulaireMagasin::connecter();
OrganisationsMagasin::connecter();
JournalMagasin::connecter();

$app = require $application.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(Kernel::class)->bootstrap();
$session = $app->make('session')->driver();
$session->start();
$app->make('view')->share('errors', new ViewErrorBag);

$requete = static function (string $uri, array $query = []) use ($app, $session): Request {
    $request = Request::create($uri, 'GET', $query);
    $request->setLaravelSession($session);
    $request->attributes->set('gamad_entite', 'AUT-GAMAD-001');
    $request->attributes->set('gamad_assurance', 'AS1');
    $app->instance('request', $request);

    return $request;
};

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) {
        $echecs++;
    }
};

echo "INTÉGRATION — CONSOLE QUOTIDIENNE P1\n\n";

$requestAccueil = $requete('/');
$accueil = $app->make(Ctr04Controller::class)
    ->tableauDeBord($requestAccueil, $app->make(EtatFondation::class))
    ->render();
$verifier(
    str_contains($accueil, 'Bonjour.')
        && str_contains($accueil, 'Votre attention')
        && str_contains($accueil, 'Actions rapides')
        && str_contains($accueil, 'Le Core fonctionne normalement'),
    'l’accueil répond par l’état, les alertes et les actions utiles',
);

$requestIndex = $requete('/identites');
$controleur = $app->make(IdentiteConsoleController::class);
$inventaire = $controleur->index($requestIndex)->render();
$verifier(
    str_contains($inventaire, 'Nouvelle identité')
        && str_contains($inventaire, 'Rechercher')
        && str_contains($inventaire, 'AUT-GAMAD-001')
        && ! str_contains($inventaire, '<pre>'),
    'l’inventaire est lisible et ne restitue pas du JSON brut',
);

$requestCreation = $requete('/identites/nouvelle');
$creation = $controleur->create($requestCreation)->render();
$verifier(
    str_contains($creation, 'Qui ou quoi inscrivez-vous')
        && str_contains($creation, 'POL-INSCRIPTION-IDENTITES-V1')
        && str_contains($creation, 'Prêt à inscrire'),
    'le formulaire explique le type, la politique et l’autorisation avant confirmation',
);

$execution = $app->make(InscrireIdentite::class)->executer([
    'canal' => 'AUTORITE',
    'type' => 'personne',
    'libelle' => 'Identité visible dans la console',
    'classification' => 'INTERNE',
    'provisoire' => false,
], 'AUT-GAMAD-001');
$reference = (string) ($execution['corps']['identite']['reference'] ?? '');
$requestFiche = $requete('/identites/'.$reference);
$fiche = $reference !== '' ? $controleur->show($requestFiche, $reference)->render() : '';
$verifier(
    $execution['statut'] === 201
        && str_contains($fiche, 'Identité visible dans la console')
        && str_contains($fiche, 'Assurance A3')
        && str_contains($fiche, (string) $execution['corps']['preuve']['reference']),
    'une identité inscrite possède une fiche, son assurance et sa preuve',
);

$css = (string) file_get_contents($application.'/public/css/gamad-core.css');
$verifier(
    str_contains($css, '--gamad-yellow: #f8d40a')
        && str_contains($css, '@media (max-width: 820px)')
        && str_contains($css, '@media (prefers-reduced-motion: reduce)'),
    'le design system GAMAD couvre la marque, Android et la réduction des animations',
);

echo "\n";
if ($echecs === 0) {
    echo "Console quotidienne P1 : ÉTABLIE.\n";
    exit(0);
}

echo "Console quotidienne P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
