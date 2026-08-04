<?php

declare(strict_types=1);

/**
 * Épreuve d'intégration HTTP sans dépendance à PHPUnit.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/api_v1_p1.php
 */

use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreFederation\SchemaFederation;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePreuves\Magasin as PreuvesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-api-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'produits' => $temp . '-produits.sqlite',
    'sources' => $temp . '-sources.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'contrats' => $temp . '-contrats.sqlite',
    'vocabulaire' => $temp . '-vocabulaire.sqlite',
    'organisations' => $temp . '-organisations.sqlite',
    'realms' => $temp . '-realms.sqlite',
    'evenements' => $temp . '-evenements.sqlite',
    'secrets' => $temp . '-secrets.sqlite',
    'preuves' => $temp . '-preuves.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('x', 32)),
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
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'SOURCE_REGISTRY_URL' => '',
    'SOURCE_REGISTRY_PATH' => $fichiers['sources'],
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
    'CONTRACT_REGISTRY_URL' => '',
    'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
    'VOCABULARY_REGISTRY_URL' => '',
    'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
    'ORGANIZATION_REGISTRY_URL' => '',
    'ORGANIZATION_REGISTRY_PATH' => $fichiers['organisations'],
    'REALM_REGISTRY_URL' => '',
    'REALM_REGISTRY_PATH' => $fichiers['realms'],
    'EVENT_JOURNAL_URL' => '',
    'EVENT_JOURNAL_PATH' => $fichiers['evenements'],
    'SECRET_REGISTRY_URL' => '',
    'SECRET_REGISTRY_PATH' => $fichiers['secrets'],
    'PROOF_REGISTRY_URL' => '',
    'PROOF_REGISTRY_PATH' => $fichiers['preuves'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$secret = 'Secret-P3-API-2026!';
$acces = AccesMagasin::connecter();
// Le magasin d'accès porte aussi les jetons fédérés (CAP-CORE-022) ; la
// readiness les exige, comme `core:fondation:migrer` les applique.
SchemaFederation::migrer($acces);
(new Ctr16($acces))->inscrireAuthentificateur('AUT-GAMAD-001', $secret);
IdentiteMagasin::connecter();
ProduitsMagasin::connecter();
SourcesMagasin::connecter();
PolitiquesMagasin::connecter();
ContratsMagasin::connecter();
VocabulaireMagasin::connecter();
OrganisationsMagasin::connecter();
RealmsMagasin::connecter();
EvenementsMagasin::connecter();
SecretsMagasin::connecter();
PreuvesMagasin::connecter();

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

echo "INTÉGRATION HTTP — API V1 P0/P1\n\n";

$live = $requete('GET', '/api/v1/health/live');
$verifier(
    $live['statut'] === 200 && ($live['corps']['version_api'] ?? null) === 'v1',
    'la sonde de vie versionnée répond sans dépendance',
);

$sansSession = $requete('POST', '/api/v1/autorisation/decisions', [
    'action' => 'inscrire une identité',
]);
$verifier(
    $sansSession['statut'] === 401
        && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'une décision sans session est refusée et tracée',
);

$connexion = $requete('POST', '/api/v1/sessions', [
    'entite' => 'AUT-GAMAD-001',
    'secret' => $secret,
]);
$jeton = (string) ($connexion['corps']['jeton'] ?? '');
$verifier(
    $connexion['statut'] === 201
        && $jeton !== ''
        && ($connexion['corps']['entite'] ?? null) === 'AUT-GAMAD-001',
    'un authentificateur valide ouvre une session bearer',
);

$decision = $requete('POST', '/api/v1/autorisation/decisions', [
    'action' => 'inscrire une identité',
    'ressource' => 'personne',
], $jeton);
$verifier(
    $decision['statut'] === 200
        && ($decision['corps']['decision']['decision'] ?? null) === 'PERMIS'
        && ($decision['corps']['decision']['politique'] ?? null) === 'POL-INSCRIPTION-IDENTITES-V1'
        && ($decision['corps']['mandat']['mandat'] ?? null) === 'MANDAT-GENESIS-II-0001',
    'la session mène à la politique d’inscription et au mandat actif',
);

$avant = (int) (new \PDO('sqlite:' . $fichiers['identites']))
    ->query('SELECT count(*) FROM identite_inscrite')
    ->fetchColumn();
$inscription = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE',
    'type' => 'personne',
    'libelle' => 'Identité inscrite par l’autorité',
    'classification' => 'INTERNE',
], $jeton);
$apres = (int) (new \PDO('sqlite:' . $fichiers['identites']))
    ->query('SELECT count(*) FROM identite_inscrite')
    ->fetchColumn();
$verifier(
    $inscription['statut'] === 201
        && ($inscription['corps']['identite']['type'] ?? null) === 'personne'
        && ($inscription['corps']['identite']['assurance'] ?? null) === 'A3'
        && ($inscription['corps']['decision']['politique'] ?? null) === 'POL-INSCRIPTION-IDENTITES-V1'
        && ($inscription['corps']['mandat']['etat'] ?? null) === 'ACTIF — TRANSITOIRE'
        && $apres === $avant + 1,
    'l’autorité sous mandat inscrit effectivement une identité A3',
);

$avantCanalNonReserve = $apres;
$canalNonReserve = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU',
    'type' => 'personne',
    'libelle' => 'Identité hors canal de l’autorité',
], $jeton);
$apresCanalNonReserve = (int) (new \PDO('sqlite:' . $fichiers['identites']))
    ->query('SELECT count(*) FROM identite_inscrite')
    ->fetchColumn();
$verifier(
    $canalNonReserve['statut'] === 422
        && ($canalNonReserve['corps']['erreur'] ?? null) === 'INSCRIPTION_REFUSEE'
        && ($canalNonReserve['corps']['resultat']['refus'] ?? null) === 'PRODUCTEUR_INCOMPETENT'
        && $apresCanalNonReserve === $avantCanalNonReserve,
    'l’autorité ne peut pas usurper le canal réservé aux produits reconnus',
);

$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$verifier(
    $integrite['valide'] === true && $integrite['evenements'] >= 6,
    'authentifications, décisions, écriture et refus forment une chaîne d’audit valide',
);
$ready = $requete('GET', '/api/v1/health/ready');
$readyOk = $ready['statut'] === 200
    && ($ready['corps']['pret'] ?? false) === true
    && count($ready['corps']['cibles'] ?? []) === 14;
if (!$readyOk) {
    fwrite(STDERR, json_encode($ready, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}
$verifier(
    $readyOk,
    'la readiness vérifie les quatorze magasins et leurs migrations',
);

echo "\n";
if ($echecs === 0) {
    echo "Intégration HTTP : ÉTABLIE.\n";
    exit(0);
}

echo "Intégration HTTP : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
