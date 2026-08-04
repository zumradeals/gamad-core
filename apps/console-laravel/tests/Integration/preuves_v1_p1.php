<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet de l'API de preuves d'intégrité CAP-CORE-015 : de
 * l'émission d'une empreinte bornée à la vérification, l'export d'un
 * paquet, sa contre-vérification, puis le cycle (suspension/révocation).
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/preuves_v1_p1.php
 */

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-preuves-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'organisations' => $temp . '-organisations.sqlite',
    'produits' => $temp . '-produits.sqlite',
    'contrats' => $temp . '-contrats.sqlite',
    'vocabulaire' => $temp . '-vocabulaire.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'realms' => $temp . '-realms.sqlite',
    'sources' => $temp . '-sources.sqlite',
    'evenements' => $temp . '-evenements.sqlite',
    'secrets' => $temp . '-secrets.sqlite',
    'preuves' => $temp . '-preuves.sqlite',
];
foreach ($fichiers as $f) {
    @unlink($f);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $f) {
        @unlink($f);
    }
});

$environnement = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('p', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php', 'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php', 'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'errorlog',
    'DATABASE_URL' => '', 'SQLITE_PATH' => $fichiers['index'],
    'MAGASIN_URL' => '', 'MAGASIN_PATH' => $fichiers['acces'],
    'IDENTITY_REGISTRY_URL' => '', 'IDENTITY_REGISTRY_PATH' => $fichiers['identites'],
    'JOURNAL_OPERATIONNEL_URL' => '', 'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
    'ORGANIZATION_REGISTRY_URL' => '', 'ORGANIZATION_REGISTRY_PATH' => $fichiers['organisations'],
    'PRODUCT_REGISTRY_URL' => '', 'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'CONTRACT_REGISTRY_URL' => '', 'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
    'VOCABULARY_REGISTRY_URL' => '', 'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
    'POLICY_REGISTRY_URL' => '', 'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
    'REALM_REGISTRY_URL' => '', 'REALM_REGISTRY_PATH' => $fichiers['realms'],
    'SOURCE_REGISTRY_URL' => '', 'SOURCE_REGISTRY_PATH' => $fichiers['sources'],
    'EVENT_JOURNAL_URL' => '', 'EVENT_JOURNAL_PATH' => $fichiers['evenements'],
    'SECRET_REGISTRY_URL' => '', 'SECRET_REGISTRY_PATH' => $fichiers['secrets'],
    'PROOF_REGISTRY_URL' => '', 'PROOF_REGISTRY_PATH' => $fichiers['preuves'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';

BaselineOperationnelle::standard()->reconstruire(Db::connect());
IdentiteMagasin::connecter();
JournalMagasin::connecter();
$ctr16 = new Ctr16(AccesMagasin::connecter());
$secretAutorite = 'Secret-Preuves-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:contrats:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:preuves:bootstrap');
$kernel = $app->make(Kernel::class);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};
$requete = static function (string $methode, string $uri, ?array $json = null, ?string $jeton = null) use ($kernel): array {
    $serveur = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
    if ($jeton !== null) {
        $serveur['HTTP_AUTHORIZATION'] = 'Bearer ' . $jeton;
    }
    $request = Request::create($uri, $methode, [], [], [], $serveur, $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR));
    $response = $kernel->handle($request);
    $corps = json_decode((string) $response->getContent(), true);
    $resultat = ['statut' => $response->getStatusCode(), 'corps' => is_array($corps) ? $corps : []];
    $kernel->terminate($request, $response);

    return $resultat;
};
$purger = static function () use ($app): void {
    $app->make('cache.store')->flush();
};

echo "INTÉGRATION HTTP — PREUVES D'INTÉGRITÉ V1 P1 (CAP-CORE-015)\n\n";

$sansSession = $requete('GET', '/api/v1/preuves');
$verifier($sansSession['statut'] === 401, 'lecture sans session refusée');

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$jeton = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($jeton !== '', 'l’autorité ouvre une session Core');

