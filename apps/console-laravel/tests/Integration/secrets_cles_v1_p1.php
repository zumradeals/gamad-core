<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet de l'API de métadonnées CAP-CORE-016, depuis
 * l'inscription jusqu'à la rotation et la compromission, en passant par la
 * vérification réelle d'un fournisseur fichier 0600 factice.
 *
 * Sert de consommateur de conformité pour la vérification serveur : le
 * fichier factice créé ici n'est jamais un secret réel, et aucune réponse
 * HTTP vérifiée ci-dessous ne doit jamais contenir son contenu.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/secrets_cles_v1_p1.php
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
$temp = sys_get_temp_dir() . '/gamad-secrets-v1-' . getmypid();
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
];
$dossierSecrets = $temp . '-fichiers';
foreach ($fichiers as $f) {
    @unlink($f);
}
@mkdir($dossierSecrets, 0700, true);
register_shutdown_function(static function () use ($fichiers, $dossierSecrets): void {
    foreach ($fichiers as $f) {
        @unlink($f);
    }
    foreach (glob($dossierSecrets . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dossierSecrets);
});

$environnement = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('s', 32)),
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
$secretAutorite = 'Secret-SecretsCles-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:contrats:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:secrets:bootstrap');
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

echo "INTÉGRATION HTTP — SECRETS & CLÉS V1 P1 (CAP-CORE-016)\n\n";

$sansSession = $requete('GET', '/api/v1/secrets-cles');
$verifier($sansSession['statut'] === 401, 'lecture sans session refusée');

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$jeton = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($jeton !== '', 'l’autorité ouvre une session Core');

$reference = 'SEC-GAMAD-P1-TEST';
$inscription = $requete('POST', '/api/v1/secrets-cles', [
    'reference' => $reference, 'nom' => 'Secret de test P1', 'type_secret' => 'CLE_CHIFFREMENT_SYMETRIQUE',
    'finalite_reference' => 'test P1', 'proprietaire_reference' => 'AUT-GAMAD-001',
    'source_reference' => 'SRC-GAMAD-CAP-CORE-016', 'environnement_reference' => 'CI',
    'classification_reference' => 'INTERNE',
], $jeton);
$verifier($inscription['statut'] === 201, 'inscription de la ressource acceptée');

$fournisseurRef = 'FOU-GAMAD-P1-TEST';
$cheminFichier = $dossierSecrets . '/secret-p1.txt';
file_put_contents($cheminFichier, "valeur-factice-p1-jamais-un-vrai-secret\n");
chmod($cheminFichier, 0600);
$inscriptionFournisseur = $requete('POST', '/api/v1/fournisseurs-secrets', [
    'reference' => $fournisseurRef, 'nom' => 'Fournisseur fichier P1', 'type_fournisseur' => 'FICHIER_0600',
    'environnement_reference' => 'CI', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $jeton);
$verifier(($inscriptionFournisseur['corps']['resultat']['reference'] ?? null) !== null, 'inscription du fournisseur fichier acceptée');

$version = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions", [
    'version' => '1', 'fournisseur_reference' => $fournisseurRef, 'handle_fournisseur' => $cheminFichier,
], $jeton);
$idVersion = (int) ($version['corps']['resultat']['id'] ?? 0);
$verifier($version['statut'] === 201 && $idVersion > 0, 'déclaration de version acceptée, sans valeur en retour');
$corpsVersionJson = json_encode($version, JSON_UNESCAPED_UNICODE);
$verifier(!str_contains(strtolower((string) $corpsVersionJson), 'valeur-factice'), 'la réponse de déclaration ne contient aucune valeur');

$verification = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions/1/verification", [], $jeton);
$verifier($verification['statut'] === 200, 'la vérification réelle du fichier 0600 réussit côté serveur');

$activation = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions/{$idVersion}/activation", [], $jeton);
$verifier(
    $activation['statut'] === 200 && ($activation['corps']['resultat']['etat'] ?? null) === 'ACTIVE_ECRITURE',
    'activation acceptée après vérification serveur réelle (aucun booléen client fait confiance)',
);

$fauxActivation = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions/9999999/activation", [], $jeton);
$verifier($fauxActivation['statut'] === 404, 'activer une version inconnue échoue proprement');

$usage = $requete('POST', "/api/v1/secrets-cles/{$reference}/usages", [
    'capacite_reference' => 'CAP-CORE-019', 'environnement_reference' => 'CI',
    'operation_reference' => 'op.test-p1', 'finalite_reference' => 'test P1', 'mode_usage' => 'CHIFFRER',
], $jeton);
$verifier($usage['statut'] === 201, 'déclaration d’usage acceptée');

