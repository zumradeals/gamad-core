<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran « Mon accès » (CAP-CORE-005).
 *
 * Le Core doit pouvoir se renforcer sans ligne de commande, sans que personne
 * ne gère l'accès d'autrui, et sans que le seul mot de passe suffise à
 * attacher un facteur fort.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/acces_console_p1.php
 */

use App\Application\Acces\MoyensAcces;
use App\Http\Controllers\AccesConsoleController;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-acces-console-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
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
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';

$AUTORITE = PolitiqueInscription::AUTORITE_INSCRIPTION;

BaselineOperationnelle::standard()->reconstruire(Db::connect());
$magasin = AccesMagasin::connecter();
JournalMagasin::connecter();
$ctr16 = new Ctr16($magasin);
$ctr16->inscrireAuthentificateur($AUTORITE, 'Mot-de-passe-autorite-1!');

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
    string $acteur = null,
    string $assurance = 'AS1 — FACTEUR UNIQUE',
) use ($app, $sessionLaravel, $AUTORITE): Request {
    $request = Request::create($uri, $methode, $donnees);
    $request->setLaravelSession($sessionLaravel);
    $request->attributes->set('gamad_entite', $acteur ?? $AUTORITE);
    $request->attributes->set('gamad_assurance', $assurance);
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

$controleur = $app->make(AccesConsoleController::class);
$moyens = $app->make(MoyensAcces::class);
$afficher = static fn (Request $r): string => $controleur->index($r, $moyens)->getContent();

echo "INTÉGRATION — CONSOLE MON ACCÈS P1 (CAP-CORE-005)\n\n";

// 1 — un seul moyen d'accès : l'écran le dit, en toutes lettres.
$avant = $afficher($requete('/mon-acces'));
$verifier(
    str_contains($avant, 'Vous n’avez qu’un seul moyen d’entrer')
        && str_contains($avant, 'Aucun facteur fort')
        && str_contains($avant, 'Mot de passe')
        && ! str_contains($avant, '<pre>'),
    'l’écran nomme le risque du moyen unique au lieu de le taire',
);

// 2 — un mot de passe seul n'attache pas un facteur fort.
$sansCode = $moyens->autoriserFacteurFort($AUTORITE, $AUTORITE, 'AS1 — FACTEUR UNIQUE');
$verifier(
    $sansCode['statut'] === 422
        && ($sansCode['corps']['erreur'] ?? null) === 'AUTORISATION_INSUFFISANTE',
    'une session simple n’attache pas une passkey, la règle héritée tient',
);

// 3 — engendrer les codes de secours depuis la console.
$engendrement = $controleur->engendrerCodes($requete('/mon-acces/codes-de-secours', 'POST'), $moyens);
$codes = $sessionLaravel->get('codes_secours', []);
$ecran = $afficher($requete('/mon-acces'));
$verifier(
    $engendrement->getStatusCode() === 302
        && count($codes) === 8
        && str_contains($ecran, 'Vos codes de secours — notez-les maintenant')
        && str_contains($ecran, (string) $codes[0])
        && ! str_contains($ecran, 'Vous n’avez qu’un seul moyen d’entrer'),
    'huit codes sont engendrés, montrés une fois, et le risque disparaît',
);

// 4 — aucun code n'entre au journal ni en clair dans le magasin.
$journalBrut = (string) file_get_contents($fichiers['journal']);
$magasinBrut = (string) file_get_contents($fichiers['acces']);
$verifier(
    ! str_contains($journalBrut, (string) $codes[0])
        && ! str_contains($magasinBrut, (string) $codes[0])
        && str_contains($journalBrut, 'CODES_SECOURS_ENGENDRES'),
    'le journal retient l’événement, jamais les codes',
);

// 5 — un code de secours ouvre réellement une session, une seule fois.
$sessionSecours = $ctr16->etablirSession($AUTORITE, (string) $codes[0]);
$rejeu = $ctr16->etablirSession($AUTORITE, (string) $codes[0]);
$verifier(
    $sessionSecours !== null
        && ($ctr16->verifierSession((string) $sessionSecours['session'])['valide'] ?? false) === true
        && $rejeu === null,
    'un code de secours ouvre une session qui survit à sa propre consommation',
);

// 6 — le code débloque l'attachement du premier facteur fort.
$avecCode = $moyens->autoriserFacteurFort(
    $AUTORITE, $AUTORITE, 'AS1 — FACTEUR UNIQUE', (string) $codes[1],
);
$codeRejoue = $moyens->autoriserFacteurFort(
    $AUTORITE, $AUTORITE, 'AS1 — FACTEUR UNIQUE', (string) $codes[1],
);
$verifier(
    $avecCode['statut'] === 201
        && str_starts_with((string) ($avecCode['corps']['jeton'] ?? ''), 'PASSKEY-')
        && ($avecCode['corps']['voie'] ?? null) === 'code de secours'
        && $codeRejoue['statut'] === 422,
    'un code de secours autorise une passkey, une seule fois',
);

// 7 — une session déjà forte se passe de code.
$sessionForte = $moyens->autoriserFacteurFort($AUTORITE, $AUTORITE, 'A2 — FACTEUR FORT');
$verifier(
    $sessionForte['statut'] === 201
        && ($sessionForte['corps']['voie'] ?? null) === 'session forte',
    'une session forte ajoute un appareil sans consommer de code',
);

// 8 — nul ne gère l'accès d'autrui.
$pourAutrui = $moyens->engendrerCodes($AUTORITE, 'IDN-PER-000000001');
$retraitAutrui = $moyens->retirer($AUTORITE, 'AUTHN-INCONNU', 'IDN-PER-000000001');
$verifier(
    $pourAutrui['statut'] === 403
        && ($pourAutrui['corps']['erreur'] ?? null) === 'ACTEUR_INCOMPETENT'
        && $retraitAutrui['statut'] === 403,
    'un moyen d’accès ne se gère que pour soi-même',
);

// 9 — le dernier moyen d'accès ne se retire pas depuis l'écran non plus.
$inventaire = $moyens->inventaire($AUTORITE);
$motDePasse = $inventaire['moyens'][0]['reference'] ?? '';
$retrait = $moyens->retirer($AUTORITE, (string) $motDePasse, $AUTORITE);
$verifier(
    $retrait['statut'] === 422
        && ($retrait['corps']['resultat']['refus'] ?? null) === 'DERNIER_MOYEN'
        && $ctr16->etablirSession($AUTORITE, 'Mot-de-passe-autorite-1!') !== null,
    'retirer son unique moyen d’accès est refusé, le mot de passe fonctionne encore',
);

// 10 — les codes de secours ne comptent pas comme moyens listés, mais comme
// second chemin dénombré.
$verifier(
    count($inventaire['moyens']) === 1
        && $inventaire['codes_restants'] === 6
        && $inventaire['moyens'][0]['fort'] === false,
    'les codes sont comptés à part des moyens, et le compte est juste',
);

// 11 — le sujet vient de la session, jamais de la requête.
$source = php_strip_whitespace(
    dirname(__DIR__, 2).'/app/Http/Controllers/AccesConsoleController.php'
);
$verifier(
    str_contains($source, "attributes->get('gamad_entite')")
        && ! preg_match('/validate\(\[[^\]]*[\'"]entite[\'"]/', $source),
    'le contrôleur ne lit jamais l’identité depuis le corps de la requête',
);

echo "\n";
if ($echecs === 0) {
    echo "Console Mon accès P1 : ÉTABLIE.\n";
    exit(0);
}

echo "Console Mon accès P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
