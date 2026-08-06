<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-021 — orchestration persistante
 * (`RegistreMatching`), qui relie les classes pures déjà éprouvées par
 * `matching_p3.php` au magasin réel (doc de chantier 02 et 03).
 *
 * Éprouve le parcours complet sur un magasin SQLite en mémoire : contexte,
 * profil compilé et activé, demande, signaux, exécution, résultat,
 * explication, segment, appartenance, activation, mesure, contestation et
 * réexamen. Données synthétiques uniquement — aucune donnée de production.
 *
 * Exécution : php core/moteur-matching/tests/matching_p4.php
 * Code de sortie : 0 si toutes les épreuves passent.
 */

require __DIR__ . '/../../registre-preuves/src/Canonicaliseur.php';
require __DIR__ . '/../../evenements-sortants/src/SchemaOutbox.php';
require __DIR__ . '/../../evenements-sortants/src/OutboxProducteur.php';
require __DIR__ . '/../src/PolitiqueMatching.php';
require __DIR__ . '/../src/ExceptionMatching.php';
require __DIR__ . '/../src/Apparieur.php';
require __DIR__ . '/../src/EvaluateurDeterministe.php';
require __DIR__ . '/../src/Classement.php';
require __DIR__ . '/../src/CompilateurPolitique.php';
require __DIR__ . '/../src/Segments.php';
require __DIR__ . '/../src/Explication.php';
require __DIR__ . '/../src/Activation.php';
require __DIR__ . '/../src/Mesure.php';
require __DIR__ . '/../src/Contestations.php';
require __DIR__ . '/../src/ResolutionSources.php';
require __DIR__ . '/../src/SchemaMatching.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/RegistreMatching.php';

use Gamad\MoteurMatching\Magasin;
use Gamad\MoteurMatching\RegistreMatching;

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
        echo "         (contexte : voir sortie ci-dessus)\n";
    }
};

echo "GARDE — ORCHESTRATION PERSISTANTE DU MATCHING (CAP-CORE-021)\n\n";

$pdo = Magasin::connecter(':memory:');
$registre = new RegistreMatching($pdo);
$acteur = 'IDN-TEST-OPERATEUR';

// ----------------------------------------------------------------------
echo "Contexte et profil\n";

$contexte = $registre->inscrireContexte([
    'code_canonique' => 'WASPLEX_AUDIENCE', 'nom' => 'Test — audience', 'finalite' => 'Épreuve automatisée',
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0', 'classification' => 'INTERNE',
    'supervision_humaine' => 'AUCUNE', 'score_autorise' => true, 'segment_autorise' => true,
    'activation_autorisee' => true, 'mesure_autorisee' => true, 'source_reference' => 'SRC-TEST',
], $acteur);
$verifier(!isset($contexte['refus']) && $contexte['etat'] === 'PREPARATION', 'contexte inscrit en PREPARATION');

$contexteInconnu = $registre->inscrireContexte(['code_canonique' => 'CODE_INVENTE'], $acteur);
$verifier(($contexteInconnu['refus'] ?? null) === 'MATCHING_CONTEXT_UNKNOWN', 'code de contexte hors liste close refusé');

$activationContexte = $registre->activerContexte($contexte['reference'], $acteur);
$verifier(($activationContexte['etat'] ?? null) === 'ACTIF', 'contexte activé');

$specification = [
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0', 'contexte_reference' => $contexte['reference'],
    'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'criteres' => [
        [
            'critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'traitement_inconnu' => 'INDETERMINE',
            'obligatoire' => true, 'exclusion_dure' => true, 'sources_autorisees' => ['SRC-TEST'], 'facteur_public_autorise' => true,
        ],
        [
            'critere_reference' => 'CRT-SCORE', 'operateur' => 'GTE', 'traitement_inconnu' => 'INDETERMINE',
            'obligatoire' => false, 'poids' => 1.0, 'sources_autorisees' => ['SRC-TEST'], 'facteur_public_autorise' => true,
        ],
    ],
];
$profil = $registre->compilerProfil($specification, $acteur);
$verifier(!isset($profil['refus']) && $profil['etat'] === 'COMPILE', 'profil compilé (deux critères)');

