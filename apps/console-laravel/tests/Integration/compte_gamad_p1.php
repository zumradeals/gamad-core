<?php

declare(strict_types=1);

/**
 * Épreuve d'intégration du Compte GAMAD.
 *
 * Prouve qu'un produit explicitement habilité peut créer une personne avec
 * email ou téléphone, que le Core attribue l'IDN et l'authentificateur, et que
 * la personne se reconnecte ensuite avec son identifiant humain sans connaître
 * sa référence canonique.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/compte_gamad_p1.php
 */

use App\Application\Comptes\CreerCompteGamad;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Gamad\RegistrePolitiques\RegistrePolitiques;
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
$politiques = PolitiquesMagasin::connecter();
$acces = AccesMagasin::connecter();

$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
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

// Politique de test : le produit GamaDrive dérivé de la baseline joue le rôle
// d'un produit reconnu. On ne dépend donc pas de PRD-GAMAD-005 dans la CI.
$registrePolitiques = new RegistrePolitiques($index, $registreIdentites, $politiques, $ctr01);
$autorite = 'AUT-GAMAD-001';
$produit = 'PRD-GAMAD-002';
$politique = 'POL-COMPTES-GAMAD-TEST';
$version = '1.0.0';
$commun = [
    'politique' => PolitiqueAdministration::POLITIQUE,
    'producteur' => $autorite,
    'source' => 'TEST-COMPTE-GAMAD-P1',
];

$registrePolitiques->inscrirePolitique([
    ...$commun,
    'reference' => $politique,
    'libelle' => 'Politique de test Compte GAMAD',
    'proprietaire_reference' => $autorite,
    'source_reference' => 'TEST-COMPTE-GAMAD-P1',
    'preuve' => 'TEST-COMPTE-POL',
]);
$registrePolitiques->creerVersion($politique, [
    ...$commun,
    'version' => $version,
    'preuve' => 'TEST-COMPTE-VER',
]);
$registrePolitiques->ajouterRegle($politique, $version, [
    ...$commun,
    'effet' => 'PERMET',
    'action_reference' => CreerCompteGamad::ACTION,
    'sujet_reference' => $produit,
    'ressource_type' => 'personne',
    'motif' => 'Le produit de test peut créer un Compte GAMAD.',
    'preuve' => 'TEST-COMPTE-REGLE',
]);
$registrePolitiques->soumettreVersion($politique, $version, [
    ...$commun,
    'preuve' => 'TEST-COMPTE-SOUMISSION',
]);
$registrePolitiques->simulerVersion($politique, $version, [
    ...$commun,
    'jeu_reference' => 'TEST-COMPTE-SIM',
    'cas' => [[
        'sujet' => $produit,
        'action' => CreerCompteGamad::ACTION,
        'ressource' => 'personne',
        'attendu' => 'PERMIS',
    ]],
    'preuve' => 'TEST-COMPTE-SIMULATION',
]);
$registrePolitiques->activerVersion($politique, $version, [
    ...$commun,
    'preuve' => 'TEST-COMPTE-ACTIVATION',
]);

$secretProduit = 'Secret-Produit-Compte-2026!';
$ctr16 = new Ctr16($acces);
$ctr16->inscrireAuthentificateur($produit, $secretProduit);
$sessionProduit = $ctr16->etablirSession($produit, $secretProduit);
$jetonProduit = (string) ($sessionProduit['session'] ?? '');

// Une autorité authentifiée existe aussi, mais n'a pas reçu la délégation
// `créer un Compte GAMAD` dans la politique de test.
$secretAutorite = 'Secret-Autorite-Compte-2026!';
$ctr16->inscrireAuthentificateur($autorite, $secretAutorite);
$sessionAutorite = $ctr16->etablirSession($autorite, $secretAutorite);
$jetonAutorite = (string) ($sessionAutorite['session'] ?? '');

echo "INTÉGRATION — COMPTE GAMAD P1\n\n";

$refusAutorite = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne refusée',
    'type_identifiant' => 'EMAIL',
    'identifiant' => 'refus@example.test',
    'mot_de_passe' => 'Mot-de-passe-refuse-2026!',
], $jetonAutorite);
$verifier(
    $refusAutorite['statut'] === 403
        && ($refusAutorite['corps']['erreur'] ?? null) === 'CREATION_COMPTE_NON_AUTORISEE',
    'un sujet authentifié sans délégation explicite ne peut pas créer un Compte GAMAD',
);

$email = 'Personne.Test+Portal@Example.Test';
$motDePasse = 'Compte-GAMAD-email-2026!';
$creation = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne Compte GAMAD',
    'type_identifiant' => 'EMAIL',
    'identifiant' => $email,
    'mot_de_passe' => $motDePasse,
], $jetonProduit);
$reference = (string) ($creation['corps']['compte']['identite'] ?? '');
$verifier(
    $creation['statut'] === 201
        && str_starts_with($reference, 'IDN-PER-')
        && str_starts_with((string) ($creation['corps']['compte']['identifiant_reference'] ?? ''), 'RID-')
        && str_starts_with((string) ($creation['corps']['compte']['authentificateur_reference'] ?? ''), 'AUTHN-')
        && (string) ($creation['corps']['session']['jeton'] ?? '') !== '',
    'le produit habilité crée identité, identifiant, authentificateur et première session',
);

$connexionEmail = $requete('POST', '/api/v1/sessions', [
    'identifiant' => 'personne.test+portal@example.test',
    'type_identifiant' => 'EMAIL',
    'secret' => $motDePasse,
]);
$verifier(
    $connexionEmail['statut'] === 201
        && ($connexionEmail['corps']['entite'] ?? null) === $reference,
    'la personne se reconnecte par email normalisé sans connaître son IDN',
);

$duplique = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Tentative de doublon',
    'type_identifiant' => 'EMAIL',
    'identifiant' => 'PERSONNE.TEST+PORTAL@example.test',
    'mot_de_passe' => 'Autre-secret-doublon-2026!',
], $jetonProduit);
$verifier(
    $duplique['statut'] === 409
        && ($duplique['corps']['erreur'] ?? null) === 'COMPTE_NON_CREATABLE',
    'la même adresse email normalisée ne peut pas créer une seconde identité',
);

$telephone = '+225 07 18 71 37 81';
$motDePasseTel = 'Compte-GAMAD-phone-2026!';
$creationTel = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne Téléphone',
    'type_identifiant' => 'TELEPHONE',
    'identifiant' => $telephone,
    'mot_de_passe' => $motDePasseTel,
], $jetonProduit);
$referenceTel = (string) ($creationTel['corps']['compte']['identite'] ?? '');
$verifier(
    $creationTel['statut'] === 201 && str_starts_with($referenceTel, 'IDN-PER-'),
    'un produit peut créer un compte avec un téléphone international comme identifiant',
);

$connexionTel = $requete('POST', '/api/v1/sessions', [
    'identifiant' => '+2250718713781',
    'type_identifiant' => 'TELEPHONE',
    'secret' => $motDePasseTel,
]);
$verifier(
    $connexionTel['statut'] === 201
        && ($connexionTel['corps']['entite'] ?? null) === $referenceTel,
    'la personne se reconnecte par téléphone normalisé sans connaître son IDN',
);

$directe = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU',
    'type' => 'personne',
    'libelle' => 'Tentative inscription générique',
], $jetonProduit);
$verifier(
    $directe['statut'] === 403,
    'la délégation Compte GAMAD ne donne pas au produit la permission générique d’inscrire une identité',
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
