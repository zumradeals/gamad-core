<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran d'administration du registre des realms (CAP-CORE-012).
 *
 * La console doit permettre d'inscrire, activer, borner par périmètre et
 * relier un realm sans ouvrir de chemin parallèle au cas d'usage gouverné de
 * l'API.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/realms_console_p1.php
 */

use App\Application\Realms\AccesRealms;
use App\Http\Controllers\RealmConsoleController;
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
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-realms-console-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'journal' => $temp.'-journal.sqlite',
    'organisations' => $temp.'-organisations.sqlite',
    'produits' => $temp.'-produits.sqlite',
    'contrats' => $temp.'-contrats.sqlite',
    'vocabulaire' => $temp.'-vocabulaire.sqlite',
    'politiques' => $temp.'-politiques.sqlite',
    'realms' => $temp.'-realms.sqlite',
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
    'ORGANIZATION_REGISTRY_URL' => '',
    'ORGANIZATION_REGISTRY_PATH' => $fichiers['organisations'],
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'CONTRACT_REGISTRY_URL' => '',
    'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
    'VOCABULARY_REGISTRY_URL' => '',
    'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
    'REALM_REGISTRY_URL' => '',
    'REALM_REGISTRY_PATH' => $fichiers['realms'],
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
ProduitsMagasin::connecter();
ContratsMagasin::connecter();
VocabulaireMagasin::connecter();
RealmsMagasin::connecter();

$ctr01 = new Ctr01($index, $registreIdentites);

$ctr16 = new Ctr16($magasinAcces);
$secretAutorite = 'Secret-Console-Realms-1!';
$ctr16->inscrireAuthentificateur(PolitiqueInscription::AUTORITE_INSCRIPTION, $secretAutorite);
$sessionAutorite = (string) $ctr16->etablirSession(
    PolitiqueInscription::AUTORITE_INSCRIPTION,
    $secretAutorite,
)['session'];

$app = require $application.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:organisations:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:produits:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:contrats:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:vocabulaire:bootstrap');
// `core:realms:bootstrap` reprend automatiquement toute identité `realm`
// déjà connue de l'index (fiche §27) : l'identité propre à cette épreuve est
// donc créée APRÈS ce bootstrap, pour rester distincte de ce mécanisme de
// reprise et tester l'inscription gouvernée elle-même depuis la console.
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:realms:bootstrap');
$app->make(Kernel::class)->bootstrap();

$identiteRealm = (string) $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE',
    'type' => 'realm',
    'libelle' => 'Console P1 Realm',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-CONSOLE-P1',
    'source' => 'épreuve console CAP-CORE-012',
    'preuve' => 'EVT-CONSOLE-P1-RLM-001',
])['reference'];

$identiteOrganisation = (string) $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE',
    'type' => 'organisation',
    'libelle' => 'Console P1 Organisation Realms',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-CONSOLE-P1',
    'source' => 'épreuve console CAP-CORE-012',
    'preuve' => 'EVT-CONSOLE-P1-ORG-001',
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

$controleur = $app->make(RealmConsoleController::class);
$acces = $app->make(AccesRealms::class);

echo "INTÉGRATION — CONSOLE DES REALMS P1 (CAP-CORE-012)\n\n";

// 1 — la liste se rend, sans erreur de vue.
$listeVide = $controleur->index($requete('/realms'))->render();
$verifier(
    str_contains($listeVide, 'Inscrire un realm') && ! str_contains($listeVide, '<pre>'),
    'la liste des realms se rend sans erreur avant toute inscription propre à cette épreuve',
);

// 2 — le formulaire d'inscription se rend et porte la politique résolue.
$formulaire = $controleur->create($requete('/realms/nouveau'))->render();
$verifier(
    str_contains($formulaire, 'Nouveau realm') && str_contains($formulaire, 'POL-REALMS-V1'),
    'le formulaire d’inscription affiche la politique technique résolue',
);

// 3 — inscription depuis la console : même cas d'usage gouverné que l'API.
$reponseStore = $controleur->store($requete('/realms', 'POST', [
    'identite_reference' => $identiteRealm,
    'code_canonique' => 'RLM-CONSOLE-P1',
    'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Console P1 Realm',
    'classification_reference' => 'INTERNE',
]), $acces);
$cible = $reponseStore->getTargetUrl();
$RLM = substr($cible, strrpos($cible, '/') + 1);
$verifier(
    str_contains($cible, '/realms/') && session('succes') !== null && $RLM !== '',
    'l’inscription depuis la console redirige vers la fiche avec confirmation',
);

