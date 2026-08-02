<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran d'administration du registre des politiques
 * (CAP-CORE-007).
 *
 * La console doit permettre d'inscrire une politique, créer une version,
 * ajouter des règles, la soumettre, la simuler, l'activer, la suspendre et
 * retirer la politique — sans ouvrir de chemin parallèle au cas d'usage
 * gouverné de l'API. `POL-POLITIQUES-V1` gouverne cet écran lui-même.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/politiques_console_p1.php
 */

use App\Application\Politiques\AccesPolitiques;
use App\Http\Controllers\PolitiqueConsoleController;
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
$temp = sys_get_temp_dir().'/gamad-politiques-console-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'journal' => $temp.'-journal.sqlite',
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

$ctr01 = new Ctr01($index, $registreIdentites);
$identiteTiers = (string) $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE',
    'type' => 'personne',
    'libelle' => 'Console P1 Politiques Tiers',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-CONSOLE-P1',
    'source' => 'épreuve console CAP-CORE-007',
    'preuve' => 'EVT-CONSOLE-P1-POL-001',
])['reference'];

$ctr16 = new Ctr16($magasinAcces);
$secretAutorite = 'Secret-Console-Politiques-1!';
$ctr16->inscrireAuthentificateur(PolitiqueInscription::AUTORITE_INSCRIPTION, $secretAutorite);
$sessionAutorite = (string) $ctr16->etablirSession(
    PolitiqueInscription::AUTORITE_INSCRIPTION,
    $secretAutorite,
)['session'];

$app = require $application.'/bootstrap/app.php';
// Bootstrappe les huit politiques déjà exploitées ET `POL-POLITIQUES-V1`,
// sans laquelle cette console elle-même resterait fermée à 403.
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

$controleur = $app->make(PolitiqueConsoleController::class);
$acces = $app->make(AccesPolitiques::class);
$REF = 'POL-CONSOLE-P1-001';

echo "INTÉGRATION — CONSOLE DES POLITIQUES P1 (CAP-CORE-007)\n\n";

// 1 — la liste se rend et porte déjà les neuf politiques (huit reprises et
// l'auto-gouvernance), sans erreur de vue.
$liste = $controleur->index($requete('/politiques'))->render();
$verifier(
    str_contains($liste, 'Inscrire une politique')
        && str_contains($liste, 'POL-SOURCES-V1')
        && str_contains($liste, 'POL-POLITIQUES-V1')
        && ! str_contains($liste, '<pre>'),
    'la liste des politiques se rend, sans erreur, avec les politiques déjà actives',
);

// 2 — le formulaire d'inscription se rend, l'inscription est ouverte pour
// l'autorité (POL-POLITIQUES-V1 le lui permet).
$formulaire = $controleur->create($requete('/politiques/nouvelle'))->render();
$verifier(
    str_contains($formulaire, 'Nouvelle politique')
        && str_contains($formulaire, 'POL-POLITIQUES-V1')
        && str_contains($formulaire, 'Prêt à inscrire'),
    'le formulaire d’inscription affiche la politique d’auto-gouvernance résolue, ouverte pour l’autorité',
);

// 3 — inscription depuis la console : même cas d'usage gouverné que l'API.
$reponseStore = $controleur->store($requete('/politiques', 'POST', [
    'reference' => $REF,
    'libelle' => 'Console P1 Politique',
    'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'source_reference' => 'épreuve console CAP-CORE-007',
]), $acces);
$verifier(
    str_ends_with($reponseStore->getTargetUrl(), "/politiques/{$REF}") && session('succes') !== null,
    'l’inscription depuis la console redirige vers la fiche avec confirmation',
);

// 4 — la fiche affiche l'absence de version active et propose d'en créer une.
$ficheSansVersion = $controleur->show($requete("/politiques/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheSansVersion, 'Aucune version active') && str_contains($ficheSansVersion, 'Créer en BROUILLON'),
    'la fiche sans version active propose la création d’une version',
);

// 5 — création de version, ajout d'une règle, depuis la console.
$controleur->creerVersion($requete("/politiques/{$REF}/versions", 'POST', [
    'version' => '1.0.0',
]), $acces, $REF);
$ficheVersion = $controleur->versionShow($requete("/politiques/{$REF}/versions/1.0.0"), $REF, '1.0.0')->render();
$verifier(
    str_contains($ficheVersion, 'BROUILLON') && str_contains($ficheVersion, 'Ajouter une règle'),
    'une version créée est en BROUILLON et propose d’ajouter une règle',
);

$controleur->ajouterRegle($requete("/politiques/{$REF}/versions/1.0.0/regles", 'POST', [
    'effet' => 'PERMET',
    'action_reference' => 'agir sous la politique console p1',
    'sujet_reference' => $identiteTiers,
    'motif' => 'le tiers peut agir sous cette politique de test console',
]), $acces, $REF, '1.0.0');
$ficheAvecRegle = $controleur->versionShow($requete("/politiques/{$REF}/versions/1.0.0"), $REF, '1.0.0')->render();
$verifier(
    str_contains($ficheAvecRegle, 'agir sous la politique console p1') && str_contains($ficheAvecRegle, 'Règles (1)'),
    'la règle ajoutée apparaît sur la fiche de version',
);

// 6 — un tiers non autorité, ni propriétaire, n'accède pas à la fiche de
// version : `versionShow` la ferme à 404, plus strictement que la fiche de
// politique elle-même.
$ficheVersionTiersRefusee = false;
try {
    $controleur->versionShow(
        $requete("/politiques/{$REF}/versions/1.0.0", acteur: $identiteTiers),
        $REF,
        '1.0.0',
    );
} catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
    $ficheVersionTiersRefusee = true;
}
$verifier(
    $ficheVersionTiersRefusee,
    'un acteur ni autorité ni propriétaire n’accède pas à la fiche de version',
);

