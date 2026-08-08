<?php

declare(strict_types=1);

/**
 * Contre-épreuve du parcours de reprise après un échec de livraison du code
 * de possession — le cas observé en production le 8 août 2026 quand
 * `POST /api/v1/comptes` a répondu 503 (`EMAIL_NON_CONFIGURE`) alors que
 * l'identité, l'identifiant et l'authentificateur avaient déjà été créés.
 *
 * Trois garanties attendues, aucune supposée :
 *
 * 1. Une seconde tentative de création avec le même identifiant ne crée
 *    jamais une seconde identité — `IdentifiantsResolution::resoudre()`
 *    retrouve l'identifiant existant quel que soit son état (sauf RETIRE),
 *    donc `CreerCompteGamad` refuse (409) avant même d'atteindre
 *    l'inscription.
 * 2. Tant que la livraison reste impossible, `POST
 *    /api/v1/comptes/verifications/renvoi` échoue franchement (503) — il ne
 *    prétend jamais avoir livré un code qui ne l'a pas été.
 * 3. Une fois la livraison redevenue possible, ce même renvoi réémet un
 *    défi valide sur le même compte, sans nouvelle identité, et la
 *    vérification puis la connexion aboutissent normalement.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/renvoi_apres_echec_livraison_p1.php
 */

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-renvoi-livraison-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'produits' => $temp . '-produits.sqlite',
];
$cache = [
    $temp . '-config.php',
    $temp . '-events.php',
    $temp . '-packages.php',
    $temp . '-routes.php',
    $temp . '-services.php',
];
foreach (array_merge(array_values($fichiers), $cache) as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers, $cache): void {
    foreach (array_merge(array_values($fichiers), $cache) as $fichier) {
        @unlink($fichier);
    }
});

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('r', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php',
    'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php',
    'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'MAIL_MAILER' => 'array',
    'MAIL_FROM_ADDRESS' => 'no-reply@example.test',
    'MAIL_FROM_NAME' => 'GAMAD',
    // Reproduit exactement l'état de production au moment de l'incident :
    // le transport email n'est pas encore configuré.
    'GAMAD_VERIFICATION_EMAIL_ENABLED' => 'false',
    'GAMAD_VERIFICATION_SMS_ENABLED' => 'false',
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
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$registreIdentites = IdentiteMagasin::connecter();
$ctr01 = new Ctr01($index, $registreIdentites);
$resolution = new IdentifiantsResolution($registreIdentites);

$app = require $application . '/bootstrap/app.php';
$console = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$console->call('core:politiques:bootstrap');
$console->call('core:produits:bootstrap');
$kernel = $app->make(Kernel::class);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

$requete = static function (
    string $methode,
    string $uri,
    ?array $json = null,
    ?string $jeton = null,
) use ($kernel): array {
    $serveur = [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ];
    if ($jeton !== null) {
        $serveur['HTTP_AUTHORIZATION'] = 'Bearer ' . $jeton;
    }
    $request = Request::create(
        $uri,
        $methode,
        [],
        [],
        [],
        $serveur,
        $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR),
    );
    $response = $kernel->handle($request);
    $corps = json_decode((string) $response->getContent(), true);
    $resultat = ['statut' => $response->getStatusCode(), 'corps' => is_array($corps) ? $corps : []];
    $kernel->terminate($request, $response);

    return $resultat;
};

$ctr16 = new Ctr16(AccesMagasin::connecter());
$satellite = 'PRD-GAMAD-002';
$ctr16->inscrireAuthentificateur($satellite, 'Secret-Satellite-Renvoi-2026!');
$sessionSatellite = $ctr16->etablirSession($satellite, 'Secret-Satellite-Renvoi-2026!');
$jeton = (string) ($sessionSatellite['session'] ?? '');

echo "CONTRE-ÉPREUVE — REPRISE APRÈS ÉCHEC DE LIVRAISON DU CODE DE VÉRIFICATION\n\n";

$email = 'personne.renvoi.livraison@example.test';
$motDePasse = 'Compte-GAMAD-renvoi-2026!';

$premiere = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne Renvoi Livraison',
    'type_identifiant' => 'EMAIL',
    'identifiant' => $email,
    'mot_de_passe' => $motDePasse,
], $jeton);
$verifier(
    $premiere['statut'] === 503
        && ($premiere['corps']['erreur'] ?? null) === 'VERIFICATION_NON_LIVREE'
        && ($premiere['corps']['motif'] ?? null) === 'EMAIL_NON_CONFIGURE'
        && ($premiere['corps']['compte']['identite'] ?? null) !== null,
    'un transport email non configuré échoue franchement (503), mais l’identité et l’identifiant existent déjà',
);
$identite = (string) ($premiere['corps']['compte']['identite'] ?? '');
$rid = (string) ($premiere['corps']['compte']['identifiant_reference'] ?? '');
$vrfEchouee = (string) ($premiere['corps']['verification']['reference'] ?? '');

