<?php

declare(strict_types=1);

/**
 * Épreuve d'intégration HTTP — CAP-002 (DG Afrique) : contrat GET
 * /sessions/current, échéance expire_le réellement attestée après
 * glissement, sans nouvelle session, sans second jeton, sans exposer le
 * bearer.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/sessions_current_p1.php
 */

use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
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
$temp = sys_get_temp_dir().'/gamad-sessions-current-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'journal' => $temp.'-journal.sqlite',
    'produits' => $temp.'-produits.sqlite',
    'sources' => $temp.'-sources.sqlite',
    'politiques' => $temp.'-politiques.sqlite',
    'contrats' => $temp.'-contrats.sqlite',
    'vocabulaire' => $temp.'-vocabulaire.sqlite',
    'organisations' => $temp.'-organisations.sqlite',
    'realms' => $temp.'-realms.sqlite',
    'evenements' => $temp.'-evenements.sqlite',
    'secrets' => $temp.'-secrets.sqlite',
    'preuves' => $temp.'-preuves.sqlite',
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('s', 32)),
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

require $application.'/vendor/autoload.php';

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$secret = 'Secret-P1-Sessions-2026!';
$acces = AccesMagasin::connecter();
SchemaFederation::migrer($acces);
(new Ctr16($acces))->inscrireAuthentificateur('AUT-GAMAD-002', $secret);
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

$app = require $application.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) {
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
        $serveur['HTTP_AUTHORIZATION'] = 'Bearer '.$jeton;
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
        'entetes' => $response->headers,
    ];
    $kernel->terminate($request, $response);

    return $resultat;
};

echo "INTÉGRATION HTTP — GET /sessions/current (CAP-002 DG AFRIQUE)\n\n";

// --- Sans jeton, refusée.
$sansJeton = $requete('GET', '/api/v1/sessions/current');
$verifier(
    $sansJeton['statut'] === 401
        && ($sansJeton['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'sans jeton, la lecture de la session courante est refusée',
);

// --- Jeton invalide, refusée.
$jetonInvalide = $requete('GET', '/api/v1/sessions/current', null, 'jeton-quelconque-invalide');
$verifier(
    $jetonInvalide['statut'] === 401,
    'un jeton invalide est refusé',
);

// --- Ouverture d'une session bearer.
$connexion = $requete('POST', '/api/v1/sessions', [
    'entite' => 'AUT-GAMAD-002',
    'secret' => $secret,
]);
$jeton = (string) ($connexion['corps']['jeton'] ?? '');
$expireInitial = (string) ($connexion['corps']['expire_le'] ?? '');
$verifier(
    $connexion['statut'] === 201 && $jeton !== '' && $expireInitial !== '',
    'POST /sessions ouvre une session bearer avec une échéance initiale',
);

// --- Lecture immédiate : session valide, expire_le cohérent, bearer jamais restitué.
$courante = $requete('GET', '/api/v1/sessions/current', null, $jeton);
$verifier(
    $courante['statut'] === 200
        && ($courante['corps']['entite'] ?? null) === 'AUT-GAMAD-002'
        && ($courante['corps']['expire_le'] ?? null) !== null
        && ! array_key_exists('jeton', $courante['corps'])
        && ! array_key_exists('preuve', $courante['corps']),
    'GET /sessions/current restitue entite/assurance/expire_le sans jeton ni preuve',
);
$verifier(
    str_contains((string) $courante['entetes']->get('Cache-Control'), 'no-store'),
    'la réponse porte Cache-Control: no-store',
);

// --- Aucune session ni jeton second n'est créé par la lecture : le même
// bearer reste utilisable et la table des sessions n'a gagné aucune ligne.
$avantRelecture = (int) (new PDO('sqlite:'.$fichiers['acces']))
    ->query('SELECT count(*) FROM session_ouverte')
    ->fetchColumn();
$requete('GET', '/api/v1/sessions/current', null, $jeton);
$requete('GET', '/api/v1/sessions/current', null, $jeton);
$apresRelecture = (int) (new PDO('sqlite:'.$fichiers['acces']))
    ->query('SELECT count(*) FROM session_ouverte')
    ->fetchColumn();
$verifier(
    $avantRelecture === 1 && $apresRelecture === 1,
    'relire la session courante ne crée ni nouvelle session ni second jeton',
);

// --- Simule un glissement réel (comme le ferait une inactivité suivie
// d'une nouvelle requête authentifiée) en rapprochant l'expiration en base,
// puis vérifie que la prochaine lecture restitue l'échéance repoussée —
// c'est exactement l'écart signalé par l'audit CAP-002 : DG Afrique doit
// pouvoir observer cette valeur sans recréer de session.
// Chaque accès PDO brut est ouvert, utilisé puis abandonné aussitôt : une
// connexion SQLite persistante face aux requêtes du noyau Laravel sur le
// même fichier entre en contention de verrou avec l'écriture du glissement.
$empreinte = hash('sha256', $jeton);
(new PDO('sqlite:'.$fichiers['acces']))
    ->prepare('UPDATE session_ouverte SET expire_le = ? WHERE jeton_empreinte = ?')
    ->execute([date('c', time() + 30), $empreinte]);
$expireAvantGlissement = (string) (static function () use ($fichiers, $empreinte): mixed {
    $ligne = (new PDO('sqlite:'.$fichiers['acces']))
        ->prepare('SELECT expire_le FROM session_ouverte WHERE jeton_empreinte = ?');
    $ligne->execute([$empreinte]);

    return $ligne->fetchColumn();
})();

$apresGlissement = $requete('GET', '/api/v1/sessions/current', null, $jeton);
$expireEnBaseApresGlissement = (string) (static function () use ($fichiers, $empreinte): mixed {
    $ligne = (new PDO('sqlite:'.$fichiers['acces']))
        ->prepare('SELECT expire_le FROM session_ouverte WHERE jeton_empreinte = ?');
    $ligne->execute([$empreinte]);

    return $ligne->fetchColumn();
})();

$verifier(
    $apresGlissement['statut'] === 200
        && ($apresGlissement['corps']['expire_le'] ?? null) === $expireEnBaseApresGlissement
        && $expireEnBaseApresGlissement > $expireAvantGlissement,
    'après une vérification réelle qui glisse la session, expire_le restitué == expire_le persisté',
);

// --- Une fois la session révoquée, la lecture est refusée (pas de fuite
// d'une échéance obsolète).
$requete('DELETE', '/api/v1/sessions/current', null, $jeton);
$apresRevocation = $requete('GET', '/api/v1/sessions/current', null, $jeton);
$verifier(
    $apresRevocation['statut'] === 401,
    'après révocation, la lecture de la session courante est refusée',
);

echo "\n";
if ($echecs > 0) {
    printf("ÉCHEC : %d assertion(s) non vérifiée(s).\n", $echecs);
    exit(1);
}
echo "Preuve établie : GET /sessions/current restitue l'échéance attestée par le Core (CAP-002 DG Afrique).\n";
exit(0);
