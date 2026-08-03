<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran d'administration du registre des organisations
 * (CAP-CORE-002).
 *
 * La console doit permettre d'inscrire, activer, structurer et affilier une
 * organisation sans ouvrir de chemin parallèle au cas d'usage gouverné de
 * l'API.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/organisations_console_p1.php
 */

use App\Application\Organisations\AccesOrganisations;
use App\Http\Controllers\OrganisationConsoleController;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreFederation\SchemaFederation;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-organisations-console-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'journal' => $temp.'-journal.sqlite',
    'organisations' => $temp.'-organisations.sqlite',
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('o', 32)),
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
    'ORGANIZATION_REGISTRY_URL' => '',
    'ORGANIZATION_REGISTRY_PATH' => $fichiers['organisations'],
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
OrganisationsMagasin::connecter();

$ctr01 = new Ctr01($index, $registreIdentites);

$ctr16 = new Ctr16($magasinAcces);
$secretAutorite = 'Secret-Console-Organisations-1!';
$ctr16->inscrireAuthentificateur(PolitiqueInscription::AUTORITE_INSCRIPTION, $secretAutorite);
$sessionAutorite = (string) $ctr16->etablirSession(
    PolitiqueInscription::AUTORITE_INSCRIPTION,
    $secretAutorite,
)['session'];

$app = require $application.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
// `core:organisations:bootstrap` reprend automatiquement toute identité
// `organisation` déjà connue de l'index (fiche §13.1) : les identités propres
// à cette épreuve sont donc créées APRÈS ce bootstrap, pour rester
// distinctes de ce mécanisme de reprise et tester l'inscription gouvernée
// elle-même depuis la console.
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:organisations:bootstrap');
$app->make(Kernel::class)->bootstrap();

$identiteOrganisation = (string) $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE',
    'type' => 'organisation',
    'libelle' => 'Console P1 Organisation',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-CONSOLE-P1',
    'source' => 'épreuve console CAP-CORE-002',
    'preuve' => 'EVT-CONSOLE-P1-ORG-001',
])['reference'];
$identiteMembre = (string) $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE',
    'type' => 'personne',
    'libelle' => 'Membre Console P1',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-CONSOLE-P1',
    'source' => 'épreuve console CAP-CORE-002',
    'preuve' => 'EVT-CONSOLE-P1-MBR-001',
])['reference'];
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

$controleur = $app->make(OrganisationConsoleController::class);
$acces = $app->make(AccesOrganisations::class);

echo "INTÉGRATION — CONSOLE DES ORGANISATIONS P1 (CAP-CORE-002)\n\n";

// 1 — la liste se rend, vide, sans erreur de vue.
$listeVide = $controleur->index($requete('/organisations'))->render();
$verifier(
    str_contains($listeVide, 'Inscrire une organisation') && ! str_contains($listeVide, '<pre>'),
    'la liste des organisations se rend sans erreur avant toute inscription',
);

// 2 — le formulaire d'inscription se rend et porte la politique résolue.
$formulaire = $controleur->create($requete('/organisations/nouveau'))->render();
$verifier(
    str_contains($formulaire, 'Nouvelle organisation') && str_contains($formulaire, 'POL-ORGANISATIONS-V1'),
    'le formulaire d’inscription affiche la politique technique résolue',
);

// 3 — inscription depuis la console : même cas d'usage gouverné que l'API.
$reponseStore = $controleur->store($requete('/organisations', 'POST', [
    'identite_reference' => $identiteOrganisation,
    'type_organisation_reference' => 'COOPERATIVE',
    'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'denomination_officielle' => 'Console P1 Organisation',
    'classification_reference' => 'INTERNE',
]), $acces);
$cible = $reponseStore->getTargetUrl();
$ORG = substr($cible, strrpos($cible, '/') + 1);
$verifier(
    str_contains($cible, '/organisations/') && session('succes') !== null && $ORG !== '',
    'l’inscription depuis la console redirige vers la fiche avec confirmation',
);

// 4 — la fiche affiche l'état PREPARATION et les actions de cycle.
$fichePreparation = $controleur->show($requete("/organisations/{$ORG}"), $ORG)->render();
$verifier(
    str_contains($fichePreparation, 'PREPARATION') && str_contains($fichePreparation, 'Activer'),
    'la fiche en PREPARATION propose l’activation',
);

// 5 — activation, création d'unité, proposition d'affiliation.
$controleur->activer($requete("/organisations/{$ORG}/activation", 'POST'), $acces, $ORG);
$controleur->creerUnite($requete("/organisations/{$ORG}/unites", 'POST', [
    'type_unite_reference' => 'SIEGE', 'nom' => 'Siège Console P1', 'classification_reference' => 'INTERNE',
]), $acces, $ORG);
$reponseAffiliation = $controleur->proposerAffiliation($requete("/organisations/{$ORG}/affiliations", 'POST', [
    'identite_reference' => $identiteMembre, 'type_affiliation_reference' => 'MEMBRE',
    'niveau_assurance_reference' => 'A1', 'classification_reference' => 'INTERNE',
]), $acces, $ORG);
$ficheActive = $controleur->show($requete("/organisations/{$ORG}"), $ORG)->render();
$verifier(
    str_contains($ficheActive, 'ACTIVE')
        && str_contains($ficheActive, 'Siège Console P1')
        && str_contains($ficheActive, 'PROPOSEE'),
    'l’activation, l’unité créée et l’affiliation proposée apparaissent sur la fiche',
);

// 6 — un tiers non autorité ne voit pas les formulaires de gouvernance.
$ficheTiers = $controleur->show($requete("/organisations/{$ORG}", acteur: $identiteOrganisation), $ORG)->render();
$verifier(
    ! str_contains($ficheTiers, 'name="denomination_officielle"'),
    'un acteur non autorité ne voit pas les formulaires d’inscription/gouvernance',
);

// 7 — suspension : immédiatement visible sur la fiche, historique conservé.
$controleur->suspendre($requete("/organisations/{$ORG}/suspension", 'POST'), $acces, $ORG);
$ficheSuspendue = $controleur->show($requete("/organisations/{$ORG}"), $ORG)->render();
$verifier(
    str_contains($ficheSuspendue, 'SUSPENDUE') && str_contains($ficheSuspendue, 'PREPARATION'),
    'la suspension apparaît sur la fiche et l’historique reste lisible',
);

// 8 — retrait : irréversible, la fiche reste consultable.
$controleur->activer($requete("/organisations/{$ORG}/activation", 'POST'), $acces, $ORG);
$controleur->retirer($requete("/organisations/{$ORG}/retrait", 'POST', ['motif' => 'retrait console P1']), $acces, $ORG);
$ficheRetiree = $controleur->show($requete("/organisations/{$ORG}"), $ORG)->render();
$verifier(
    str_contains($ficheRetiree, 'RETIREE'),
    'le retrait est irréversible ; la fiche reste consultable',
);

// 9 — la liste reflète l'état réel du registre, y compris une organisation retirée.
$listeFinale = $controleur->index($requete('/organisations'))->render();
$verifier(
    str_contains($listeFinale, 'RETIREE') && str_contains($listeFinale, 'Console P1 Organisation'),
    'la liste reflète l’état réel du registre, y compris une organisation retirée',
);

echo "\n";
if ($echecs === 0) {
    echo "Console des organisations P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Console des organisations P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
