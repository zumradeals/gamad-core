<?php

declare(strict_types=1);

/**
 * Parcours HTTP de l'API de Matching CAP-CORE-021 : authentification,
 * lecture de contexte, soumission de demande, exécution, résultat,
 * explication, refus de segment sous le seuil minimal, contestation et
 * réexamen, refus propre sur une référence inconnue.
 *
 * Le contexte et le profil de test sont semés directement via
 * `RegistreMatching` (même magasin SQLite que celui configuré pour la
 * requête HTTP) : il n'existe pas encore de route ni de commande
 * d'exploitation pour cette opération dans ce chantier — donnée de test
 * explicite, jamais fabriquée comme si elle venait d'un vrai consommateur.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/matching_v1_p1.php
 */

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\MoteurMatching\Magasin as MatchingMagasin;
use Gamad\MoteurMatching\RegistreMatching;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-matching-v1-' . getmypid();
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
    'matching' => $temp . '-matching.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('m', 32)),
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
    'MATCHING_REGISTRY_URL' => '', 'MATCHING_REGISTRY_PATH' => $fichiers['matching'],
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
$secretAutorite = 'Secret-Matching-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:contrats:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:matching:bootstrap-gouvernance');
$kernel = $app->make(Kernel::class);

// Semis direct du contexte et du profil de test — voir en-tête.
$registre = new RegistreMatching(MatchingMagasin::connecter());
$acteur = 'AUT-GAMAD-001';
$contexte = $registre->inscrireContexte([
    'code_canonique' => 'WASPLEX_AUDIENCE', 'nom' => 'Test HTTP', 'finalite' => 'Épreuve d’intégration HTTP',
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0', 'classification' => 'INTERNE',
    'supervision_humaine' => 'AUCUNE', 'score_autorise' => true, 'segment_autorise' => true,
    'activation_autorisee' => true, 'mesure_autorisee' => true, 'source_reference' => 'SRC-TEST',
], $acteur);
$registre->activerContexte($contexte['reference'], $acteur);
$profil = $registre->compilerProfil([
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0', 'contexte_reference' => $contexte['reference'],
    'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'criteres' => [[
        'critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'traitement_inconnu' => 'INDETERMINE',
        'obligatoire' => true, 'poids' => 1.0, 'sources_autorisees' => ['SRC-TEST'], 'facteur_public_autorise' => true,
    ]],
], $acteur);
$registre->activerProfil($profil['reference'], 'PRV-TEST-SIMULATION', $acteur);

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

echo "INTÉGRATION HTTP — MATCHING V1 P1 (CAP-CORE-021)\n\n";

$sansSession = $requete('GET', '/api/v1/matching/contextes');
$verifier($sansSession['statut'] === 401, 'lecture sans session refusée');

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$jeton = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($jeton !== '', 'l’autorité ouvre une session Core');

$contextes = $requete('GET', '/api/v1/matching/contextes', null, $jeton);
$verifier($contextes['statut'] === 200 && count($contextes['corps']['contextes'] ?? []) === 1, 'lecture des contextes via HTTP');

$lectureContexte = $requete('GET', "/api/v1/matching/contextes/{$contexte['reference']}", null, $jeton);
$verifier($lectureContexte['statut'] === 200 && ($lectureContexte['corps']['contexte']['etat'] ?? null) === 'ACTIF', 'lecture d’un contexte actif via HTTP');

