<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran d'administration des satellites (CAP-CORE-022).
 *
 * La console doit permettre d'administrer la fédération sans connaissance
 * technique, sans ouvrir de chemin parallèle au cas d'usage gouverné, et sans
 * jamais montrer à un satellite les porteurs d'un autre satellite.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/federation_console_p1.php
 */

use App\Http\Controllers\SatelliteConsoleController;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreFederation\SchemaFederation;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-federation-console-'.getmypid();
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('c', 32)),
    'APP_URL' => 'https://console.test',
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

$DRIVE = 'PRD-GAMAD-002';
$WASPLEX = 'PRD-GAMAD-003';

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$magasin = AccesMagasin::connecter();
SchemaFederation::migrer($magasin);
$registre = IdentiteMagasin::connecter();
JournalMagasin::connecter();

$ctr01 = new Ctr01($index, $registre);
$porteur = (string) $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE',
    'type' => 'personne',
    'libelle' => 'Aïcha la testeuse',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-CONSOLE-P1',
    'source' => 'épreuve console CAP-CORE-022',
    'preuve' => 'EVT-CONSOLE-P1-001',
])['reference'];

$ctr16 = new Ctr16($magasin);
$secretAutorite = 'Secret-Console-Federation-1!';
$ctr16->inscrireAuthentificateur(PolitiqueInscription::AUTORITE_INSCRIPTION, $secretAutorite);
$sessionAutorite = (string) $ctr16->etablirSession(
    PolitiqueInscription::AUTORITE_INSCRIPTION,
    $secretAutorite,
)['session'];

$app = require $application.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$sessionLaravel = $app->make('session')->driver();
$sessionLaravel->start();
$app->make('view')->share('errors', new ViewErrorBag);
$app->make('redirect')->setSession($sessionLaravel);

