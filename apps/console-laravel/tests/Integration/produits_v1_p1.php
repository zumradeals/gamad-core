<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet du registre des produits (CAP-CORE-011), depuis
 * l'inscription jusqu'au retrait, et son raccordement à la fédération
 * (CAP-CORE-022) qui le consomme.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/produits_v1_p1.php
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
$temp = sys_get_temp_dir() . '/gamad-produits-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'produits' => $temp . '-produits.sqlite',
    'sources' => $temp . '-sources.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('q', 32)),
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
$secretAutorite = 'Secret-Produits-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
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
    $serveur = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
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

echo "INTÉGRATION HTTP — PRODUITS V1 P1 (CAP-CORE-011)\n\n";

$REF = 'PRD-V1-P1-001';

// 1 — session non autorisée refusée.
$sansSession = $requete('POST', '/api/v1/produits', [
    'reference' => $REF, 'identite_reference' => 'X', 'nom_canonique' => 'X',
    'nom_affichage' => 'X', 'type_produit' => 'SATELLITE', 'proprietaire_reference' => 'X',
]);
$verifier(
    $sansSession['statut'] === 401
        && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'aucune inscription n’est possible sans session',
);

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$sessionAutorite = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($sessionAutorite !== '', 'l’autorité ouvre une session Core');

$identite = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'produit', 'libelle' => 'Produit V1 P1',
], $sessionAutorite);
$identiteRef = (string) ($identite['corps']['identite']['reference'] ?? '');
$verifier($identite['statut'] === 201, 'l’identité canonique du produit est résolue par CAP-CORE-001');

// 2, 3 — l'autorité inscrit un produit, visible en PREPARATION.
$inscription = $requete('POST', '/api/v1/produits', [
    'reference' => $REF,
    'identite_reference' => $identiteRef,
    'nom_canonique' => 'Produit V1 P1',
    'nom_affichage' => 'Produit V1 P1',
    'type_produit' => 'SATELLITE',
    'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$fiche = $requete('GET', "/api/v1/produits/{$REF}", null, $sessionAutorite);
$verifier(
    $inscription['statut'] === 201
        && ($inscription['corps']['resultat']['etat'] ?? null) === 'PREPARATION'
        && $fiche['statut'] === 200
        && ($fiche['corps']['produit']['etat'] ?? null) === 'PREPARATION',
    'l’autorité inscrit un produit, visible en PREPARATION',
);

$doublon = $requete('POST', '/api/v1/produits', [
    'reference' => $REF, 'identite_reference' => $identiteRef, 'nom_canonique' => 'X',
    'nom_affichage' => 'X', 'type_produit' => 'SATELLITE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$verifier(
    $doublon['statut'] === 409
        && ($doublon['corps']['resultat']['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    'une référence déjà inscrite est refusée avec un conflit HTTP',
);

// 4 — environnement de production déclaré.
$environnementDeclare = $requete('POST', "/api/v1/produits/{$REF}/environnements", [
    'environnement' => 'PRODUCTION',
    'api_base_url' => 'https://v1-p1.example/api',
    'audience_federation' => $REF,
], $sessionAutorite);
$httpRefuse = $requete('POST', "/api/v1/produits/{$REF}/environnements", [
    'environnement' => 'PRODUCTION',
    'api_base_url' => 'http://non-securise.example/api',
    'audience_federation' => $REF . '-AUTRE',
], $sessionAutorite);
$verifier(
    $environnementDeclare['statut'] === 201
        && ($environnementDeclare['corps']['resultat']['actif'] ?? null) === true
        && $httpRefuse['statut'] === 422
        && ($httpRefuse['corps']['resultat']['refus'] ?? null) === 'URL_INVALIDE',
    'un environnement de production est déclaré ; sans HTTPS, il est refusé',
);

// 5, 7 — activation ; fédération autorisée après autorisation explicite.
$avantAutorisation = $requete('POST', "/api/v1/produits/{$REF}/activation", [], $sessionAutorite);
$requete('PATCH', "/api/v1/produits/{$REF}", ['federation_autorisee' => true], $sessionAutorite);
$catalogueAvant = $requete('GET', '/api/v1/produits', null, $sessionAutorite);
$verifier(
    $avantAutorisation['statut'] === 200
        && ($avantAutorisation['corps']['resultat']['etat'] ?? null) === 'ACTIF'
        && (array_column($catalogueAvant['corps']['produits'] ?? [], 'federable', 'reference')[$REF] ?? null) === true,
    'l’activation réussit et le catalogue fédéré CAP-CORE-022 reflète le registre CAP-CORE-011',
);

// 6 — produit visible dans le catalogue actif.
$listeActive = array_column($catalogueAvant['corps']['produits'] ?? [], null, 'reference');
$verifier(
    ($listeActive[$REF]['etat'] ?? null) === 'ACTIF',
    'le produit apparaît dans le catalogue fédéré comme ACTIF',
);

// 8, 9 — suspension : nouvelle ouverture fédérée refusée.
$ouvertureAvantSuspension = $requete('POST', "/api/v1/produits/{$REF}/ouverture", [
    'identite' => 'AUT-GAMAD-001',
], $sessionAutorite);
$suspension = $requete('POST', "/api/v1/produits/{$REF}/suspension", [], $sessionAutorite);
$ouvertureApresSuspension = $requete('POST', "/api/v1/produits/{$REF}/ouverture", [
    'identite' => 'AUT-GAMAD-001',
], $sessionAutorite);
$verifier(
    $suspension['statut'] === 200
        && ($suspension['corps']['resultat']['etat'] ?? null) === 'SUSPENDU'
        && $ouvertureApresSuspension['statut'] === 422
        && ($ouvertureApresSuspension['corps']['resultat']['refus'] ?? null) === 'PRODUIT_NON_RECONNU',
    'la suspension ferme immédiatement toute nouvelle ouverture fédérée',
);

// 10 — historique toujours lisible après suspension.
$environnements = $requete('GET', "/api/v1/produits/{$REF}/environnements", null, $sessionAutorite);
$verifier(
    $fiche['statut'] === 200 && count($environnements['corps']['environnements'] ?? []) === 1,
    'la fiche et ses environnements restent lisibles après suspension',
);

// 11, 12 — retrait irréversible ; référence non réutilisable.
$retrait = $requete('POST', "/api/v1/produits/{$REF}/retrait", [], $sessionAutorite);
$ficheApresRetrait = $requete('GET', "/api/v1/produits/{$REF}", null, $sessionAutorite);
$reinscription = $requete('POST', '/api/v1/produits', [
    'reference' => $REF, 'identite_reference' => $identiteRef, 'nom_canonique' => 'Doublon',
    'nom_affichage' => 'Doublon', 'type_produit' => 'SATELLITE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$verifier(
    $retrait['statut'] === 200
        && ($retrait['corps']['resultat']['etat'] ?? null) === 'RETIRE'
        && $ficheApresRetrait['statut'] === 200
        && ($ficheApresRetrait['corps']['produit']['etat'] ?? null) === 'RETIRE'
        && $reinscription['statut'] === 409,
    'le retrait est irréversible ; la fiche reste lisible et sa référence n’est jamais réutilisable',
);

// Audit : le parcours est chaîné, sans secret.
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:' . $fichiers['journal']);
$produits = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE categorie = 'PRODUITS'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $produits >= 8,
    'le parcours des produits est chaîné dans l’audit CAP-CORE-013',
);

echo "\n";
if ($echecs === 0) {
    echo "Produits v1 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Produits v1 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
