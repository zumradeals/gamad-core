<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran d'administration du registre des sources (CAP-CORE-006).
 *
 * La console doit permettre d'inscrire, activer, suspendre et retirer une
 * source sans ouvrir de chemin parallèle au cas d'usage gouverné de l'API.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/sources_console_p1.php
 */

use App\Application\Sources\AccesSources;
use App\Http\Controllers\SourceConsoleController;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreFederation\SchemaFederation;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-sources-console-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'journal' => $temp.'-journal.sqlite',
    'produits' => $temp.'-produits.sqlite',
    'sources' => $temp.'-sources.sqlite',
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('s', 32)),
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
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'SOURCE_REGISTRY_URL' => '',
    'SOURCE_REGISTRY_PATH' => $fichiers['sources'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$magasinAcces = AccesMagasin::connecter();
SchemaFederation::migrer($magasinAcces);
$registreIdentites = IdentiteMagasin::connecter();
JournalMagasin::connecter();
ProduitsMagasin::connecter();

$ctr01 = new Ctr01($index, $registreIdentites);
$identiteTiers = (string) $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE',
    'type' => 'produit',
    'libelle' => 'Console P1 Source Tiers',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-CONSOLE-P1',
    'source' => 'épreuve console CAP-CORE-006',
    'preuve' => 'EVT-CONSOLE-P1-SRC-001',
])['reference'];

$ctr16 = new Ctr16($magasinAcces);
$secretAutorite = 'Secret-Console-Sources-1!';
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

$controleur = $app->make(SourceConsoleController::class);
$acces = $app->make(AccesSources::class);
$REF = 'SRC-CONSOLE-P1-001';

echo "INTÉGRATION — CONSOLE DES SOURCES P1 (CAP-CORE-006)\n\n";

// 1 — la liste se rend, vide, sans erreur de vue.
$listeVide = $controleur->index($requete('/registre-sources'))->render();
$verifier(
    str_contains($listeVide, 'Inscrire une source') && ! str_contains($listeVide, '<pre>'),
    'la liste des sources se rend sans erreur avant toute inscription',
);

// 2 — le formulaire d'inscription se rend et porte la politique résolue.
$formulaire = $controleur->create($requete('/registre-sources/nouvelle'))->render();
$verifier(
    str_contains($formulaire, 'Nouvelle source') && str_contains($formulaire, 'POL-SOURCES-V1'),
    'le formulaire d’inscription affiche la politique technique résolue',
);

// 3 — inscription depuis la console : même cas d'usage gouverné que l'API.
$reponseStore = $controleur->store($requete('/registre-sources', 'POST', [
    'reference' => $REF,
    'nom_canonique' => 'Console P1 Source',
    'nom_affichage' => 'Console P1 Source',
    'type_source' => 'SERVICE_CORE',
    'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
]), $acces);
$verifier(
    str_ends_with($reponseStore->getTargetUrl(), "/registre-sources/{$REF}") && session('succes') !== null,
    'l’inscription depuis la console redirige vers la fiche avec confirmation',
);

// 4 — la fiche affiche l'état PREPARATION et les actions de cycle.
$fichePreparation = $controleur->show($requete("/registre-sources/{$REF}"), $REF)->render();
$verifier(
    str_contains($fichePreparation, 'PREPARATION') && str_contains($fichePreparation, 'Activer'),
    'la fiche en PREPARATION propose l’activation',
);

// 5 — activation depuis la console.
$controleur->activer($requete("/registre-sources/{$REF}/activation", 'POST'), $acces, $REF);
$ficheActive = $controleur->show($requete("/registre-sources/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheActive, 'ACTIVE'),
    'l’activation apparaît sur la fiche',
);

// 6 — une finalité se déclare depuis la console et apparaît sur la fiche.
$controleur->declarerFinalite($requete("/registre-sources/{$REF}/finalites", 'POST', [
    'finalite_reference' => 'FIN-CONSOLE-P1',
    'date_debut' => '2026-01-01',
]), $acces, $REF);
$ficheAvecFinalite = $controleur->show($requete("/registre-sources/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheAvecFinalite, 'FIN-CONSOLE-P1'),
    'la finalité déclarée apparaît sur la fiche',
);

// 7 — un tiers non autorité ne voit pas les actions de gouvernance.
$ficheTiers = $controleur->show($requete("/registre-sources/{$REF}", acteur: $identiteTiers), $REF)->render();
$verifier(
    ! str_contains($ficheTiers, 'Enregistrer (nouvelle révision)'),
    'un acteur non autorité ne voit pas les formulaires de gouvernance',
);

// 8 — suspension : immédiatement visible sur la fiche, historique conservé.
$controleur->suspendre($requete("/registre-sources/{$REF}/suspension", 'POST'), $acces, $REF);
$ficheSuspendue = $controleur->show($requete("/registre-sources/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheSuspendue, 'SUSPENDUE') && str_contains($ficheSuspendue, 'PREPARATION'),
    'la suspension apparaît sur la fiche et l’historique reste lisible',
);

// 9 — retrait : irréversible, la fiche reste consultable.
$controleur->activer($requete("/registre-sources/{$REF}/activation", 'POST'), $acces, $REF);
$controleur->retirer($requete("/registre-sources/{$REF}/retrait", 'POST'), $acces, $REF);
$ficheRetiree = $controleur->show($requete("/registre-sources/{$REF}"), $REF)->render();
$reponseReinscription = $acces->inscrire([
    'reference' => $REF,
    'nom_canonique' => 'Doublon',
    'nom_affichage' => 'Doublon',
    'type_source' => 'SERVICE_CORE',
    'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
], PolitiqueInscription::AUTORITE_INSCRIPTION);
$verifier(
    str_contains($ficheRetiree, 'RETIREE')
        && $reponseReinscription['statut'] === 409,
    'le retrait est irréversible ; la référence retirée reste consultable et jamais réutilisable',
);

// 10 — la liste distingue les états visibles à l'autorité.
$listeFinale = $controleur->index($requete('/registre-sources'))->render();
$verifier(
    str_contains($listeFinale, 'RETIREE') && str_contains($listeFinale, 'Console P1 Source'),
    'la liste reflète l’état réel du registre, y compris une source retirée',
);

echo "\n";
if ($echecs === 0) {
    echo "Console des sources P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Console des sources P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
