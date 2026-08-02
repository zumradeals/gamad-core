<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet du registre des contrats (CAP-CORE-009), depuis
 * l'inscription jusqu'au retrait, en passant par la version, les parties,
 * l'opération, le schéma, la soumission, l'analyse de compatibilité
 * obligatoire, la conformité obligatoire et l'activation.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/contrats_v1_p1.php
 */

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-contrats-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'contrats' => $temp . '-contrats.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('z', 32)),
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
    'CONTRACT_REGISTRY_URL' => '',
    'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
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
PolitiquesMagasin::connecter();
$ctr16 = new Ctr16(AccesMagasin::connecter());
$secretAutorite = 'Secret-Contrats-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
// Bootstrappe `POL-CONTRATS-V1` (auto-gouvernance) et les treize contrats déjà
// exploités. Sans `POL-CONTRATS-V1`, toute écriture gouvernée sur ce registre,
// y compris depuis ce test, serait refusée par CTR-03.
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:contrats:bootstrap');
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

echo "INTÉGRATION HTTP — CONTRATS V1 P1 (CAP-CORE-009)\n\n";

$REF = 'CTR-V1-P1-TEST-001';

// 1 — sans session, aucune écriture n'est possible.
$sansSession = $requete('POST', '/api/v1/contrats', [
    'reference' => $REF, 'nom' => 'X', 'type_contrat' => 'HTTP_API', 'finalite_reference' => 'test',
    'producteur_capacite_reference' => 'CAP-CORE-009', 'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'x',
]);
$verifier(
    $sansSession['statut'] === 401
        && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'aucune inscription n’est possible sans session',
);

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$sessionAutorite = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($sessionAutorite !== '', 'l’autorité ouvre une session Core');

$identiteTiers = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'personne', 'libelle' => 'Tiers V1 P1 Contrats',
], $sessionAutorite);
$referenceTiers = (string) ($identiteTiers['corps']['identite']['reference'] ?? '');
$ctr16->inscrireAuthentificateur($referenceTiers, 'Secret-Tiers-Contrats-1!');
$sessionTiersReponse = $requete('POST', '/api/v1/sessions', [
    'entite' => $referenceTiers, 'secret' => 'Secret-Tiers-Contrats-1!',
]);
$sessionTiers = (string) ($sessionTiersReponse['corps']['jeton'] ?? '');
$verifier($sessionTiers !== '', 'le tiers ouvre aussi une session Core, sans aucun droit de gouvernance');

// 2 — la liste porte déjà les treize contrats repris.
$listeInitiale = $requete('GET', '/api/v1/contrats', null, $sessionAutorite);
$referencesInitiales = array_column($listeInitiale['corps']['contrats'] ?? [], 'reference');
$verifier(
    $listeInitiale['statut'] === 200
        && count($referencesInitiales) === 13
        && in_array('CTR-01', $referencesInitiales, true)
        && in_array('CTR-GAMAD-FEDERATION', $referencesInitiales, true),
    'la liste porte les treize contrats repris par le bootstrap',
);

// 3 — inscription gouvernée : naît sans version active.
$inscription = $requete('POST', '/api/v1/contrats', [
    'reference' => $REF, 'nom' => 'Contrat V1 P1', 'type_contrat' => 'HTTP_API', 'finalite_reference' => 'test HTTP',
    'producteur_capacite_reference' => 'CAP-CORE-009', 'proprietaire_reference' => 'AUT-GAMAD-001',
    'source_reference' => 'test HTTP CAP-CORE-009',
], $sessionAutorite);
$fiche = $requete('GET', "/api/v1/contrats/{$REF}", null, $sessionAutorite);
$verifier(
    $inscription['statut'] === 201
        && ($inscription['corps']['resultat']['reference'] ?? null) === $REF
        && $fiche['statut'] === 200
        && array_key_exists('version_active', $fiche['corps']['contrat'] ?? [])
        && $fiche['corps']['contrat']['version_active'] === null,
    'l’autorité inscrit un contrat, sans version active',
);

$doublon = $requete('POST', '/api/v1/contrats', [
    'reference' => $REF, 'nom' => 'Doublon', 'type_contrat' => 'HTTP_API', 'finalite_reference' => 'x',
    'producteur_capacite_reference' => 'CAP-CORE-009', 'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'x',
], $sessionAutorite);
$verifier(
    $doublon['statut'] === 409
        && ($doublon['corps']['resultat']['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    'une référence déjà inscrite est refusée avec un conflit HTTP',
);

// 4 — un tiers sans droit ne peut ni inscrire ni administrer.
$inscriptionTiers = $requete('POST', '/api/v1/contrats', [
    'reference' => 'CTR-V1-P1-INTERDIT', 'nom' => 'X', 'type_contrat' => 'HTTP_API', 'finalite_reference' => 'x',
    'producteur_capacite_reference' => 'CAP-CORE-009', 'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'x',
], $sessionTiers);
$verifier(
    $inscriptionTiers['statut'] === 403
        && ($inscriptionTiers['corps']['erreur'] ?? null) === 'AUTORISATION_REFUSEE',
    'un tiers sans droit ne peut pas inscrire de contrat : CTR-03 refuse',
);

// 5 — version, parties, opération, schéma, soumission : immuable ensuite.
$version = $requete('POST', "/api/v1/contrats/{$REF}/versions", ['version' => '1.0.0'], $sessionAutorite);
$verifier(
    $version['statut'] === 201 && ($version['corps']['resultat']['etat'] ?? null) === 'BROUILLON',
    'une version se crée en BROUILLON',
);

$partieProducteur = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/parties", [
    'role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-009',
], $sessionAutorite);
$partieConsommateur = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/parties", [
    'role' => 'CONSOMMATEUR', 'partie_type' => 'PRODUIT', 'partie_reference' => 'PRD-GAMAD-002',
], $sessionAutorite);
$verifier(
    $partieProducteur['statut'] === 201 && $partieConsommateur['statut'] === 201,
    'le producteur et le consommateur se déclarent sur la version en BROUILLON',
);