$lecture = $requete('GET', "/api/v1/secrets-cles/{$reference}/versions", null, $jeton);
$verifier(
    $lecture['statut'] === 200 && !array_key_exists('handle_fournisseur', $lecture['corps']['versions'][0] ?? []),
    'la lecture des versions masque toujours le handle du fournisseur',
);

$suspension = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions/{$idVersion}/suspension", ['motif' => 'exercice P1'], $jeton);
$verifier($suspension['statut'] === 200 && ($suspension['corps']['resultat']['etat'] ?? null) === 'SUSPENDUE', 'suspension acceptée');

$revocation = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions/{$idVersion}/revocation", ['motif' => 'fin de test P1'], $jeton);
$verifier($revocation['statut'] === 200 && ($revocation['corps']['resultat']['etat'] ?? null) === 'REVOQUEE', 'révocation acceptée depuis SUSPENDUE');

// Deuxième version pour l'exercice de compromission et de rotation.
$purger();
$version2 = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions", [
    'version' => '2', 'fournisseur_reference' => $fournisseurRef, 'handle_fournisseur' => $cheminFichier,
], $jeton);
$idVersion2 = (int) ($version2['corps']['resultat']['id'] ?? 0);
$requete('POST', "/api/v1/secrets-cles/{$reference}/versions/2/verification", [], $jeton);
$requete('POST', "/api/v1/secrets-cles/{$reference}/versions/{$idVersion2}/activation", [], $jeton);

$compromission = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions/{$idVersion2}/compromission", [
    'niveau' => 'CONFIRMEE', 'source_reference' => 'SRC-GAMAD-CAP-CORE-016', 'motif' => 'exercice P1 — valeur factice',
], $jeton);
if ($compromission['statut'] !== 201) { fwrite(STDERR, json_encode($compromission, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL); }
$verifier($compromission['statut'] === 201, 'déclaration de compromission acceptée');
$corpsCompromissionJson = json_encode($compromission, JSON_UNESCAPED_UNICODE);
$verifier(!str_contains(strtolower((string) $corpsCompromissionJson), 'valeur-factice-p1-jamais'), 'la réponse de compromission ne contient aucune valeur du secret');

$refusReactivation = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions/{$idVersion2}/activation", [], $jeton);
$verifier($refusReactivation['statut'] !== 200, 'une version compromise ne peut pas être réactivée via l’API');

// Rotation : troisième version, plan, validation, exécution.
$purger();
$version3 = $requete('POST', "/api/v1/secrets-cles/{$reference}/versions", [
    'version' => '3', 'fournisseur_reference' => $fournisseurRef, 'handle_fournisseur' => $cheminFichier,
], $jeton);
$idVersion3 = (int) ($version3['corps']['resultat']['id'] ?? 0);

$plan = $requete('POST', "/api/v1/secrets-cles/{$reference}/rotations", [
    'strategie' => 'DOUBLE_LECTURE_ECRITURE_NOUVELLE', 'date_prevue' => gmdate('c'),
    'retour_arriere_autorise' => true, 'impact' => ['consommateurs' => ['CAP-CORE-019']],
    'nouvelle_version_id' => $idVersion3,
], $jeton);
$referencePlan = (string) ($plan['corps']['resultat']['reference'] ?? '');
if ($plan['statut'] !== 201) { fwrite(STDERR, json_encode($plan, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL); }
$verifier($plan['statut'] === 201 && $referencePlan !== '', 'planification de rotation acceptée');

$purger();
$validation = $requete('POST', "/api/v1/rotations-secrets/{$referencePlan}/validation", [], $jeton);
$verifier($validation['statut'] === 200 && ($validation['corps']['resultat']['etat'] ?? null) === 'VALIDE', 'validation du plan acceptée');

$execution = $requete('POST', "/api/v1/rotations-secrets/{$referencePlan}/execution", [
    'etape_reference' => 'activer-nouvelle-version', 'reussie' => true,
], $jeton);
$verifier($execution['statut'] === 200 && ($execution['corps']['resultat']['etat'] ?? null) === 'REUSSIE', 'exécution d’étape acceptée');

$diagnostic = $requete('GET', '/api/v1/secrets-cles/diagnostic', null, $jeton);
$verifier($diagnostic['statut'] === 200 && isset($diagnostic['corps']['registre']), 'le diagnostic de production répond');

$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$verifier($integrite['valide'] === true && $integrite['evenements'] >= 5, 'le parcours est chaîné dans l’audit CAP-CORE-013, sans valeur');

echo "\n";
if ($echecs === 0) {
    echo "Intégration HTTP secrets & clés : ÉTABLIE.\n";
    exit(0);
}

echo "Intégration HTTP secrets & clés : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
