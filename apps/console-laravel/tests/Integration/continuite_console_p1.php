<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran de continuité (CAP-CORE-019).
 *
 * La console doit permettre de voir, configurer et déclencher sans ligne de
 * commande — et sans jamais recevoir le droit d'exécuter une commande système.
 * Elle écrit des réglages et dépose des demandes ; c'est tout.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/continuite_console_p1.php
 */

use App\Application\Continuite\Continuite;
use App\Http\Controllers\ContinuiteConsoleController;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-continuite-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'journal' => $temp.'-journal.sqlite',
];
$partage = $temp.'-partage';
foreach ($fichiers as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers, $partage): void {
    foreach ($fichiers as $fichier) {
        @unlink($fichier);
    }
    exec('rm -rf '.escapeshellarg($partage));
});
mkdir($partage.'/demandes', 0o770, true);

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('k', 32)),
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
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
    'GAMAD_CONTINUITE_DIR' => $partage,
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';

BaselineOperationnelle::standard()->reconstruire(Db::connect());
JournalMagasin::connecter();

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
) use ($app, $sessionLaravel): Request {
    $request = Request::create($uri, $methode, $donnees);
    $request->setLaravelSession($sessionLaravel);
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

$controleur = $app->make(ContinuiteConsoleController::class);
$continuite = new Continuite($partage);
$afficher = static fn (Request $r): string => $controleur->index($r, $continuite)->getContent();

echo "INTÉGRATION — CONSOLE DE CONTINUITÉ P1 (CAP-CORE-019)\n\n";

// 1 — l'écran dit la vérité quand rien n'est configuré.
$avant = $afficher($requete('/continuite'));
$verifier(
    str_contains($avant, 'Toutes vos copies sont sur le disque qu’elles protègent')
        && str_contains($avant, 'Copies sur le serveur seulement')
        && str_contains($avant, 'Où envoyer les copies')
        && ! str_contains($avant, '<pre>'),
    'sans destination, l’écran nomme le risque au lieu de le taire',
);

// 2 — configuration refusée à qui n'est pas l'autorité.
$parTiers = $continuite->configurer([
    'hote' => 'ftp.exemple.test', 'chemin' => 'gamad', 'utilisateur' => 'u',
    'secret' => 'motdepasse', 'tls' => 'opportuniste', 'retention' => 14,
], 'IDN-PER-000000001');
$verifier(
    $parTiers['statut'] === 403
        && ($parTiers['corps']['erreur'] ?? null) === 'AUTORISATION_REFUSEE',
    'un sujet autre que l’autorité ne configure pas la continuité',
);

// 3 — configuration nominale, avec phrase de chiffrement engendrée une fois.
$configuration = $continuite->configurer([
    'hote' => 'ftp.exemple.test', 'chemin' => 'gamad-core', 'utilisateur' => 'sauvegarde',
    'secret' => 'motdepasse-ftp', 'tls' => 'opportuniste', 'retention' => 7,
], PolitiqueInscription::AUTORITE_INSCRIPTION);
$phrase = (string) ($configuration['corps']['phrase_chiffrement'] ?? '');
$env = (string) @file_get_contents($partage.'/offsite.env');
$verifier(
    $configuration['statut'] === 200
        && str_starts_with($phrase, 'GAMAD-')
        && strlen($phrase) >= 52
        && str_contains($env, 'GAMAD_OFFSITE_DEST=ftp://ftp.exemple.test/gamad-core')
        && str_contains($env, 'GAMAD_OFFSITE_FTP_TLS=opportuniste')
        && str_contains($env, 'GAMAD_OFFSITE_RETENTION=7')
        && trim((string) @file_get_contents($partage.'/ftp.secret')) === 'motdepasse-ftp',
    'l’autorité configure la destination et reçoit une phrase de chiffrement forte',
);

// 4 — ni le mot de passe ni la phrase n'entrent au journal.
$journal = (string) @file_get_contents($fichiers['journal']);
$verifier(
    ! str_contains($journal, 'motdepasse-ftp')
        && ! str_contains($journal, $phrase)
        && str_contains($journal, 'DESTINATION_CONFIGUREE'),
    'le journal conserve la configuration, jamais les secrets qu’elle porte',
);

// 5 — la phrase n'est engendrée qu'une fois ; le mot de passe se conserve.
$seconde = $continuite->configurer([
    'hote' => 'ftp.exemple.test', 'chemin' => 'gamad-core', 'utilisateur' => 'sauvegarde',
    'secret' => '', 'tls' => 'exige', 'retention' => 7,
], PolitiqueInscription::AUTORITE_INSCRIPTION);
$verifier(
    $seconde['statut'] === 200
        && ($seconde['corps']['phrase_chiffrement'] ?? null) === null
        && trim((string) @file_get_contents($partage.'/ftp.secret')) === 'motdepasse-ftp'
        && str_contains((string) @file_get_contents($partage.'/offsite.env'), 'TLS=exige'),
    'reconfigurer ne réengendre pas la phrase et n’exige pas de retaper le mot de passe',
);

// 6 — l'écran restitue l'état sans jamais réafficher un secret.
$apres = $afficher($requete('/continuite'));
$verifier(
    str_contains($apres, 'Copie hors machine active')
        && str_contains($apres, 'ftp://ftp.exemple.test/gamad-core')
        && str_contains($apres, 'Déjà enregistré')
        && ! str_contains($apres, 'motdepasse-ftp')
        && ! str_contains($apres, $phrase),
    'l’écran montre la destination et l’état des secrets, jamais leur valeur',
);

// 7 — déclencher dépose une demande, sans rien exécuter.
$demande = $controleur->declencher($requete('/continuite/sauvegarde', 'POST'), $continuite, 'sauvegarde');
$verifier(
    $demande->getStatusCode() === 302
        && is_file($partage.'/demandes/sauvegarde.demande')
        && str_contains((string) $sessionLaravel->get('succes'), 'Sauvegarde demandée'),
    'la console dépose une demande de sauvegarde au lieu de l’exécuter',
);

// 8 — un tiers ne déclenche rien.
@unlink($partage.'/demandes/exercice.demande');
$declenchementTiers = $continuite->demander('exercice', 'IDN-PER-000000001');
$verifier(
    $declenchementTiers['statut'] === 403
        && ! is_file($partage.'/demandes/exercice.demande'),
    'un sujet autre que l’autorité ne déclenche aucune opération',
);

// 9 — une opération inconnue est refusée avant toute décision.
$inconnue = $continuite->demander('effacer-tout', PolitiqueInscription::AUTORITE_INSCRIPTION);
$verifier(
    $inconnue['statut'] === 422
        && ($inconnue['corps']['erreur'] ?? null) === 'OPERATION_INCONNUE'
        && ! is_file($partage.'/demandes/effacer-tout.demande'),
    'seules les opérations de la liste close sont demandables',
);

// 10 — la console n'exécute jamais de commande système.
// Les commentaires sont retirés : une garde qui interdit un mot doit inspecter
// le code, pas la prose qui explique justement qu'on ne s'en sert pas.
$sources = '';
foreach ([
    '/app/Http/Controllers/ContinuiteConsoleController.php',
    '/app/Application/Continuite/Continuite.php',
] as $fichier) {
    $sources .= php_strip_whitespace(dirname(__DIR__, 2).$fichier);
}
$verifier(
    ! preg_match('/\b(exec|shell_exec|system|passthru|proc_open|popen|escapeshellcmd)\s*\(/', $sources)
        && ! str_contains($sources, 'sudo')
        && ! str_contains($sources, 'Symfony\\Component\\Process')
        && ! str_contains($sources, '`')
        && str_contains($sources, 'demandes/'),
    'ni exec, ni sudo, ni processus : la console demande, elle n’exécute pas',
);

// 11 — un répertoire partagé absent est signalé, pas contourné.
$sansPartage = new Continuite($temp.'-absent');
$refusInstallation = $sansPartage->demander('sauvegarde', PolitiqueInscription::AUTORITE_INSCRIPTION);
$verifier(
    $refusInstallation['statut'] === 503
        && ($refusInstallation['corps']['erreur'] ?? null) === 'CONTINUITE_NON_INSTALLEE'
        && str_contains((string) $refusInstallation['corps']['message'], 'installer-continuite.sh'),
    'sans installation, l’écran dit quoi faire au lieu d’échouer obscurément',
);

echo "\n";
if ($echecs === 0) {
    echo "Console de continuité P1 : ÉTABLIE.\n";
    exit(0);
}

echo "Console de continuité P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