$operation = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/operations", [
    'reference_operation' => 'testerV1P1', 'type_operation' => 'INTERROGER', 'methode_http' => 'GET', 'chemin_http' => '/test-v1-p1',
], $sessionAutorite);
$verifier(
    $operation['statut'] === 201,
    'une opération se déclare sur la version en BROUILLON',
);

$schema = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/schemas", [
    'operation_reference' => 'testerV1P1', 'sens' => 'SORTIE', 'format' => 'JSON_SCHEMA',
    'contenu' => json_encode(['proprietes' => ['reference' => ['type' => 'string', 'requis' => true]]]),
], $sessionAutorite);
$verifier($schema['statut'] === 201, 'un schéma se déclare sur la version en BROUILLON');

// Les refus ANALYSE_MANQUANTE, CONFORMITE_MANQUANTE et VERSION_IMMUABLE sont
// déjà éprouvés exhaustivement par `contrats_p3.php` ; cette épreuve HTTP se
// concentre sur le parcours heureux et les codes HTTP, en restant sous la
// limite de débit partagée par toutes les routes POST gouvernées de l'API
// (`throttle:20,1`, par IP — voir `politiques_v1_p1.php`).
$soumission = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/soumission", [], $sessionAutorite);
$verifier(
    $soumission['statut'] === 200 && ($soumission['corps']['resultat']['etat'] ?? null) === 'EN_VALIDATION',
    'la soumission fige le contenu et transite vers EN_VALIDATION',
);

$analyse = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/analyse", [], $sessionAutorite);
$verifier(
    $analyse['statut'] === 201 && ($analyse['corps']['resultat']['resultat'] ?? null) === 'COMPATIBLE',
    'la première version d’un contrat s’analyse toujours COMPATIBLE',
);

$conformite = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/conformite", [
    'resultat' => 'CONFORME', 'artefact_reference' => 'commit:v1-p1-test',
], $sessionAutorite);
$verifier($conformite['statut'] === 201, 'la conformité s’enregistre pour cette version exacte');

$activation = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/activation", [], $sessionAutorite);
$verifier(
    $activation['statut'] === 200 && ($activation['corps']['resultat']['etat'] ?? null) === 'ACTIVE',
    'l’activation réussit une fois l’analyse et la conformité acquises',
);

// 7 — dépréciation puis suspension puis retrait, référence non réutilisable.
$depreciation = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/depreciation", [], $sessionAutorite);
$verifier(
    $depreciation['statut'] === 200 && ($depreciation['corps']['resultat']['etat'] ?? null) === 'DEPRECIEE',
    'la dépréciation transite vers DEPRECIEE',
);

$suspension = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/suspension", [], $sessionAutorite);
$verifier(
    $suspension['statut'] === 200 && ($suspension['corps']['resultat']['etat'] ?? null) === 'SUSPENDUE',
    'la suspension transite vers SUSPENDUE',
);

$retrait = $requete('POST', "/api/v1/contrats/{$REF}/versions/1.0.0/retrait", [], $sessionAutorite);
$verifier(
    $retrait['statut'] === 200 && ($retrait['corps']['resultat']['etat'] ?? null) === 'RETIREE',
    'le retrait transite vers RETIREE',
);

$reutilisation = $requete('POST', "/api/v1/contrats/{$REF}/versions", ['version' => '1.0.0'], $sessionAutorite);
$verifier(
    $reutilisation['statut'] === 409
        && ($reutilisation['corps']['resultat']['refus'] ?? null) === 'VERSION_DEJA_UTILISEE',
    'une référence de version retirée n’est jamais réutilisable',
);

$historique = $requete('GET', "/api/v1/contrats/{$REF}/historique", null, $sessionAutorite);
$verifier(
    $historique['statut'] === 200 && count($historique['corps']['historique'] ?? []) === 6,
    'l’historique restitue chaque transition traversée par cette version',
);

$consommateurs = $requete('GET', "/api/v1/contrats/{$REF}/consommateurs?version=1.0.0", null, $sessionAutorite);
$verifier(
    $consommateurs['statut'] === 200
        && count($consommateurs['corps']['consommateurs'] ?? []) === 1
        && $consommateurs['corps']['consommateurs'][0]['partie_reference'] === 'PRD-GAMAD-002',
    'les consommateurs déclarés sont restituables par version',
);

// Audit : le parcours est chaîné, sans secret.
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:' . $fichiers['journal']);
$evenements = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE categorie = 'CONTRATS'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $evenements >= 10,
    'le parcours des contrats est chaîné dans l’audit CAP-CORE-013',
);

echo "\n";
if ($echecs === 0) {
    echo "Contrats v1 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Contrats v1 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
