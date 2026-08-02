<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet du registre des sources (CAP-CORE-006), depuis
 * l'inscription jusqu'au retrait, en passant par les finalités, les
 * vérifications et la lignée.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/sources_v1_p1.php
 */

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-sources-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'produits' => $temp . '-produits.sqlite',
    'sources' => $temp . '-sources.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
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
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
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
ProduitsMagasin::connecter();
$ctr16 = new Ctr16(AccesMagasin::connecter());
$secretAutorite = 'Secret-Sources-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

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

echo "INTÉGRATION HTTP — SOURCES V1 P1 (CAP-CORE-006)\n\n";

$REF = 'SRC-V1-P1-001';

// 1 — session non autorisée refusée.
$sansSession = $requete('POST', '/api/v1/sources', [
    'reference' => $REF, 'nom_canonique' => 'X', 'nom_affichage' => 'X',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => 'X',
]);
$verifier(
    $sansSession['statut'] === 401
        && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'aucune inscription n’est possible sans session',
);

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$sessionAutorite = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($sessionAutorite !== '', 'l’autorité ouvre une session Core');

// 2 — un produit consommateur réel et ACTIF (CAP-CORE-011), pour les finalités.
$identiteConsommateur = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'produit', 'libelle' => 'Consommateur V1 P1',
], $sessionAutorite);
$idConsommateur = (string) ($identiteConsommateur['corps']['identite']['reference'] ?? '');
$REF_PRODUIT = 'PRD-V1-P1-CONSOMMATEUR';
$requete('POST', '/api/v1/produits', [
    'reference' => $REF_PRODUIT, 'identite_reference' => $idConsommateur,
    'nom_canonique' => 'Consommateur V1 P1', 'nom_affichage' => 'Consommateur V1 P1',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$activationProduit = $requete('POST', "/api/v1/produits/{$REF_PRODUIT}/activation", [], $sessionAutorite);
$verifier(
    ($activationProduit['corps']['resultat']['etat'] ?? null) === 'ACTIF',
    'un produit consommateur réel est inscrit et activé pour servir les finalités',
);

// 3, 4 — l'autorité inscrit une source, visible en PREPARATION.
$inscription = $requete('POST', '/api/v1/sources', [
    'reference' => $REF,
    'nom_canonique' => 'Source V1 P1',
    'nom_affichage' => 'Source V1 P1',
    'type_source' => 'SERVICE_CORE',
    'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$fiche = $requete('GET', "/api/v1/sources/{$REF}", null, $sessionAutorite);
$verifier(
    $inscription['statut'] === 201
        && ($inscription['corps']['resultat']['etat'] ?? null) === 'PREPARATION'
        && $fiche['statut'] === 200
        && ($fiche['corps']['source']['etat'] ?? null) === 'PREPARATION',
    'l’autorité inscrit une source, visible en PREPARATION',
);

$doublon = $requete('POST', '/api/v1/sources', [
    'reference' => $REF, 'nom_canonique' => 'X', 'nom_affichage' => 'X',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$verifier(
    $doublon['statut'] === 409
        && ($doublon['corps']['resultat']['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    'une référence déjà inscrite est refusée avec un conflit HTTP',
);

// 5 — activation.
$activation = $requete('POST', "/api/v1/sources/{$REF}/activation", [], $sessionAutorite);
$verifier(
    $activation['statut'] === 200 && ($activation['corps']['resultat']['etat'] ?? null) === 'ACTIVE',
    'l’activation gouvernée réussit',
);

// 6, 7 — finalité déclarée pour le consommateur réel ; utilisabilité vraie
// pour lui, fausse pour un autre.
$finalite = $requete('POST', "/api/v1/sources/{$REF}/finalites", [
    'finalite_reference' => 'FIN-V1-P1', 'produit_consommateur_reference' => $REF_PRODUIT,
    'date_debut' => '2026-01-01',
], $sessionAutorite);
$utilisableBonConsommateur = $requete('POST', "/api/v1/sources/{$REF}/utilisabilite", [
    'consommateur' => $REF_PRODUIT, 'finalite' => 'FIN-V1-P1',
], $sessionAutorite);
$utilisableAutreConsommateur = $requete('POST', "/api/v1/sources/{$REF}/utilisabilite", [
    'consommateur' => 'PRD-AUTRE', 'finalite' => 'FIN-V1-P1',
], $sessionAutorite);
$verifier(
    $finalite['statut'] === 201
        && $utilisableBonConsommateur['corps']['utilisable'] === true
        && $utilisableAutreConsommateur['corps']['utilisable'] === false
        && in_array('FINALITE_NON_DECLAREE', $utilisableAutreConsommateur['corps']['motifs'] ?? [], true),
    'une finalité déclarée pour un consommateur précis ne s’étend pas à un autre',
);

// 8 — vérification enregistrée, auto-attestation refusée.
$autoAttestation = $requete('POST', "/api/v1/sources/{$REF}/verifications", [
    'niveau' => 'ATTESTEE', 'resultat' => 'VALIDE', 'verifie_par_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$verification = $requete('POST', "/api/v1/sources/{$REF}/verifications", [
    'niveau' => 'CONTROLEE', 'resultat' => 'VALIDE', 'verifie_par_reference' => 'AUT-TIERS-V1-P1',
], $sessionAutorite);
$verifier(
    $autoAttestation['statut'] === 422
        && ($autoAttestation['corps']['resultat']['refus'] ?? null) === 'AUTO_ATTESTATION_INTERDITE'
        && $verification['statut'] === 201,
    'une source ne peut pas s’auto-attester ; une vérification tierce réussit',
);

// 9 — lignée déclarée, cycle refusé.
$REF_PARENTE = 'SRC-V1-P1-PARENTE';
$requete('POST', '/api/v1/sources', [
    'reference' => $REF_PARENTE, 'nom_canonique' => 'Parente V1 P1', 'nom_affichage' => 'Parente V1 P1',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$requete('POST', "/api/v1/sources/{$REF_PARENTE}/activation", [], $sessionAutorite);
$lignee = $requete('POST', "/api/v1/sources/{$REF}/lignee", [
    'source_parente_reference' => $REF_PARENTE, 'type_relation' => 'DERIVEE_DE',
], $sessionAutorite);
$cycleRefuse = $requete('POST', "/api/v1/sources/{$REF_PARENTE}/lignee", [
    'source_parente_reference' => $REF, 'type_relation' => 'DERIVEE_DE',
], $sessionAutorite);
$verifier(
    $lignee['statut'] === 201
        && $cycleRefuse['statut'] === 422
        && ($cycleRefuse['corps']['resultat']['refus'] ?? null) === 'CYCLE_LIGNEE_INTERDIT',
    'la lignée se déclare ; toute relation qui fermerait un cycle est refusée',
);

// 10, 11 — suspension puis retrait irréversible ; référence non réutilisable.
$suspension = $requete('POST', "/api/v1/sources/{$REF}/suspension", [], $sessionAutorite);
$retrait = $requete('POST', "/api/v1/sources/{$REF}/retrait", [], $sessionAutorite);
$ficheApresRetrait = $requete('GET', "/api/v1/sources/{$REF}", null, $sessionAutorite);
$reinscription = $requete('POST', '/api/v1/sources', [
    'reference' => $REF, 'nom_canonique' => 'Doublon', 'nom_affichage' => 'Doublon',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$verifier(
    $suspension['statut'] === 200
        && ($suspension['corps']['resultat']['etat'] ?? null) === 'SUSPENDUE'
        && $retrait['statut'] === 200
        && ($retrait['corps']['resultat']['etat'] ?? null) === 'RETIREE'
        && $ficheApresRetrait['statut'] === 200
        && ($ficheApresRetrait['corps']['source']['etat'] ?? null) === 'RETIREE'
        && $reinscription['statut'] === 409,
    'le retrait est irréversible ; la fiche reste lisible et sa référence n’est jamais réutilisée',
);

// Audit : le parcours est chaîné, sans secret.
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:' . $fichiers['journal']);
$evenements = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE categorie = 'SOURCES'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $evenements >= 8,
    'le parcours des sources est chaîné dans l’audit CAP-CORE-013',
);

echo "\n";
if ($echecs === 0) {
    echo "Sources v1 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Sources v1 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
