<?php

declare(strict_types=1);

/**
 * Épreuve d'intégration de la porte d'accès web.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/console_auth_p1.php
 */

use App\Http\Controllers\AccesController;
use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-console-auth-' . getmypid();
$acces = $temp . '-acces.sqlite';
$journal = $temp . '-journal.sqlite';
$temporaires = [
    $acces,
    $journal,
    $temp . '-config.php',
    $temp . '-events.php',
    $temp . '-packages.php',
    $temp . '-routes.php',
    $temp . '-services.php',
];
foreach ($temporaires as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($temporaires): void {
    foreach ($temporaires as $fichier) {
        @unlink($fichier);
    }
});

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('c', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php',
    'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php',
    'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'DATABASE_URL' => '',
    'MAGASIN_URL' => '',
    'MAGASIN_PATH' => $acces,
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $journal,
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';

$secret = 'Secret-P1-Console-2026!';
$ctr = new Ctr16(AccesMagasin::connecter());
$ctr->inscrireAuthentificateur('AUT-CONSOLE-001', $secret);

$app = require $application . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$controleur = $app->make(AccesController::class);
$sessionWeb = $app->make('session')->driver();
$sessionWeb->start();

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};
$connecter = static function (string $secretPresente) use (
    $app,
    $controleur,
    $sessionWeb,
): \Illuminate\Http\RedirectResponse {
    $requete = Request::create(
        '/connexion',
        'POST',
        ['entite' => 'AUT-CONSOLE-001', 'secret' => $secretPresente],
        [],
        [],
        [
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_REFERER' => 'https://console.example.test/connexion',
        ],
    );
    $requete->setLaravelSession($sessionWeb);
    $app->instance('request', $requete);

    return $controleur->connecter($requete);
};

echo "INTÉGRATION — AUTHENTIFICATION CONSOLE P1\n\n";

$reponse = $connecter($secret);
$referenceSession = $sessionWeb->get('gamad_session');
$verdict = is_string($referenceSession)
    ? $ctr->verifierSession($referenceSession)
    : ['valide' => false];
$cible = parse_url($reponse->getTargetUrl(), PHP_URL_PATH);
$connexionValide = ($cible === null || $cible === '/')
    && is_string($referenceSession)
    && ($verdict['valide'] ?? false) === true;
if (!$connexionValide) {
    fwrite(STDERR, json_encode([
        'cible' => $reponse->getTargetUrl(),
        'session_presente' => is_string($referenceSession),
        'verdict' => $verdict,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}
$verifier(
    $connexionValide,
    'une preuve valide ouvre et conserve une session console',
);

for ($i = 0; $i < 5; $i++) {
    $connecter('Secret-errone-' . $i);
}
$limitee = $connecter('Encore-errone');
$verifier(
    $limitee->headers->has('Retry-After'),
    'la sixième tentative refusée en une minute est bloquée',
);

$pdoJournal = JournalMagasin::ouvrir();
$decisions = $pdoJournal->query(
    "SELECT decision FROM evenement_operationnel
     WHERE type_evenement = 'AUTHENTIFICATION_CONSOLE'
     ORDER BY sequence_id"
)->fetchAll(PDO::FETCH_COLUMN);
$verifier(
    $decisions === [
        'ACCEPTEE',
        'REFUSEE',
        'REFUSEE',
        'REFUSEE',
        'REFUSEE',
        'REFUSEE',
        'BLOQUEE',
    ],
    'chaque acceptation, refus et blocage produit une preuve sans secret',
);

$integrite = (new Journal($pdoJournal))->verifierIntegrite();
$verifier(
    $integrite['valide'] === true && $integrite['evenements'] === 7,
    'la chaîne d’audit de la console reste intègre',
);
$journalBrut = (string) file_get_contents($journal);
$verifier(
    !str_contains($journalBrut, $secret)
        && !str_contains($journalBrut, 'Secret-errone')
        && !str_contains($journalBrut, 'Encore-errone'),
    'aucun secret présenté ne se retrouve dans le journal',
);

echo "\n";
if ($echecs === 0) {
    echo "Authentification console P1 : ÉTABLIE.\n";
    exit(0);
}

echo "Authentification console P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
