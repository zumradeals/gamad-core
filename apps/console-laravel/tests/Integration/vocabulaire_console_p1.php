<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran d'administration du registre du vocabulaire canonique
 * (CAP-CORE-010).
 *
 * La console doit permettre d'inscrire un vocabulaire, créer une version, y
 * ajouter des termes, la soumettre, l'analyser, générer une projection,
 * enregistrer une conformité, l'activer, la déprécier et la retirer — sans
 * ouvrir de chemin parallèle au cas d'usage gouverné de l'API.
 * `POL-VOCABULAIRE-V1` gouverne cet écran lui-même.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/vocabulaire_console_p1.php
 */

use App\Application\Vocabulaire\AccesVocabulaire;
use App\Http\Controllers\VocabulaireConsoleController;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreFederation\SchemaFederation;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-vocabulaire-console-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'journal' => $temp.'-journal.sqlite',
    'politiques' => $temp.'-politiques.sqlite',
    'vocabulaire' => $temp.'-vocabulaire.sqlite',
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('w', 32)),
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
    'VOCABULARY_REGISTRY_URL' => '',
    'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
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
PolitiquesMagasin::connecter();

$ctr16 = new Ctr16($magasinAcces);
$secretAutorite = 'Secret-Console-Vocabulaire-1!';
$ctr16->inscrireAuthentificateur(PolitiqueInscription::AUTORITE_INSCRIPTION, $secretAutorite);
$sessionAutorite = (string) $ctr16->etablirSession(
    PolitiqueInscription::AUTORITE_INSCRIPTION,
    $secretAutorite,
)['session'];

$app = require $application.'/bootstrap/app.php';
// Bootstrappe `POL-VOCABULAIRE-V1` et les trente-trois vocabulaires déjà
// exploités. Sans `POL-VOCABULAIRE-V1`, cette console elle-même resterait
// fermée à 403.
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:vocabulaire:bootstrap');
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

$controleur = $app->make(VocabulaireConsoleController::class);
$acces = $app->make(AccesVocabulaire::class);
$REF = 'VOC-CONSOLE-P1-001';

echo "INTÉGRATION — CONSOLE DU VOCABULAIRE P1 (CAP-CORE-010)\n\n";

// 1 — la liste se rend et porte déjà les trente-trois vocabulaires repris, sans erreur.
$liste = $controleur->index($requete('/vocabulaires'))->render();
$verifier(
    str_contains($liste, 'Inscrire un vocabulaire')
        && str_contains($liste, 'VOC-GAMAD-IDENTITE-TYPE')
        && str_contains($liste, 'VOC-GAMAD-CONTRAT-TYPE-OPERATION')
        && ! str_contains($liste, '<pre>'),
    'la liste du vocabulaire se rend, sans erreur, avec les vocabulaires déjà actifs',
);

// 2 — le formulaire d'inscription se rend, ouvert pour l'autorité.
$formulaire = $controleur->create($requete('/vocabulaires/nouveau'))->render();
$verifier(
    str_contains($formulaire, 'Nouveau vocabulaire')
        && str_contains($formulaire, 'POL-VOCABULAIRE-V1')
        && str_contains($formulaire, 'Prêt à inscrire'),
    'le formulaire d’inscription affiche la politique d’auto-gouvernance résolue, ouverte pour l’autorité',
);

// 3 — inscription depuis la console : même cas d'usage gouverné que l'API.
$reponseStore = $controleur->store($requete('/vocabulaires', 'POST', [
    'reference' => $REF,
    'namespace' => 'console.p1.test',
    'nom' => 'Console P1 Vocabulaire',
    'domaine' => 'test',
    'portee' => 'CORE',
    'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'source_reference' => 'épreuve console CAP-CORE-010',
]), $acces);
$verifier(
    str_ends_with($reponseStore->getTargetUrl(), "/vocabulaires/{$REF}") && session('succes') !== null,
    'l’inscription depuis la console redirige vers la fiche avec confirmation',
);