$refusActivationSansSimulation = $registre->activerProfil($profil['reference'], '', $acteur);
$verifier(($refusActivationSansSimulation['refus'] ?? null) === 'SIMULATION_ABSENTE', 'activation refusée sans preuve de simulation');

$profilActif = $registre->activerProfil($profil['reference'], 'PRV-TEST-SIMULATION', $acteur);
$verifier(($profilActif['etat'] ?? null) === 'ACTIF', 'profil activé avec preuve de simulation fournie');
$verifier($registre->resoudreProfilActif($contexte['reference'])['reference'] === $profil['reference'], 'résolution du profil actif par contexte');

// ----------------------------------------------------------------------
echo "\nDemande, signaux et exécution\n";

function objetCandidat(string $ref): array
{
    return [
        'role_objet' => 'CANDIDAT', 'objet_type' => 'PERSONNE', 'objet_reference_externe' => $ref,
        'source_reference' => 'SRC-TEST', 'contrat_reference' => 'CTR-MAT-02', 'valide_depuis' => '2026-01-01T00:00:00Z',
        'classification' => 'INTERNE',
    ];
}

$demande = $registre->soumettreDemande([
    'idempotency_key' => 'IDEMP-P4-001', 'consommateur_produit' => 'PRD-GAMAD-002', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'Épreuve automatisée', 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST',
    'mode_resultat' => 'CLASSEMENT', 'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'correlation_id' => 'COR-P4-001',
    'objets' => [objetCandidat('CAND-A'), objetCandidat('CAND-B')],
    'criteres' => [
        ['critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'valeur_normalisee' => 'ABJ', 'obligatoire' => true, 'origine' => 'POLITIQUE'],
        ['critere_reference' => 'CRT-SCORE', 'operateur' => 'GTE', 'valeur_normalisee' => 50, 'poids_effectif' => 1.0, 'origine' => 'POLITIQUE'],
    ],
], $acteur);
$verifier(!isset($demande['refus']) && $demande['etat'] === 'SOUMISE', 'demande soumise avec deux candidats');

$demandeIdempotente = $registre->soumettreDemande(['idempotency_key' => 'IDEMP-P4-001'], $acteur);
$verifier(($demandeIdempotente['idempotent'] ?? false) === true && $demandeIdempotente['reference'] === $demande['reference'], 'rejeu de la même idempotency_key : aucun doublon');

$critereInconnu = $registre->soumettreDemande([
    'idempotency_key' => 'IDEMP-P4-CRIT-INCONNU', 'consommateur_produit' => 'PRD-GAMAD-002', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'x', 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST', 'mode_resultat' => 'CLASSEMENT',
    'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1', 'correlation_id' => 'COR-X',
    'objets' => [objetCandidat('CAND-Z')], 'criteres' => [['critere_reference' => 'CRT-INVENTE', 'operateur' => 'EQ', 'origine' => 'CONSOMMATEUR_AUTORISE']],
], $acteur);
$verifier(($critereInconnu['refus'] ?? null) === 'MATCHING_CRITERION_NOT_ALLOWED', 'un critère absent du profil actif ne peut pas être ajouté par le consommateur');

$registre->enregistrerSignal([
    'sujet_type' => 'PERSONNE', 'sujet_reference' => 'CAND-A', 'signal_code' => 'CRT-REGION', 'valeur_type' => 'TEXTE',
    'valeur_normalisee' => 'ABJ', 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve automatisée',
    'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
    'observation_le' => '2026-08-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
]);
$registre->enregistrerSignal([
    'sujet_type' => 'PERSONNE', 'sujet_reference' => 'CAND-A', 'signal_code' => 'CRT-SCORE', 'valeur_type' => 'NOMBRE',
    'valeur_normalisee' => 80, 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve automatisée',
    'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
    'observation_le' => '2026-08-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
]);
$registre->enregistrerSignal([
    'sujet_type' => 'PERSONNE', 'sujet_reference' => 'CAND-B', 'signal_code' => 'CRT-REGION', 'valeur_type' => 'TEXTE',
    'valeur_normalisee' => 'DKR', 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve automatisée',
    'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
    'observation_le' => '2026-08-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
]);
// CAND-B n'a aucun signal CRT-SCORE : non établi, mais l'exclusion dure sur CRT-REGION tranche avant.

$refusExecutionAvantValidation = $registre->executer($demande['reference'], [], $acteur, '2026-08-15T00:00:00Z');
$verifier(!isset($refusExecutionAvantValidation['refus']), 'exécution possible dès SOUMISE (VALIDEE non systématiquement requise)');
$premiereExecution = $refusExecutionAvantValidation;
$verifier(($premiereExecution['etat'] ?? null) === 'TERMINEE' && ($premiereExecution['candidats_evalues'] ?? null) === 2, 'exécution terminée, deux candidats évalués');

$resultats = $registre->listerResultats($premiereExecution['execution']);
$verifier(count($resultats) === 2, 'deux résultats produits');

$resultatA = null;
$resultatB = null;
// Résolution explicite candidat -> résultat via matching_candidat (sujet_reference).
$stmt = $pdo->prepare('SELECT candidat_reference, sujet_reference FROM matching_candidat WHERE execution_reference = ?');
$stmt->execute([$premiereExecution['execution']]);
$candidatsIndex = [];
foreach ($stmt->fetchAll() as $c) {
    $candidatsIndex[$c['sujet_reference']] = $c['candidat_reference'];
}
foreach ($resultats as $r) {
    if ($r['candidat_reference'] === $candidatsIndex['CAND-A']) {
        $resultatA = $r;
    }
    if ($r['candidat_reference'] === $candidatsIndex['CAND-B']) {
        $resultatB = $r;
    }
}
$verifier($resultatA !== null && $resultatA['classe_resultat'] === 'CORRESPONDANCE_FORTE' && (float) $resultatA['pertinence'] === 1.0, 'CAND-A : région et score satisfaits — CORRESPONDANCE_FORTE, pertinence 1.0');
$verifier($resultatB !== null && $resultatB['classe_resultat'] === 'NON_CORRESPONDANT' && $resultatB['pertinence'] === null, 'CAND-B : région défavorable sur exclusion dure — NON_CORRESPONDANT, pertinence non calculée');
$verifier((int) $resultatA['rang'] === 1 && $resultatB['rang'] === null, 'classement : seul le candidat admissible reçoit un rang');

$explicationA = $registre->expliquerResultat($resultatA['reference'], '2026-08-15T00:00:00Z');
$verifier(in_array('CRT-REGION', $explicationA['facteurs_favorables'] ?? [], true) && in_array('CRT-SCORE', $explicationA['facteurs_favorables'] ?? [], true), 'explication : deux facteurs favorables publics projetés');
$verifier(($explicationA['non_decisionnel'] ?? false) === true, 'explication : non_decisionnel toujours vrai');

$explicationExpiree = $registre->expliquerResultat($resultatA['reference'], '2030-01-01T00:00:00Z');
$verifier(($explicationExpiree['refus'] ?? null) === 'MATCHING_RESULT_EXPIRED', 'un résultat expiré n’est jamais projeté comme actuel');

// ----------------------------------------------------------------------
echo "\nSegment : population insuffisante puis population suffisante\n";

$segmentPetitePopulation = $registre->construireSegment($demande['reference'], [], $acteur, '2026-08-15T00:00:00Z');
$verifier(($segmentPetitePopulation['refus'] ?? null) === 'MATCHING_POPULATION_TOO_SMALL', 'segment refusé plutôt que fabriqué sous le seuil minimal');

$objetsLot = [];
$criteresLot = [
    ['critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'valeur_normalisee' => 'ABJ', 'obligatoire' => true, 'origine' => 'POLITIQUE'],
    ['critere_reference' => 'CRT-SCORE', 'operateur' => 'GTE', 'valeur_normalisee' => 50, 'poids_effectif' => 1.0, 'origine' => 'POLITIQUE'],
];
for ($i = 1; $i <= 30; $i++) {
    $objetsLot[] = objetCandidat("CAND-LOT-{$i}");
}
$demandeLot = $registre->soumettreDemande([
    'idempotency_key' => 'IDEMP-P4-LOT', 'consommateur_produit' => 'PRD-GAMAD-002', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'Épreuve automatisée', 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST',
    'mode_resultat' => 'SEGMENT_PROTEGE', 'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'correlation_id' => 'COR-P4-LOT', 'objets' => $objetsLot, 'criteres' => $criteresLot,
], $acteur);
for ($i = 1; $i <= 30; $i++) {
    $registre->enregistrerSignal([
        'sujet_type' => 'PERSONNE', 'sujet_reference' => "CAND-LOT-{$i}", 'signal_code' => 'CRT-REGION', 'valeur_type' => 'TEXTE',
        'valeur_normalisee' => 'ABJ', 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve automatisée',
        'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
        'observation_le' => '2026-08-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
    ]);
    $registre->enregistrerSignal([
        'sujet_type' => 'PERSONNE', 'sujet_reference' => "CAND-LOT-{$i}", 'signal_code' => 'CRT-SCORE', 'valeur_type' => 'NOMBRE',
        'valeur_normalisee' => 60, 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve automatisée',
        'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
        'observation_le' => '2026-08-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
    ]);
}
$executionLot = $registre->executer($demandeLot['reference'], [], $acteur, '2026-08-15T00:00:00Z');
$verifier(($executionLot['candidats_evalues'] ?? 0) === 30, 'lot de 30 candidats exécuté');

$segment = $registre->construireSegment($demandeLot['reference'], [], $acteur, '2026-08-15T00:00:00Z');
$verifier(!isset($segment['refus']) && $segment['population_nombre'] === 30, 'segment construit : 30 membres au-dessus du seuil');

$token = $pdo->query("SELECT membre_token FROM matching_segment_membre WHERE segment_reference = '{$segment['reference']}' LIMIT 1")->fetchColumn();
$appartenance = $registre->verifierAppartenanceSegment($segment['reference'], (string) $token, '2026-08-15T00:00:00Z');
$verifier($appartenance['statut'] === 'APPARTIENT', 'vérification d’appartenance : token actif reconnu');
$appartenanceInconnue = $registre->verifierAppartenanceSegment($segment['reference'], 'MTK-INEXISTANT', '2026-08-15T00:00:00Z');
$verifier($appartenanceInconnue['statut'] === 'N_APPARTIENT_PAS', 'un token inconnu ne révèle jamais la liste, seulement N_APPARTIENT_PAS');

// ----------------------------------------------------------------------
echo "\nActivation et mesure\n";

$activationRefusee = $registre->demanderActivation($segment['reference'], [
    'consommateur_produit' => 'PRD-GAMAD-002', 'finalite_reference' => 'Épreuve automatisée', 'realm_reference' => 'RLM-GAMAD-001',
    'environnement' => 'TEST', 'contrat_reference' => 'CTR-MAT-08', 'contrat_version' => '1', 'autorisation_reference' => 'AUT-X',
    'usage_autorise' => 'CIBLAGE_TEST',
], ['autorisation_decision' => 'REFUSE'], $acteur, '2026-08-15T00:00:00Z');
$verifier($activationRefusee['etat'] === 'REFUSEE', 'activation refusée sans décision PERMIS de CAP-CORE-004');

$activation = $registre->demanderActivation($segment['reference'], [
    'consommateur_produit' => 'PRD-GAMAD-002', 'finalite_reference' => 'Épreuve automatisée', 'realm_reference' => 'RLM-GAMAD-001',
    'environnement' => 'TEST', 'contrat_reference' => 'CTR-MAT-08', 'contrat_version' => '1', 'autorisation_reference' => 'AUT-X',
    'usage_autorise' => 'CIBLAGE_TEST',
], [
    'produit_actif' => true, 'contrat_actif' => true, 'politique_active' => true, 'autorisation_decision' => 'PERMIS',
    'decision_formelle_requise' => false, 'risque_bloquant' => false, 'incident_bloquant' => false, 'obligations_acceptees' => true,
], $acteur, '2026-08-15T00:00:00Z');
$verifier($activation['etat'] === 'AUTORISEE', 'activation autorisée quand tous les faits requis sont réunis');

$accuse = $registre->accuserActivation($activation['reference'], $acteur);
$verifier(($accuse['etat'] ?? null) === 'ACTIVE', 'activation devient ACTIVE après accusé du consommateur');

$activationSegmentExpire = $registre->demanderActivation($segment['reference'], [
    'consommateur_produit' => 'PRD-GAMAD-002', 'finalite_reference' => 'Épreuve automatisée', 'realm_reference' => 'RLM-GAMAD-001',
    'environnement' => 'TEST', 'contrat_reference' => 'CTR-MAT-08', 'contrat_version' => '1', 'autorisation_reference' => 'AUT-X',
    'usage_autorise' => 'CIBLAGE_TEST',
], ['produit_actif' => true, 'contrat_actif' => true, 'politique_active' => true, 'autorisation_decision' => 'PERMIS', 'obligations_acceptees' => true], $acteur, '2030-01-01T00:00:00Z');
$verifier($activationSegmentExpire['etat'] === 'REFUSEE' && $activationSegmentExpire['motif_code'] === 'MATCHING_SEGMENT_EXPIRED', 'activation refusée sur un segment expiré à l’instant de référence');

$mesureRefusee = $registre->enregistrerMesure($activation['reference'], [
    'mesure_code' => 'CONTACTS', 'fenetre_debut' => '2026-08-15T00:00:00Z', 'fenetre_fin' => '2026-08-16T00:00:00Z',
    'source_reference' => 'PRD-GAMAD-002', 'contrat_reference' => 'CTR-MAT-09', 'classification' => 'INTERNE',
], ['contrat_actif' => true, 'finalite_identique' => true, 'nominative' => true, 'nominatif_autorise_contrat' => false], $acteur);
$verifier(($mesureRefusee['refus'] ?? null) === 'MATCHING_RAW_EXPORT_FORBIDDEN', 'mesure nominative non contractuelle refusée');

$registre->enregistrerMesure($activation['reference'], [
    'mesure_code' => 'CONTACTS', 'valeur_numerique' => 12, 'fenetre_debut' => '2026-08-15T00:00:00Z', 'fenetre_fin' => '2026-08-16T00:00:00Z',
    'source_reference' => 'PRD-GAMAD-002', 'contrat_reference' => 'CTR-MAT-09', 'classification' => 'INTERNE',
], ['contrat_actif' => true, 'finalite_identique' => true, 'nominative' => false, 'nominatif_autorise_contrat' => false], $acteur);
$registre->enregistrerMesure($activation['reference'], [
    'mesure_code' => 'CONTACTS', 'valeur_numerique' => 8, 'fenetre_debut' => '2026-08-16T00:00:00Z', 'fenetre_fin' => '2026-08-17T00:00:00Z',
    'source_reference' => 'PRD-GAMAD-002', 'contrat_reference' => 'CTR-MAT-09', 'classification' => 'INTERNE',
], ['contrat_actif' => true, 'finalite_identique' => true, 'nominative' => false, 'nominatif_autorise_contrat' => false], $acteur);
$agregat = $registre->agregerMesures($activation['reference']);
$verifier(($agregat['CONTACTS']['nombre'] ?? 0) === 2 && (float) ($agregat['CONTACTS']['moyenne'] ?? 0) === 10.0, 'agrégation mécanique des mesures (moyenne exacte, sans pondération inventée)');

// ----------------------------------------------------------------------
echo "\nContestation et réexamen\n";

$contestationRefusee = $registre->ouvrirContestation([
    'resultat_reference' => $resultatB['reference'], 'contestant_reference' => 'IDN-CONTESTANT', 'motif_code' => 'DESACCORD_CLASSEMENT',
    'realm_reference' => 'RLM-GAMAD-001', 'classification' => 'INTERNE',
], ['contestant_autorise' => false], $acteur);
$verifier(isset($contestationRefusee['refus']), 'contestant non autorisé refusé');

$contestation = $registre->ouvrirContestation([
    'resultat_reference' => $resultatB['reference'], 'contestant_reference' => 'IDN-CONTESTANT', 'motif_code' => 'DESACCORD_CLASSEMENT',
    'realm_reference' => 'RLM-GAMAD-001', 'classification' => 'INTERNE',
], ['contestant_autorise' => true], $acteur);
$verifier(($contestation['etat'] ?? null) === 'RECEVABLE', 'contestation recevable acceptée');

// Correction de source : CAND-B a en réalité la région ABJ (signal plus récent).
$registre->enregistrerSignal([
    'sujet_type' => 'PERSONNE', 'sujet_reference' => 'CAND-B', 'signal_code' => 'CRT-REGION', 'valeur_type' => 'TEXTE',
    'valeur_normalisee' => 'ABJ', 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve automatisée',
    'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
    'observation_le' => '2026-09-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
]);
$registre->enregistrerSignal([
    'sujet_type' => 'PERSONNE', 'sujet_reference' => 'CAND-B', 'signal_code' => 'CRT-SCORE', 'valeur_type' => 'NOMBRE',
    'valeur_normalisee' => 70, 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve automatisée',
    'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
    'observation_le' => '2026-09-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
]);
$secondeExecution = $registre->executer($demande['reference'], [], $acteur, '2026-09-02T00:00:00Z');
$verifier(!isset($secondeExecution['refus']), 'reprise d’exécution sur une demande déjà TERMINEE (source corrigée)');

$reexamen = $registre->reexaminer($contestation['reference'], $secondeExecution['execution'], ['SRC-TEST'], $acteur);
$verifier(($reexamen['verdict'] ?? null) === 'MODIFIE', 'réexamen : correction de source change le classement — verdict MODIFIE');
$contestationApres = $registre->resoudreContestation($contestation['reference']);
$verifier(($contestationApres['etat'] ?? null) === 'RESOLUE', 'contestation résolue après réexamen');

$ancienResultatIntact = $registre->resoudreResultat($resultatB['reference']);
$verifier($ancienResultatIntact['classe_resultat'] === 'NON_CORRESPONDANT', 'l’ancien résultat n’est jamais réécrit par le réexamen');

// ----------------------------------------------------------------------
echo "\nExpiration planifiée (matching:expiration, doc 05 §6)\n";

$expirationPrematuree = $registre->expirerSegmentsEchus('2026-08-16T00:00:00Z');
$verifier($expirationPrematuree['segments_expires'] === 0, 'aucun segment marqué expiré avant son échéance réelle');
$verifier($registre->resoudreSegment($segment['reference'])['etat'] === 'ACTIF', 'le segment reste ACTIF avant échéance');

$expirationSegments = $registre->expirerSegmentsEchus('2027-01-01T00:00:00Z');
$verifier($expirationSegments['segments_expires'] === 1, 'le segment échu est marqué EXPIRE après son échéance réelle');
$verifier($registre->resoudreSegment($segment['reference'])['etat'] === 'EXPIRE', 'la fiche du segment reflète l’expiration');

$expirationSegmentsRejouee = $registre->expirerSegmentsEchus('2027-01-01T00:00:00Z');
$verifier($expirationSegmentsRejouee['segments_expires'] === 0, 'rejouer l’expiration sur un segment déjà EXPIRE ne compte rien de plus (idempotent)');

$expirationActivations = $registre->expirerActivationsEchues('2027-01-01T00:00:00Z');
$verifier($expirationActivations['activations_expirees'] === 1, 'l’activation ACTIVE échue est marquée EXPIREE');
$verifier($registre->resoudreActivation($activation['reference'])['etat'] === 'EXPIREE', 'la fiche de l’activation reflète l’expiration');

// ----------------------------------------------------------------------
echo "\nOutbox transactionnelle (CAP-CORE-014)\n";

$evenements = (int) $pdo->query("SELECT COUNT(*) FROM evenement_sortant WHERE type_evenement LIKE 'CAP-CORE-021.%'")->fetchColumn();
$verifier($evenements >= 5, 'événements CAP-CORE-021 déposés dans l’outbox partagé (exécution, segment, activation, mesure, contestation, réexamen)');

// ----------------------------------------------------------------------
if ($echecs === 0) {
    echo "\nToutes les épreuves sont vertes.\n";
    exit(0);
}
printf("\n%d épreuve(s) en échec.\n", $echecs);
exit(1);