// 7 — soumission : la version devient immuable.
$controleur->soumettre($requete("/politiques/{$REF}/versions/1.0.0/soumission", 'POST'), $acces, $REF, '1.0.0');
$ficheEnValidation = $controleur->versionShow($requete("/politiques/{$REF}/versions/1.0.0"), $REF, '1.0.0')->render();
$verifier(
    str_contains($ficheEnValidation, 'EN_VALIDATION')
        && str_contains($ficheEnValidation, 'Lancer la simulation')
        && ! str_contains($ficheEnValidation, 'Ajouter une règle'),
    'une version soumise passe en EN_VALIDATION, immuable, et propose la simulation',
);

// 8 — simulation obligatoire, puis activation.
$controleur->simuler($requete("/politiques/{$REF}/versions/1.0.0/simulation", 'POST', [
    'jeu_reference' => 'CONSOLE-P1',
    'sujet' => [$identiteTiers],
    'action' => ['agir sous la politique console p1'],
    'attendu' => ['PERMIS'],
]), $acces, $REF, '1.0.0');
$controleur->activer($requete("/politiques/{$REF}/versions/1.0.0/activation", 'POST'), $acces, $REF, '1.0.0');
$ficheActive = $controleur->show($requete("/politiques/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheActive, 'Active — 1.0.0'),
    'l’activation depuis la console apparaît immédiatement sur la fiche de la politique',
);

// 9 — la règle activée gouverne réellement une décision CTR-03 pour le tiers.
$decisionTiers = (new \Gamad\RegistreAutorisation\Ctr03(
    \Gamad\RegistrePolitiques\Magasin::ouvrir(),
))->autoriser($identiteTiers, 'agir sous la politique console p1');
$verifier(
    $decisionTiers['decision'] === 'PERMIS' && $decisionTiers['politique'] === $REF,
    'une version activée par la console gouverne immédiatement une vraie décision CTR-03',
);

// 10 — suspension : immédiatement visible, ferme la permission.
$controleur->suspendre($requete("/politiques/{$REF}/versions/1.0.0/suspension", 'POST'), $acces, $REF, '1.0.0');
$ficheSuspendue = $controleur->show($requete("/politiques/{$REF}"), $REF)->render();
$decisionApresSuspension = (new \Gamad\RegistreAutorisation\Ctr03(
    \Gamad\RegistrePolitiques\Magasin::ouvrir(),
))->autoriser($identiteTiers, 'agir sous la politique console p1');
$verifier(
    str_contains($ficheSuspendue, 'Aucune version active')
        && $decisionApresSuspension['decision'] === 'REFUSÉ',
    'la suspension retire immédiatement la politique de l’usage',
);

// 11 — retrait : irréversible, la fiche reste consultable.
$controleur->creerVersion($requete("/politiques/{$REF}/versions", 'POST', ['version' => '2.0.0']), $acces, $REF);
$controleur->ajouterRegle($requete("/politiques/{$REF}/versions/2.0.0/regles", 'POST', [
    'effet' => 'PERMET', 'action_reference' => 'agir sous la politique console p1 v2', 'motif' => 'version 2',
]), $acces, $REF, '2.0.0');
$controleur->soumettre($requete("/politiques/{$REF}/versions/2.0.0/soumission", 'POST'), $acces, $REF, '2.0.0');
$controleur->simuler($requete("/politiques/{$REF}/versions/2.0.0/simulation", 'POST', [
    'jeu_reference' => 'CONSOLE-P1-V2',
    'sujet' => [PolitiqueInscription::AUTORITE_INSCRIPTION],
    'action' => ['agir sous la politique console p1 v2'],
    'attendu' => ['PERMIS'],
]), $acces, $REF, '2.0.0');
$controleur->activer($requete("/politiques/{$REF}/versions/2.0.0/activation", 'POST'), $acces, $REF, '2.0.0');
$controleur->retirer($requete("/politiques/{$REF}/retrait", 'POST'), $acces, $REF);
$ficheRetiree = $controleur->show($requete("/politiques/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheRetiree, 'Aucune version active'),
    'le retrait est irréversible ; la fiche reste consultable, sans version active',
);

// 12 — la liste distingue les états visibles à l'autorité.
$listeFinale = $controleur->index($requete('/politiques'))->render();
$verifier(
    str_contains($listeFinale, 'Console P1 Politique'),
    'la liste reflète l’état réel du registre, y compris une politique retirée',
);

echo "\n";
if ($echecs === 0) {
    echo "Console des politiques P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Console des politiques P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