// 4 — la fiche affiche l'absence de version active et propose d'en créer une.
$ficheSansVersion = $controleur->show($requete("/vocabulaires/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheSansVersion, 'Aucune version active') && str_contains($ficheSansVersion, 'Créer en BROUILLON'),
    'la fiche sans version active propose la création d’une version',
);

// 5 — création de version, déclaration d'un terme.
$controleur->creerVersion($requete("/vocabulaires/{$REF}/versions", 'POST', ['version' => '1.0.0']), $acces, $REF);
$controleur->ajouterTerme($requete("/vocabulaires/{$REF}/versions/1.0.0/termes", 'POST', [
    'reference' => 'TERM-CONSOLE-P1-ACTIF', 'code' => 'ACTIF', 'definition' => 'le terme est actif', 'type_semantique' => 'ETAT',
]), $acces, $REF, '1.0.0');
$ficheVersion = $controleur->versionShow($requete("/vocabulaires/{$REF}/versions/1.0.0"), $REF, '1.0.0')->render();
$verifier(
    str_contains($ficheVersion, 'BROUILLON')
        && str_contains($ficheVersion, 'ACTIF')
        && str_contains($ficheVersion, 'Soumettre à validation'),
    'une version créée en BROUILLON affiche ses termes, et propose la soumission',
);

// 6 — un tiers non autorité, ni propriétaire, n'accède pas à la fiche de
// version : `versionShow` la ferme à 404, plus strictement que la fiche.
$identiteTiers = (string) (new Ctr01($index, $registreIdentites))->inscrireIdentite([
    'canal' => 'AUTORITE', 'type' => 'personne', 'libelle' => 'Console P1 Vocabulaire Tiers',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-VOCABULAIRE-V1', 'source' => 'épreuve console CAP-CORE-010',
    'preuve' => 'EVT-CONSOLE-P1-VOC-001',
])['reference'];
$ficheVersionTiersRefusee = false;
try {
    $controleur->versionShow(
        $requete("/vocabulaires/{$REF}/versions/1.0.0", acteur: $identiteTiers),
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
$controleur->soumettre($requete("/vocabulaires/{$REF}/versions/1.0.0/soumission", 'POST'), $acces, $REF, '1.0.0');
$ficheEnValidation = $controleur->versionShow($requete("/vocabulaires/{$REF}/versions/1.0.0"), $REF, '1.0.0')->render();
$verifier(
    str_contains($ficheEnValidation, 'EN_VALIDATION')
        && str_contains($ficheEnValidation, 'Analyser la compatibilité')
        && ! str_contains($ficheEnValidation, 'Ajouter un terme'),
    'une version soumise passe en EN_VALIDATION, immuable, et propose l’analyse',
);

// 8 — analyse, projection et conformité obligatoires, puis activation.
$controleur->analyser($requete("/vocabulaires/{$REF}/versions/1.0.0/analyse", 'POST'), $acces, $REF, '1.0.0');
$controleur->genererProjection($requete("/vocabulaires/{$REF}/versions/1.0.0/projections", 'POST', [
    'type_projection' => 'JSON',
]), $acces, $REF, '1.0.0');
$controleur->enregistrerConformite($requete("/vocabulaires/{$REF}/versions/1.0.0/conformite", 'POST', [
    'resultat' => 'CONFORME', 'consommateur_reference' => 'CAP-CORE-010', 'type_consommateur' => 'CAPACITE',
]), $acces, $REF, '1.0.0');
$controleur->activer($requete("/vocabulaires/{$REF}/versions/1.0.0/activation", 'POST'), $acces, $REF, '1.0.0');
$ficheActive = $controleur->show($requete("/vocabulaires/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheActive, 'Active — 1.0.0'),
    'l’activation depuis la console apparaît immédiatement sur la fiche du vocabulaire',
);

// 9 — dépréciation, immédiatement visible.
$controleur->deprecier($requete("/vocabulaires/{$REF}/versions/1.0.0/depreciation", 'POST'), $acces, $REF, '1.0.0');
$ficheDepreciee = $controleur->show($requete("/vocabulaires/{$REF}"), $REF)->render();
$verifier(
    str_contains($ficheDepreciee, 'Aucune version active'),
    'la dépréciation retire immédiatement le vocabulaire de l’usage',
);

// 10 — retrait : irréversible, la fiche reste consultable.
$controleur->retirer($requete("/vocabulaires/{$REF}/versions/1.0.0/retrait", 'POST'), $acces, $REF, '1.0.0');
$ficheRetiree = $controleur->versionShow($requete("/vocabulaires/{$REF}/versions/1.0.0"), $REF, '1.0.0')->render();
$verifier(
    str_contains($ficheRetiree, 'RETIREE'),
    'le retrait est irréversible ; la fiche de version reste consultable',
);

// 11 — la liste reflète l'état réel du registre.
$listeFinale = $controleur->index($requete('/vocabulaires'))->render();
$verifier(
    str_contains($listeFinale, 'Console P1 Vocabulaire'),
    'la liste reflète l’état réel du registre, y compris un vocabulaire sans version active',
);

echo "\n";
if ($echecs === 0) {
    echo "Console du vocabulaire P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Console du vocabulaire P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