$contenu = json_encode(['champ' => 'valeur de test P1', 'ordre' => 1], JSON_UNESCAPED_UNICODE);
$emission = $requete('POST', '/api/v1/preuves', [
    'sujet_type' => 'TEST_P1', 'sujet_reference' => 'sujet-preuve-p1',
    'producteur_capacite_reference' => 'CAP-CORE-015', 'realm_reference' => 'RLM-GAMAD-CORE',
    'finalite_reference' => 'TEST_INTEGRATION_P1', 'source_reference' => 'preuves_v1_p1.php',
    'classification' => 'INTERNE', 'algorithme' => 'SHA-256', 'contenu_json' => $contenu,
], $jeton);
if ($emission['statut'] !== 201) { fwrite(STDERR, json_encode($emission, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL); }
$reference = (string) ($emission['corps']['resultat']['reference'] ?? '');
$verifier($emission['statut'] === 201 && $reference !== '', 'émission d’empreinte bornée acceptée');

$lecture = $requete('GET', "/api/v1/preuves/{$reference}", null, $jeton);
$verifier($lecture['statut'] === 200 && ($lecture['corps']['preuve']['reference'] ?? null) === $reference, 'lecture de la preuve émise');

$etat = $requete('GET', "/api/v1/preuves/{$reference}/etat", null, $jeton);
$verifier($etat['statut'] === 200 && in_array($etat['corps']['etat'] ?? null, ['EMISE', 'ACTIVE'], true), 'lecture de l’état du cycle');

$empreintes = $requete('GET', "/api/v1/preuves/{$reference}/empreintes", null, $jeton);
$empreinteHex = (string) ($empreintes['corps']['empreintes'][0]['empreinte_hex'] ?? '');
$verifier($empreintes['statut'] === 200 && $empreinteHex !== '', 'lecture des empreintes émises');

$empreinteAttendue = hash('sha256', $contenu);
$verifier($empreinteHex === $empreinteAttendue, 'l’empreinte émise correspond exactement à SHA-256(contenu)');

$verifOk = $requete('POST', "/api/v1/preuves/{$reference}/verification", ['empreinte_presentee' => $empreinteHex], $jeton);
$verifier($verifOk['statut'] === 200 && ($verifOk['corps']['resultat']['resultat'] ?? null) === 'VALIDE', 'vérification avec l’empreinte correcte renvoie VALIDE');

$verifDivergente = $requete('POST', "/api/v1/preuves/{$reference}/verification", ['empreinte_presentee' => str_repeat('0', 64)], $jeton);
$verifier(
    $verifDivergente['statut'] === 200 && ($verifDivergente['corps']['resultat']['resultat'] ?? null) === 'EMPREINTE_DIVERGENTE',
    'vérification avec une empreinte falsifiée renvoie EMPREINTE_DIVERGENTE, pas une erreur HTTP',
);

$inconnue = $requete('GET', '/api/v1/preuves/PRF-GAMAD-INCONNUE', null, $jeton);
$verifier($inconnue['statut'] === 404, 'lecture d’une preuve inconnue échoue proprement');

$purger();
$export = $requete('POST', "/api/v1/preuves/{$reference}/export", ['profil' => 'VERIFICATION_INTERNE'], $jeton);
$paquetContenu = $export['corps']['resultat']['contenu'] ?? null;
$verifier($export['statut'] === 201 && is_array($paquetContenu), 'export d’un paquet vérifiable accepté');

$verifPaquet = $requete('POST', '/api/v1/preuves/verification-paquet', $paquetContenu, $jeton);
$verifier(
    $verifPaquet['statut'] === 200 && ($verifPaquet['corps']['resultat']['resultat'] ?? null) === 'VALIDE'
    && ($verifPaquet['corps']['resultat']['paquet_utilisable'] ?? false) === true,
    'le paquet exporté se vérifie lui-même comme utilisable',
);

$paquetTampere = $paquetContenu;
$paquetTampere['etat'] = 'REVOQUEE';
$purger();
$verifPaquetTampere = $requete('POST', '/api/v1/preuves/verification-paquet', $paquetTampere, $jeton);
$verifier(
    ($verifPaquetTampere['corps']['resultat']['resultat'] ?? null) === 'PAQUET_DIVERGENT',
    'un paquet dont un champ a été modifié après export est détecté comme divergent',
);

$corpsPaquetJson = json_encode($verifPaquet, JSON_UNESCAPED_UNICODE);
$verifier(!str_contains(strtolower((string) $corpsPaquetJson), 'private') && !str_contains((string) $corpsPaquetJson, 'cle_privee'), 'aucune clé privée dans la réponse de vérification de paquet');

$purger();
$suspension = $requete('POST', "/api/v1/preuves/{$reference}/suspension", ['motif_code' => 'DOUTE_TEMPORAIRE', 'motif_detail' => 'exercice P1'], $jeton);
if ($suspension['statut'] !== 200) { fwrite(STDERR, json_encode($suspension, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL); }
$verifier($suspension['statut'] === 200 && ($suspension['corps']['resultat']['etat'] ?? null) === 'SUSPENDUE', 'suspension acceptée');

$revocation = $requete('POST', "/api/v1/preuves/{$reference}/revocation", ['motif_code' => 'FIN_DE_VIE', 'motif_detail' => 'fin de test P1'], $jeton);
$verifier($revocation['statut'] === 200 && ($revocation['corps']['resultat']['etat'] ?? null) === 'REVOQUEE', 'révocation acceptée depuis SUSPENDUE');

$refusReactivation = $requete('POST', "/api/v1/preuves/{$reference}/suspension", ['motif_code' => 'TENTATIVE', 'motif_detail' => 'tentative après révocation'], $jeton);
$verifier($refusReactivation['statut'] !== 200, 'une preuve révoquée ne peut pas être suspendue à nouveau');

$diagnostic = $requete('GET', '/api/v1/preuves/diagnostic', null, $jeton);
$verifier($diagnostic['statut'] === 200 && isset($diagnostic['corps']['registre']), 'le diagnostic répond');

$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$verifier($integrite['valide'] === true && $integrite['evenements'] >= 5, 'le parcours est chaîné dans l’audit CAP-CORE-013');

echo "\n";
if ($echecs === 0) {
    echo "Intégration HTTP preuves d'intégrité : ÉTABLIE.\n";
    exit(0);
}

echo "Intégration HTTP preuves d'intégrité : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