$demande = $requete('POST', '/api/v1/matching/demandes', [
    'idempotency_key' => 'IDEMP-HTTP-001', 'consommateur_produit' => 'PRD-GAMAD-002', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'Épreuve d’intégration HTTP', 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST',
    'mode_resultat' => 'CLASSEMENT', 'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'correlation_id' => 'COR-HTTP-001',
    'objets' => [
        ['role_objet' => 'CANDIDAT', 'objet_type' => 'PERSONNE', 'objet_reference_externe' => 'CAND-HTTP-A', 'source_reference' => 'SRC-TEST', 'contrat_reference' => 'CTR-MAT-02', 'valide_depuis' => '2026-01-01T00:00:00Z', 'classification' => 'INTERNE'],
        ['role_objet' => 'CANDIDAT', 'objet_type' => 'PERSONNE', 'objet_reference_externe' => 'CAND-HTTP-B', 'source_reference' => 'SRC-TEST', 'contrat_reference' => 'CTR-MAT-02', 'valide_depuis' => '2026-01-01T00:00:00Z', 'classification' => 'INTERNE'],
    ],
    'criteres' => [['critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'valeur_normalisee' => 'ABJ', 'obligatoire' => true, 'origine' => 'POLITIQUE']],
], $jeton);
if ($demande['statut'] !== 201) {
    fwrite(STDERR, json_encode($demande, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
}
$demandeReference = (string) ($demande['corps']['resultat']['reference'] ?? '');
$verifier($demande['statut'] === 201 && $demandeReference !== '', 'soumission de demande acceptée via HTTP');

// Signaux semés directement (pas de route d'ingestion dans ce périmètre).
$registre->enregistrerSignal([
    'sujet_type' => 'PERSONNE', 'sujet_reference' => 'CAND-HTTP-A', 'signal_code' => 'CRT-REGION', 'valeur_type' => 'TEXTE',
    'valeur_normalisee' => 'ABJ', 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve d’intégration HTTP',
    'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
    'observation_le' => '2026-08-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
]);
$registre->enregistrerSignal([
    'sujet_type' => 'PERSONNE', 'sujet_reference' => 'CAND-HTTP-B', 'signal_code' => 'CRT-REGION', 'valeur_type' => 'TEXTE',
    'valeur_normalisee' => 'DKR', 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve d’intégration HTTP',
    'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
    'observation_le' => '2026-08-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
]);

$execution = $requete('POST', "/api/v1/matching/demandes/{$demandeReference}/execution", [], $jeton);
$executionReference = (string) ($execution['corps']['resultat']['execution'] ?? '');
$verifier($execution['statut'] === 200 && $executionReference !== '', 'exécution déclenchée via HTTP');

$resultatsReferences = $execution['corps']['resultat']['resultats'] ?? [];
$verifier(count($resultatsReferences) === 2, 'deux résultats produits par l’exécution HTTP');

$premierResultat = (string) ($resultatsReferences[0] ?? '');
$lectureResultat = $requete('GET', "/api/v1/matching/resultats/{$premierResultat}", null, $jeton);
$verifier($lectureResultat['statut'] === 200 && ($lectureResultat['corps']['resultat']['reference'] ?? null) === $premierResultat, 'lecture d’un résultat via HTTP');

$explication = $requete('GET', "/api/v1/matching/resultats/{$premierResultat}/explication", null, $jeton);
$verifier($explication['statut'] === 200 && ($explication['corps']['explication']['non_decisionnel'] ?? false) === true, 'explication d’un résultat via HTTP, non_decisionnel toujours vrai');

$segmentRefuse = $requete('POST', '/api/v1/matching/segments', ['demande_reference' => $demandeReference], $jeton);
$verifier(
    $segmentRefuse['statut'] === 422 && ($segmentRefuse['corps']['resultat']['refus'] ?? null) === 'MATCHING_POPULATION_TOO_SMALL',
    'construction de segment refusée via HTTP sous le seuil minimal (422, code exact)',
);

$activationInconnue = $requete('POST', '/api/v1/matching/segments/SEG-GAMAD-INEXISTANT/activation', [
    'consommateur_produit' => 'PRD-GAMAD-002', 'finalite_reference' => 'x', 'realm_reference' => 'RLM-GAMAD-001',
    'environnement' => 'TEST', 'contrat_reference' => 'CTR-MAT-08', 'contrat_version' => '1',
    'autorisation_reference' => 'AUT-X', 'usage_autorise' => 'TEST',
], $jeton);
$verifier($activationInconnue['statut'] === 404 && ($activationInconnue['corps']['resultat']['refus'] ?? null) === 'SEGMENT_INCONNU', 'activation sur un segment inconnu refusée proprement (404)');

// Contestation et réexamen (sans nouvelle exécution fournie : verdict ANNULE).
$candidatsIndex = [];
foreach (MatchingMagasin::connecter()->query("SELECT candidat_reference, sujet_reference FROM matching_candidat WHERE execution_reference = '{$executionReference}'")->fetchAll() as $c) {
    $candidatsIndex[$c['sujet_reference']] = $c['candidat_reference'];
}
$resultatsParCandidat = [];
foreach ($resultatsReferences as $r) {
    $ligne = $registre->resoudreResultat((string) $r);
    $resultatsParCandidat[$ligne['candidat_reference']] = $ligne;
}
$resultatB = $resultatsParCandidat[$candidatsIndex['CAND-HTTP-B']] ?? null;
$verifier($resultatB !== null && $resultatB['classe_resultat'] === 'NON_CORRESPONDANT', 'CAND-HTTP-B non correspondant (région différente) — préparation de la contestation');

$contestation = $requete('POST', '/api/v1/matching/contestations', [
    'resultat_reference' => $resultatB['reference'], 'contestant_reference' => 'IDN-CONTESTANT-HTTP', 'motif_code' => 'DESACCORD_CLASSEMENT',
    'realm_reference' => 'RLM-GAMAD-001', 'classification' => 'INTERNE', 'faits' => ['contestant_autorise' => true],
], $jeton);
$contestationReference = (string) ($contestation['corps']['resultat']['reference'] ?? '');
$verifier($contestation['statut'] === 201 && $contestationReference !== '', 'ouverture de contestation via HTTP');

$reexamen = $requete('POST', "/api/v1/matching/contestations/{$contestationReference}/reexecution", [], $jeton);
$verifier($reexamen['statut'] === 201 && ($reexamen['corps']['resultat']['verdict'] ?? null) === 'ANNULE', 'réexamen sans nouvelle exécution fournie : verdict ANNULE via HTTP');

$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$verifier($integrite['valide'] === true && $integrite['evenements'] >= 5, 'le parcours HTTP est chaîné dans l’audit CAP-CORE-013');

echo "\n";
if ($echecs === 0) {
    echo "Intégration HTTP Matching : ÉTABLIE.\n";
    exit(0);
}

echo "Intégration HTTP Matching : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