// 4 — la fiche affiche l'état PREPARATION et les actions de cycle.
$fichePreparation = $controleur->show($requete("/realms/{$RLM}"), $RLM)->render();
$verifier(
    str_contains($fichePreparation, 'PREPARATION') && str_contains($fichePreparation, 'Activer'),
    'la fiche en PREPARATION propose l’activation',
);

// 5 — organisation rattachée, périmètre déclaré, puis activation.
$controleurOrg = $app->make(\App\Http\Controllers\OrganisationConsoleController::class);
$accesOrg = $app->make(\App\Application\Organisations\AccesOrganisations::class);
$reponseOrg = $controleurOrg->store($requete('/organisations', 'POST', [
    'identite_reference' => $identiteOrganisation,
    'type_organisation_reference' => 'INSTITUTION',
    'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'denomination_officielle' => 'Console P1 Organisation Realms',
    'classification_reference' => 'INTERNE',
]), $accesOrg);
$cibleOrg = $reponseOrg->getTargetUrl();
$ORG = substr($cibleOrg, strrpos($cibleOrg, '/') + 1);
$controleurOrg->activer($requete("/organisations/{$ORG}/activation", 'POST'), $accesOrg, $ORG);

$controleur->declarerPerimetre($requete("/realms/{$RLM}/perimetres", 'POST', [
    'dimension_reference' => 'PAYS', 'valeur_reference' => 'CONSOLE-P1',
]), $acces, $RLM);
$controleur->rattacherOrganisation($requete("/realms/{$RLM}/organisations", 'POST', [
    'organisation_reference' => $ORG, 'role_reference' => 'RESPONSABLE', 'classification_reference' => 'INTERNE',
]), $acces, $RLM);
$controleur->activer($requete("/realms/{$RLM}/activation", 'POST'), $acces, $RLM);
$ficheActive = $controleur->show($requete("/realms/{$RLM}"), $RLM)->render();
$verifier(
    str_contains($ficheActive, 'ACTIF')
        && str_contains($ficheActive, 'CONSOLE-P1')
        && str_contains($ficheActive, $ORG),
    'le périmètre, l’organisation rattachée et l’activation apparaissent sur la fiche',
);

// 6 — un tiers non autorité ne voit pas les formulaires de gouvernance.
$ficheTiers = $controleur->show($requete("/realms/{$RLM}", acteur: $identiteRealm), $RLM)->render();
$verifier(
    ! str_contains($ficheTiers, 'name="nom_affichage"'),
    'un acteur non autorité ne voit pas les formulaires d’inscription/gouvernance',
);

// 7 — suspension : immédiatement visible sur la fiche, historique conservé.
$controleur->suspendre($requete("/realms/{$RLM}/suspension", 'POST'), $acces, $RLM);
$ficheSuspendue = $controleur->show($requete("/realms/{$RLM}"), $RLM)->render();
$verifier(
    str_contains($ficheSuspendue, 'SUSPENDU') && str_contains($ficheSuspendue, 'PREPARATION'),
    'la suspension apparaît sur la fiche et l’historique reste lisible',
);

// 8 — fermeture puis retrait : irréversible, la fiche reste consultable.
$controleur->fermer($requete("/realms/{$RLM}/fermeture", 'POST'), $acces, $RLM);
$controleur->retirer($requete("/realms/{$RLM}/retrait", 'POST', ['motif_reference' => 'RETRAIT_CONSOLE_P1']), $acces, $RLM);
$ficheRetiree = $controleur->show($requete("/realms/{$RLM}"), $RLM)->render();
$verifier(
    str_contains($ficheRetiree, 'RETIRE'),
    'le retrait est irréversible ; la fiche reste consultable',
);

// 9 — la liste reflète l'état réel du registre, y compris un realm retiré.
$listeFinale = $controleur->index($requete('/realms'))->render();
$verifier(
    str_contains($listeFinale, 'RETIRE') && str_contains($listeFinale, 'Console P1 Realm'),
    'la liste reflète l’état réel du registre, y compris un realm retiré',
);

echo "\n";
if ($echecs === 0) {
    echo "Console des realms P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Console des realms P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
