<?php

declare(strict_types=1);

/**
 * Épreuve d'intégration du Compte GAMAD.
 *
 * Prouve qu'un SATELLITE ACTIF reconnu par CAP-CORE-011 peut spontanément
 * créer un Compte GAMAD pour autrui, sans règle individuelle ajoutée pour lui.
 * Prouve aussi qu'un partenaire ou un sujet ordinaire n'obtient pas ce droit.
 */

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-compte-' . getmypid();
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('g', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php',
    'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php',
    'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
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
$acces = AccesMagasin::connecter();

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
    $resultat = [
        'statut' => $response->getStatusCode(),
        'corps' => is_array($corps) ? $corps : [],
    ];
    $kernel->terminate($request, $response);

    return $resultat;
};

$ctr16 = new Ctr16($acces);
$ouvrirProduit = static function (string $produit, string $secret) use ($ctr16): string {
    $ctr16->inscrireAuthentificateur($produit, $secret);
    $session = $ctr16->etablirSession($produit, $secret);
    return (string) ($session['session'] ?? '');
};

$satellite = 'PRD-GAMAD-002'; // GamaDrive : SATELLITE ACTIF dans le bootstrap CAP-CORE-011.
$partenaire = 'PRD-GAMAD-003'; // Wasplex : PARTENAIRE en PREPARATION.
$autorite = 'AUT-GAMAD-001';
$jetonSatellite = $ouvrirProduit($satellite, 'Secret-Satellite-Compte-2026!');
$jetonPartenaire = $ouvrirProduit($partenaire, 'Secret-Partenaire-Compte-2026!');
$jetonAutorite = $ouvrirProduit($autorite, 'Secret-Autorite-Compte-2026!');

echo "INTÉGRATION — COMPTE GAMAD P1\n\n";

$refusPartenaire = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne partenaire refusée',
    'type_identifiant' => 'EMAIL',
    'identifiant' => 'partenaire.refus@example.test',
    'mot_de_passe' => 'Mot-de-passe-partenaire-2026!',
], $jetonPartenaire);
$verifier(
    $refusPartenaire['statut'] === 403,
    'un PARTENAIRE en préparation ne devient pas satellite par déclaration et ne crée pas de compte',
);

$refusAutorite = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne autorité refusée',
    'type_identifiant' => 'EMAIL',
    'identifiant' => 'autorite.refus@example.test',
    'mot_de_passe' => 'Mot-de-passe-autorite-2026!',
], $jetonAutorite);
$verifier(
    $refusAutorite['statut'] === 403,
    'un sujet authentifié qui n’est ni satellite actif ni explicitement habilité est refusé',
);

$email = 'Personne.Test+Satellite@Example.Test';
$motDePasse = 'Compte-GAMAD-email-2026!';
$creation = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne Compte GAMAD',
    'type_identifiant' => 'EMAIL',
    'identifiant' => $email,
    'mot_de_passe' => $motDePasse,
], $jetonSatellite);
$reference = (string) ($creation['corps']['compte']['identite'] ?? '');
$rid = (string) ($creation['corps']['compte']['identifiant_reference'] ?? '');
$vrf = (string) ($creation['corps']['verification']['reference'] ?? '');
$code = (string) ($creation['corps']['verification']['code'] ?? '');
$verifier(
    $creation['statut'] === 201
        && str_starts_with($reference, 'IDN-PER-')
        && str_starts_with($rid, 'RID-')
        && str_starts_with($vrf, 'VRF-')
        && preg_match('/^[0-9]{6}$/', $code) === 1
        && ($creation['corps']['compte']['verification_requise'] ?? false) === true
        && !isset($creation['corps']['session']),
    'un SATELLITE ACTIF crée spontanément identité, accès et défi de possession sans règle individuelle',
);

$connexionAvant = $requete('POST', '/api/v1/sessions', [
    'identifiant' => 'personne.test+satellite@example.test',
    'type_identifiant' => 'EMAIL',
    'secret' => $motDePasse,
]);
$verifier(
    $connexionAvant['statut'] === 401,
    'un email NON_VERIFIE ne peut pas encore ouvrir de session',
);

