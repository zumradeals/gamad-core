<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran d'administration du registre des produits (CAP-CORE-011).
 *
 * La console doit permettre d'inscrire, activer, suspendre et retirer un
 * produit sans ouvrir de chemin parallèle au cas d'usage gouverné de l'API.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/produits_console_p1.php
 */

use App\Application\Produits\AccesProduits;
use App\Http\Controllers\ProduitConsoleController;
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
$temp = sys_get_temp_dir().'/gamad-produits-console-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'journal' => $temp.'-journal.sqlite',
    'produits' => $temp.'-produits.sqlite',
    'sources' => $temp.'-sources.sqlite',
    'politiques' => $temp.'-politiques.sqlite',
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('p', 32)),
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
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
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
$identiteProduit = (string) $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE',
    'type' => 'produit',
    'libelle' => 'Console P1 Produit',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-CONSOLE-P1',
    'source' => 'épreuve console CAP-CORE-011',
    'preuve' => 'EVT-CONSOLE-P1-PRD-001',
])['reference'];

$ctr16 = new Ctr16($magasinAcces);
$secretAutorite = 'Secret-Console-Produits-1!';
$ctr16->inscrireAuthentificateur(PolitiqueInscription::AUTORITE_INSCRIPTION, $secretAutorite);
$sessionAutorite = (string) $ctr16->etablirSession(
    PolitiqueInscription::AUTORITE_INSCRIPTION,
    $secretAutorite,
)['session'];

$app = require $application.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
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

$controleur = $app->make(ProduitConsoleController::class);
$acces = $app->make(AccesProduits::class);
$REF = 'PRD-CONSOLE-P1-001';

echo "INTÉGRATION — CONSOLE DES PRODUITS P1 (CAP-CORE-011)\n\n";

// 1 — la liste se rend, vide, sans erreur de vue.
$listeVide = $controleur->index($requete('/produits'))->render();
$verifier(
    str_contains($listeVide, 'Inscrire un produit') && ! str_contains($listeVide, '<pre>'),
    'la liste des produits se rend sans erreur avant toute inscription',
);

// 2 — le formulaire d'inscription se rend et porte la politique résolue.
$formulaire = $controleur->create($requete('/produits/nouveau'))->render();
$verifier(
    str_contains($formulaire, 'Nouveau produit') && str_contains($formulaire, 'POL-PRODUITS-V1'),
    'le formulaire d’inscription affiche la politique technique résolue',
);

// 3 — inscription depuis la console : même cas d'usage gouverné que l'API.
$reponseStore = $controleur->store($requete('/produits', 'POST', [
    'reference' => $REF,
    'identite_reference' => $identiteProduit,
    'nom_canonique' => 'Console P1 Produit',
    'nom_affichage' => 'Console P1 Produit',
    'type_produit' => 'APPLICATION_INTERNE',
    'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
]), $acces);
$verifier(
    str_ends_with($reponseStore->getTargetUrl(), "/produits/{$REF}") && session('succes') !== null,
    'l’inscription depuis la console redirige vers la fiche avec confirmation',
);

// 4 — la fiche affiche l'état PREPARATION et les actions de cycle.
$fichePreparation = $controleur->show($requete("/produits/{$REF}"), $REF)->render();
$verifier(
    str_contains($fichePreparation, 'PREPARATION') && str_contains($fichePreparation, 'Activer'),
    'la fiche en PREPARATION propose l’activation',
);

// 5 — déclaration d'un environnement de production HTTPS.
$controleur->declarerEnvironnement($requete("/produits/{$REF}/environnements", 'POST', [
    'environnement' => 'PRODUCTION',
    'api_base_url' => 'https://console-p1.example/api',
    'audience_federation' => $REF,
]), $acces, $REF);

// 6 — activation depuis la console.
$controleur->activer($requete("/produits/{$REF}/activation", 'POST'), $acces, $REF);
$ficheActive = $controleur->show($requete("/produits/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheActive, 'ACTIF') && str_contains($ficheActive, 'console-p1.example'),
    'l’activation et l’environnement déclaré apparaissent sur la fiche',
);

// 7 — un tiers non autorité ne voit pas les actions de gouvernance.
$ficheTiers = $controleur->show($requete("/produits/{$REF}", acteur: $identiteProduit), $REF)->render();
$verifier(
    ! str_contains($ficheTiers, 'name="federation_autorisee"'),
    'un acteur non autorité ne voit pas les formulaires de gouvernance',
);

// 8 — suspension : immédiatement visible sur la fiche, historique conservé.
$controleur->suspendre($requete("/produits/{$REF}/suspension", 'POST'), $acces, $REF);
$ficheSuspendue = $controleur->show($requete("/produits/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheSuspendue, 'SUSPENDU') && str_contains($ficheSuspendue, 'PREPARATION'),
    'la suspension apparaît sur la fiche et l’historique reste lisible',
);

// 9 — retrait : irréversible, la fiche reste consultable.
$controleur->activer($requete("/produits/{$REF}/activation", 'POST'), $acces, $REF);
$controleur->retirer($requete("/produits/{$REF}/retrait", 'POST'), $acces, $REF);
$ficheRetiree = $controleur->show($requete("/produits/{$REF}"), $REF)->render();
$reponseReinscription = $acces->inscrire([
    'reference' => $REF,
    'identite_reference' => $identiteProduit,
    'nom_canonique' => 'Doublon',
    'nom_affichage' => 'Doublon',
    'type_produit' => 'APPLICATION_INTERNE',
    'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
], PolitiqueInscription::AUTORITE_INSCRIPTION);
$verifier(
    str_contains($ficheRetiree, 'RETIRE')
        && $reponseReinscription['statut'] === 409,
    'le retrait est irréversible ; la référence retirée reste consultable et jamais réutilisable',
);

// 10 — la liste distingue les états visibles à l'autorité.
$listeFinale = $controleur->index($requete('/produits'))->render();
$verifier(
    str_contains($listeFinale, 'RETIRE') && str_contains($listeFinale, 'Console P1 Produit'),
    'la liste reflète l’état réel du registre, y compris un produit retiré',
);

echo "\n";
if ($echecs === 0) {
    echo "Console des produits P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Console des produits P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