$requete = static function (
    string $uri,
    string $methode = 'GET',
    array $donnees = [],
    string $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION,
) use ($app, $sessionLaravel, $sessionAutorite): Request {
    $request = Request::create($uri, $methode, $donnees);
    $request->setLaravelSession($sessionLaravel);
    $sessionLaravel->put('gamad_session', $sessionAutorite);
    $request->attributes->set('gamad_entite', $acteur);
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

$controleur = $app->make(SatelliteConsoleController::class);
$accesSatellites = $app->make(App\Application\Federation\AccesSatellites::class);

echo "INTÉGRATION — CONSOLE DES SATELLITES P1 (CAP-CORE-022)\n\n";

// 1 — la liste distingue le produit ouvrable du partenaire non entériné.
$liste = $controleur->index($requete('/satellites'))->render();
$verifier(
    str_contains($liste, 'GAMAD Drive')
        && str_contains($liste, 'Wasplex')
        && str_contains($liste, 'Ouvrable')
        && str_contains($liste, 'Non entériné')
        && ! str_contains($liste, '<pre>'),
    'la liste des satellites est lisible et distingue l’ouvrable du non entériné',
);

// 2 — la fiche de raccordement porte les quatre informations à remettre.
$fiche = $controleur->show($requete("/satellites/{$DRIVE}"), $DRIVE)->render();
$verifier(
    str_contains($fiche, 'Fiche de raccordement')
        && str_contains($fiche, '1. Référence du produit')
        && str_contains($fiche, '2. Adresse du Core')
        && str_contains($fiche, '3. Secret de connexion')
        && str_contains($fiche, '4. Les trois portes')
        && str_contains($fiche, 'https://console.test/api/v1')
        && str_contains($fiche, "POST /produits/{$DRIVE}/verification"),
    'la fiche de raccordement donne les quatre informations et l’adresse réelle',
);

// 3 — l'état des identifiants du satellite est constaté, jamais présumé.
$avantSecret = str_contains($fiche, 'Identifiants Core à créer');
$ctr16->inscrireAuthentificateur($DRIVE, 'Secret-Satellite-Drive-1!');
$apresSecret = $controleur->show($requete("/satellites/{$DRIVE}"), $DRIVE)->render();
$verifier(
    $avantSecret
        && str_contains($apresSecret, 'Identifiants Core configurés')
        && ! str_contains($apresSecret, 'Secret-Satellite-Drive-1!'),
    'la fiche constate l’existence des identifiants sans jamais montrer le secret',
);

// 4 — ouvrir un accès depuis la console, et voir le jeton une seule fois.
$ouverture = $controleur->ouvrir(
    $requete("/satellites/{$DRIVE}/acces", 'POST', [
        'identite' => $porteur,
        'relation_type' => 'CLIENT',
    ]),
    $accesSatellites,
    $DRIVE,
);
$apresOuverture = $controleur->show($requete("/satellites/{$DRIVE}"), $DRIVE)->render();
$verifier(
    $ouverture->getStatusCode() === 302
        && str_contains((string) $ouverture->getTargetUrl(), "/satellites/{$DRIVE}")
        && str_contains((string) $sessionLaravel->get('succes'), 'Accès ouvert')
        && str_contains($apresOuverture, 'Jeton d’ouverture — affiché une seule fois')
        && str_contains($apresOuverture, 'FED-')
        && str_contains($apresOuverture, 'Aïcha la testeuse')
        && str_contains($apresOuverture, 'Client'),
    'la console ouvre l’accès et montre le jeton une seule fois',
);

// 5 — le jeton disparaît de l'écran au rechargement suivant.
$sessionLaravel->forget('jeton_federe');
$rechargement = $controleur->show($requete("/satellites/{$DRIVE}"), $DRIVE)->render();
$verifier(
    ! str_contains($rechargement, 'Jeton d’ouverture')
        && ! str_contains($rechargement, 'FED-')
        && str_contains($rechargement, '1 accès actif'),
    'le jeton n’est pas réaffiché, l’accès reste visible',
);

// 6 — répéter l'ouverture ne crée pas un second accès.
$controleur->ouvrir(
    $requete("/satellites/{$DRIVE}/acces", 'POST', [
        'identite' => $porteur,
        'relation_type' => 'CLIENT',
    ]),
    $accesSatellites,
    $DRIVE,
);
$liens = (int) $registre
    ->query("SELECT count(*) FROM relation_produit WHERE produit_reference = '{$DRIVE}'")
    ->fetchColumn();
$apresRepetition = $controleur->show($requete("/satellites/{$DRIVE}"), $DRIVE)->render();
$verifier(
    $liens === 1 && str_contains($apresRepetition, '1 accès actif'),
    'ouvrir deux fois le même accès ne crée pas un second compte',
);

// 7 — un partenaire non entériné n'offre aucun formulaire d'ouverture.
$ficheWasplex = $controleur->show($requete("/satellites/{$WASPLEX}"), $WASPLEX)->render();
$verifier(
    str_contains($ficheWasplex, 'Produit non entériné')
        && ! str_contains($ficheWasplex, 'Ouvrir l’accès</button>'),
    'un produit non entériné ne propose pas d’ouvrir un accès',
);

// 8 — isolation : un satellite ne lit pas les porteurs d'un autre satellite.
$vueEtrangere = $controleur->show(
    $requete("/satellites/{$DRIVE}", 'GET', [], $WASPLEX),
    $DRIVE,
)->render();
$verifier(
    str_contains($vueEtrangere, 'Liste non lisible depuis cette session')
        && ! str_contains($vueEtrangere, 'Aïcha la testeuse')
        && str_contains($vueEtrangere, 'Administration réservée'),
    'un satellite ne voit ni les porteurs ni l’administration d’un autre satellite',
);

// 9 — révoquer depuis la console ferme l'accès et les jetons.
$revocation = $controleur->revoquer(
    $requete("/satellites/{$DRIVE}/revocation", 'POST', ['identite' => $porteur]),
    $accesSatellites,
    $DRIVE,
);
$apresRevocation = $controleur->show($requete("/satellites/{$DRIVE}"), $DRIVE)->render();
$verifier(
    $revocation->getStatusCode() === 302
        && str_contains((string) $sessionLaravel->get('succes'), 'Accès révoqué')
        && str_contains((string) $sessionLaravel->get('succes'), 'ont été fermés')
        && str_contains($apresRevocation, 'Personne n’a encore accès à ce produit')
        && ($ctr01->resoudreIdentite($porteur)['etat'] ?? null) === 'ACTIVE',
    'la révocation depuis la console ferme l’accès sans supprimer l’identité',
);

// 10 — la console ne contourne pas le cas d'usage gouverné.
$source = (string) file_get_contents(
    dirname(__DIR__, 2).'/app/Http/Controllers/SatelliteConsoleController.php'
);
$verifier(
    str_contains($source, 'AccesSatellites $acces')
        && ! str_contains($source, 'rattacherProduit')
        && ! str_contains($source, 'cloreRelationProduit'),
    'l’écran passe par le cas d’usage gouverné et n’écrit jamais en direct',
);

echo "\n";
if ($echecs === 0) {
    echo "Console des satellites P1 : ÉTABLIE.\n";
    exit(0);
}

echo "Console des satellites P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
