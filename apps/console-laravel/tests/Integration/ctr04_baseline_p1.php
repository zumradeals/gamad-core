<?php

declare(strict_types=1);

/**
 * Vérifie que CTR-04 et le tableau de bord fonctionnent sur un index vide,
 * initialisé depuis la baseline opérationnelle, sans corpus documentaire.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/ctr04_baseline_p1.php
 */

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$racine = dirname($application, 2);
$temp = sys_get_temp_dir().'/gamad-ctr04-baseline-'.getmypid();
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('d', 32)),
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

$secret = 'Secret-CTR04-Baseline-2026!';
$authentificateur = (new Ctr16(AccesMagasin::connecter()))
    ->inscrireAuthentificateur('AUT-GAMAD-001', $secret);
IdentiteMagasin::connecter();

$app = require $application.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) {
        $echecs++;
    }
};

/** @return array{statut:int,corps:string,cookies:array<string,string>} */
$requete = static function (
    string $methode,
    string $uri,
    ?array $formulaire = null,
    array $cookies = [],
) use ($kernel): array {
    $request = Request::create($uri, $methode, $formulaire ?? [], $cookies);
    $response = $kernel->handle($request);
    $recus = $cookies;
    foreach ($response->headers->getCookies() as $cookie) {
        $recus[$cookie->getName()] = (string) $cookie->getValue();
    }
    $resultat = [
        'statut' => $response->getStatusCode(),
        'corps' => (string) $response->getContent(),
        'cookies' => $recus,
    ];
    $kernel->terminate($request, $response);

    return $resultat;
};

echo "INTÉGRATION — CTR-04 PAR BASELINE P1\n\n";

$verifier(
    ! is_file($fichiers['index']),
    'le scénario démarre sans index préconstruit',
);

$connexion = $requete('POST', '/connexion', [
    'entite' => 'AUT-GAMAD-001',
    'secret' => $secret,
]);
$cookies = $connexion['cookies'];
$verifier(
    in_array($connexion['statut'], [302, 303], true),
    'une session console est ouverte',
);

$accueil = $requete('GET', '/', null, $cookies);
$corps = $accueil['corps'];
$verifier(
    $accueil['statut'] === 200,
    'le tableau de bord répond sur un index initialement vide',
);
$verifier(
    str_contains($corps, 'Index technique')
        && str_contains($corps, 'SHA-256 vérifiée')
        && str_contains($corps, 'État des capacités'),
    'le tableau de bord présente l’état opérationnel de l’index et des capacités',
);
$verifier(
    ! str_contains($corps, 'Actes d’adoption')
        && ! str_contains($corps, 'Fichiers intègres')
        && ! str_contains($corps, 'Preuve temporelle'),
    'le tableau de bord ne présente plus les tableaux d’adoptions historiques',
);

$diagnostic = $requete('GET', '/index/diagnostic', null, $cookies);
$charge = json_decode($diagnostic['corps'], true);
$verifier(
    $diagnostic['statut'] === 200
        && is_array($charge)
        && ($charge['coherent'] ?? null) === true
        && ($charge['baseline']['concorde'] ?? null) === true
        && ($charge['divergences'] ?? null) === [],
    'le diagnostic de l’index est cohérent après initialisation',
);

$norme = $requete('GET', '/normes/SOURCES-0001', null, $cookies);
$normeCorps = json_decode($norme['corps'], true);
$verifier(
    $norme['statut'] === 200
        && is_array($normeCorps)
        && ($normeCorps['reference'] ?? null) === 'SOURCES-0001'
        && ! array_key_exists('chemin', $normeCorps)
        && ! array_key_exists('empreinte_git', $normeCorps),
    'CTR-04 résout une norme sans référencer de fichier',
);

$capacite = $requete('GET', '/capacites/CAP-CORE-007?date=2026-07-26', null, $cookies);
$capaciteCorps = json_decode($capacite['corps'], true);
$verifier(
    $capacite['statut'] === 200
        && ($capaciteCorps['valeur'] ?? null) === 'EN CONCEPTION',
    'CTR-04 reconstruit l’état d’une capacité à une date passée',
);

// Contre-épreuve : un index amputé doit être signalé, non présumé conforme.
$pdo = Db::connect();
$pdo->exec("DELETE FROM etat_capacite WHERE capacite_reference = 'CAP-CORE-001'");
$apres = $requete('GET', '/index/diagnostic', null, $cookies);
$apresCorps = json_decode($apres['corps'], true);
$verifier(
    $apres['statut'] === 409
        && is_array($apresCorps)
        && ($apresCorps['coherent'] ?? null) === false
        && ($apresCorps['divergences'] ?? []) !== [],
    'un index amputé est signalé par le diagnostic, jamais présumé conforme',
);

// Le diagnostic est une lecture sous session : l'authentificateur révoqué,
// la session applicative survivante ne doit plus ouvrir aucune porte.
(new Ctr16(AccesMagasin::connecter()))->revoquerAuthentificateur($authentificateur);
$ferme = $requete('GET', '/index/diagnostic', null, $cookies);
$verifier(
    in_array($ferme['statut'], [302, 303], true),
    'une session dont l’authentificateur est révoqué n’ouvre plus le diagnostic',
);

$controleur = (string) file_get_contents(
    $racine.'/apps/console-laravel/app/Http/Controllers/Ctr04Controller.php'
);
$verifier(
    str_contains($controleur, 'BaselineOperationnelle::standard()->reconstruire')
        && ! str_contains($controleur, 'Ingestion')
        && ! str_contains($controleur, 'genesis-ii'),
    'le contrôleur ne dépend plus du parseur ni du corpus historique',
);

echo "\n";
if ($echecs === 0) {
    echo "CTR-04 par baseline P1 : ÉTABLI.\n";
    exit(0);
}

echo "CTR-04 par baseline P1 : NON ÉTABLI ({$echecs} écart(s)).\n";
exit(1);