$mauvaisCode = $requete('POST', '/api/v1/comptes/verifications', [
    'identite' => $reference,
    'identifiant_reference' => $rid,
    'verification_reference' => $vrf,
    'code' => '000000' === $code ? '000001' : '000000',
], $jetonSatellite);
$verifier(
    $mauvaisCode['statut'] === 422,
    'un code de possession erroné est refusé',
);

$validation = $requete('POST', '/api/v1/comptes/verifications', [
    'identite' => $reference,
    'identifiant_reference' => $rid,
    'verification_reference' => $vrf,
    'code' => $code,
], $jetonSatellite);
$verifier(
    $validation['statut'] === 200
        && ($validation['corps']['identifiant']['etat'] ?? null) === 'VERIFIE',
    'le satellite valide la preuve de possession avec le code correct',
);

$connexionEmail = $requete('POST', '/api/v1/sessions', [
    'identifiant' => 'personne.test+satellite@example.test',
    'type_identifiant' => 'EMAIL',
    'secret' => $motDePasse,
]);
$verifier(
    $connexionEmail['statut'] === 201
        && ($connexionEmail['corps']['entite'] ?? null) === $reference,
    'après vérification, la personne se reconnecte par email sans connaître son IDN',
);

$duplique = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Tentative de doublon',
    'type_identifiant' => 'EMAIL',
    'identifiant' => 'PERSONNE.TEST+SATELLITE@example.test',
    'mot_de_passe' => 'Autre-secret-doublon-2026!',
], $jetonSatellite);
$verifier(
    $duplique['statut'] === 409,
    'le même email normalisé ne peut pas créer une seconde identité',
);

$telephone = '+225 07 18 71 37 81';
$motDePasseTel = 'Compte-GAMAD-phone-2026!';
$creationTel = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne Téléphone',
    'type_identifiant' => 'TELEPHONE',
    'identifiant' => $telephone,
    'mot_de_passe' => $motDePasseTel,
], $jetonSatellite);
$referenceTel = (string) ($creationTel['corps']['compte']['identite'] ?? '');
$ridTel = (string) ($creationTel['corps']['compte']['identifiant_reference'] ?? '');
$vrfTel = (string) ($creationTel['corps']['verification']['reference'] ?? '');
$codeTel = (string) ($creationTel['corps']['verification']['code'] ?? '');
$verifier(
    $creationTel['statut'] === 201
        && str_starts_with($referenceTel, 'IDN-PER-')
        && str_starts_with($vrfTel, 'VRF-'),
    'un SATELLITE ACTIF crée aussi un compte avec téléphone international',
);

$validationTel = $requete('POST', '/api/v1/comptes/verifications', [
    'identite' => $referenceTel,
    'identifiant_reference' => $ridTel,
    'verification_reference' => $vrfTel,
    'code' => $codeTel,
], $jetonSatellite);
$verifier($validationTel['statut'] === 200, 'la possession du téléphone est vérifiée');

$connexionTel = $requete('POST', '/api/v1/sessions', [
    'identifiant' => '+2250718713781',
    'type_identifiant' => 'TELEPHONE',
    'secret' => $motDePasseTel,
]);
$verifier(
    $connexionTel['statut'] === 201
        && ($connexionTel['corps']['entite'] ?? null) === $referenceTel,
    'la personne se reconnecte par téléphone normalisé après vérification',
);

$directe = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU',
    'type' => 'personne',
    'libelle' => 'Tentative inscription générique',
], $jetonSatellite);
$verifier(
    $directe['statut'] === 403,
    'le plein droit de créer des comptes ne donne pas au satellite un droit générique sur Identity Registry',
);

$identiteResolue = $ctr01->resoudreIdentite($reference);
$verifier(
    is_array($identiteResolue)
        && ($identiteResolue['type'] ?? null) === 'personne'
        && ($identiteResolue['etat'] ?? null) === 'ACTIVE',
    'le compte correspond à une vraie personne canonique active dans CAP-CORE-001',
);

echo "\n";
if ($echecs === 0) {
    echo "Compte GAMAD P1 : ÉTABLI.\n";
    exit(0);
}

echo "Compte GAMAD P1 : NON ÉTABLI ({$echecs} écart(s)).\n";
exit(1);