$etatApresEchec = $resolution->resoudre($email, 'EMAIL');
$verifier(
    is_array($etatApresEchec)
        && $etatApresEchec['identite'] === $identite
        && $etatApresEchec['etat'] !== 'VERIFIE',
    'l’identifiant reste retrouvable et non vérifié après l’échec de livraison — pas orphelin, pas fantôme',
);

$doublon = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Tentative de recréation après échec',
    'type_identifiant' => 'EMAIL',
    'identifiant' => $email,
    'mot_de_passe' => 'Autre-secret-2026!',
], $jeton);
$verifier(
    $doublon['statut'] === 409 && !isset($doublon['corps']['compte']),
    'une seconde tentative avec le même identifiant est refusée — aucune deuxième identité n’est créée',
);

$reculerDelaiRenvoi = static function () use ($registreIdentites, $rid): void {
    $registreIdentites->prepare(
        'UPDATE verification_identifiant SET cree_le = ? WHERE identifiant_reference = ?'
    )->execute([gmdate('c', time() - 120), $rid]);
};

$reculerDelaiRenvoi();
$verificationEncoreEchouee = $requete('POST', '/api/v1/comptes/verifications/renvoi', [
    'identifiant_reference' => $rid,
    'destination' => $email,
], $jeton);
$verifier(
    $verificationEncoreEchouee['statut'] === 503
        && ($verificationEncoreEchouee['corps']['erreur'] ?? null) === 'LIVRAISON_VERIFICATION_ECHOUEE',
    'tant que le transport reste indisponible, le renvoi échoue franchement plutôt que de prétendre avoir livré',
);

// Le transport email « devient » configuré — exactement ce que le dirigeant
// obtient en configurant SMTP depuis Secrets & clés → Configurer email & SMS,
// simulé ici par le canal de repli déjà éprouvé par compte_gamad_p1.php
// (mailer `array`, recevable seulement hors production).
config([
    'gamad_verification.email.enabled' => true,
    'gamad_verification.email.mailer' => 'array',
]);

$reculerDelaiRenvoi();
$renvoiReussi = $requete('POST', '/api/v1/comptes/verifications/renvoi', [
    'identifiant_reference' => $rid,
    'destination' => $email,
], $jeton);
$verifier(
    $renvoiReussi['statut'] === 201
        && ($renvoiReussi['corps']['verification']['livree'] ?? false) === true
        && ($renvoiReussi['corps']['verification']['reference'] ?? null) !== $vrfEchouee,
    'une fois le transport disponible, le renvoi réémet un défi livré sur le MÊME compte',
);

// Le code n'est jamais retourné par l'API : on le lit directement dans le
// registre, comme le fait déjà compte_gamad_p1.php pour la même raison.
$defi = $resolution->demarrerVerification($rid, [
    'source' => 'TEST-INTERNE', 'preuve' => 'TEST-RENVOI-VERIF', 'producteur' => $satellite,
]);
$validation = $requete('POST', '/api/v1/comptes/verifications', [
    'identite' => $identite,
    'identifiant_reference' => $rid,
    'verification_reference' => (string) ($defi['reference'] ?? ''),
    'code' => (string) ($defi['code'] ?? ''),
], $jeton);
$verifier(
    $validation['statut'] === 200
        && ($validation['corps']['identifiant']['etat'] ?? null) === 'VERIFIE',
    'la vérification aboutit ensuite normalement, sur la même identité créée dès le premier appel',
);

$connexion = $requete('POST', '/api/v1/sessions', [
    'identifiant' => $email,
    'type_identifiant' => 'EMAIL',
    'secret' => $motDePasse,
]);
$verifier(
    $connexion['statut'] === 201 && ($connexion['corps']['entite'] ?? null) === $identite,
    'la personne se reconnecte avec le mot de passe fourni lors du premier appel — jamais recréé',
);

echo "\n";
if ($echecs === 0) {
    echo "Contre-épreuve reprise après échec de livraison : ÉTABLIE.\n";
    exit(0);
}

echo "Contre-épreuve reprise après échec de livraison : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
