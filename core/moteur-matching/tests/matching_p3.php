<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-021 — moteur de Matching.
 *
 * Éprouve directement `Apparieur` et `EvaluateurDeterministe`, les deux
 * seules classes du moteur sans dépendance à un autre magasin — fonctions
 * pures sur des faits déjà rassemblés, testables sans bootstrap complet.
 *
 * Couvre une partie des « épreuves minimales » du chantier (doc 04 §20,
 * catégorie « Évaluation déterministe », items 57 à 75). Ce fichier grandit
 * au fil du chantier ; il ne prétend pas encore couvrir les 160 épreuves
 * exigées pour le GO — voir les tâches restantes du chantier.
 *
 * CONTRE-ÉPREUVE : les épreuves 65 (interdit) et 62 (exclusion dure) vérifient
 * qu'un résultat défavorable est bien produit, pas seulement qu'aucune
 * exception n'est levée.
 *
 * Exécution : php core/moteur-matching/tests/matching_p3.php
 * Code de sortie : 0 si toutes les épreuves passent.
 */

require __DIR__ . '/../../registre-preuves/src/Canonicaliseur.php';
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

use Gamad\MoteurMatching\Activation;
use Gamad\MoteurMatching\Apparieur;
use Gamad\MoteurMatching\Classement;
use Gamad\MoteurMatching\CompilateurPolitique;
use Gamad\MoteurMatching\Contestations;
use Gamad\MoteurMatching\EvaluateurDeterministe;
use Gamad\MoteurMatching\Explication;
use Gamad\MoteurMatching\Mesure;
use Gamad\MoteurMatching\ResolutionSources;
use Gamad\MoteurMatching\Segments;

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — MOTEUR DE MATCHING (CAP-CORE-021)\n\n";

echo "Apparieur — opérateurs déterministes\n";

$verifier(Apparieur::evaluer('EQ', 'CI', 'CI') === true, '57. EQ vrai sur valeurs identiques');
$verifier(Apparieur::evaluer('EQ', 'CI', 'SN') === false, '57bis. EQ faux sur valeurs différentes');
$verifier(Apparieur::evaluer('IN', 'Abidjan', ['Abidjan', 'Abobo']) === true, '58. IN vrai si membre de la liste');
$verifier(Apparieur::evaluer('IN', 'Dakar', ['Abidjan', 'Abobo']) === false, '58bis. IN faux si absent de la liste');

$dansLaFenetre = Apparieur::evaluer('VALID_AT', ['depuis' => '2026-01-01T00:00:00Z', 'jusqua' => '2026-12-31T23:59:59Z'], null, '2026-06-15T00:00:00Z');
$horsLaFenetre = Apparieur::evaluer('VALID_AT', ['depuis' => '2026-01-01T00:00:00Z', 'jusqua' => '2026-12-31T23:59:59Z'], null, '2027-01-15T00:00:00Z');
$verifier($dansLaFenetre === true, '59. comparaison de dates : instant dans la fenêtre de validité');
$verifier($horsLaFenetre === false, '59bis. comparaison de dates : instant hors fenêtre');
$verifier($dansLaFenetre !== $horsLaFenetre, '74. un instant de référence différent est explicitement tracé (résultat différent, pas caché)');

$verifier(Apparieur::evaluer('GTE', 26, 25) === true, '60. comparaison d’unités normalisées (âge ≥ seuil)');
$verifier(Apparieur::evaluer('GTE', 24, 25) === false, '60bis. comparaison d’unités normalisées, sous le seuil');
$verifier(Apparieur::evaluer('BETWEEN', 30, [25, 55]) === true, '60ter. BETWEEN vrai dans l’intervalle');
$verifier(Apparieur::evaluer('EXISTS_VERIFIED', null, null) === false, 'EXISTS_VERIFIED faux si non établi');
$verifier(Apparieur::evaluer('NOT_EXISTS_VERIFIED', null, null) === true, 'NOT_EXISTS_VERIFIED vrai si non établi');

echo "\nEvaluateurDeterministe — agrégation, classement, arrêts\n";

$critereDurSatisfait = [
    'critere_reference' => 'CRT-VEHICULE', 'obligatoire' => true, 'exclusion_dure' => true,
    'poids' => null, 'traitement_inconnu' => 'INDETERMINE', 'traitement_contradictoire' => null,
    'facteur_public_autorise' => true,
];
$critereDurDefavorable = $critereDurSatisfait;

$resultatDurSatisfait = EvaluateurDeterministe::evaluer(
    [$critereDurSatisfait],
    ['CRT-VEHICULE' => ['etat' => 'SATISFAIT', 'confiance_source' => 1.0]],
);
$verifier($resultatDurSatisfait['classe'] !== 'NON_CORRESPONDANT', '61. critère dur satisfait ne force pas un arrêt défavorable');

$resultatDurDefavorable = EvaluateurDeterministe::evaluer(
    [$critereDurDefavorable],
    ['CRT-VEHICULE' => ['etat' => 'DEFAVORABLE', 'confiance_source' => 1.0]],
);
$verifier($resultatDurDefavorable['classe'] === 'NON_CORRESPONDANT', '62. critère dur défavorable (exclusion dure) → NON_CORRESPONDANT');
$verifier($resultatDurDefavorable['pertinence'] === null, '62bis. NON_CORRESPONDANT par exclusion dure : aucune pertinence inventée');

$critereObligatoireSouple = [
    'critere_reference' => 'CRT-PERMIS', 'obligatoire' => true, 'exclusion_dure' => false,
    'poids' => null, 'traitement_inconnu' => 'INDETERMINE', 'traitement_contradictoire' => null,
    'facteur_public_autorise' => false,
];
$resultatObligatoireNonEtabli = EvaluateurDeterministe::evaluer(
    [$critereObligatoireSouple],
    ['CRT-PERMIS' => ['etat' => 'NON_ETABLI', 'confiance_source' => null]],
);
$verifier($resultatObligatoireNonEtabli['classe'] === 'INDETERMINE', '63. obligation non établie → INDETERMINE');
$verifier($resultatObligatoireNonEtabli['arret_code'] === 'OBLIGATION_NON_ETABLIE', '63bis. code d’arrêt explicite, pas un score masqué');

$resultatContradictoire = EvaluateurDeterministe::evaluer(
    [$critereObligatoireSouple],
    ['CRT-PERMIS' => ['etat' => 'CONTRADICTOIRE', 'confiance_source' => 0.4]],
);
$verifier($resultatContradictoire['classe'] === 'INDETERMINE' && $resultatContradictoire['arret_code'] === 'OBLIGATION_CONTRADICTOIRE', '64. contradictoire sur obligation → traitement explicite (INDETERMINE), pas ignoré');

$critereInterdit = [
    'critere_reference' => 'CRT-INTERDIT', 'obligatoire' => false, 'exclusion_dure' => false,
    'poids' => 1.0, 'traitement_inconnu' => 'INDETERMINE', 'traitement_contradictoire' => null,
    'facteur_public_autorise' => false,
];
$resultatInterdit = EvaluateurDeterministe::evaluer(
    [$critereInterdit],
    ['CRT-INTERDIT' => ['etat' => 'INTERDIT', 'confiance_source' => null]],
);
$verifier($resultatInterdit['classe'] === 'INTERDIT', '65. critère interdit → arrêt en classe INTERDIT');
$verifier($resultatInterdit['pertinence'] === null && $resultatInterdit['confiance'] === null, '65bis. INTERDIT ne porte ni pertinence ni confiance');

$profilPondere = [
    ['critere_reference' => 'CRT-A', 'obligatoire' => false, 'exclusion_dure' => false, 'poids' => 2.0, 'traitement_inconnu' => 'IGNORER_AVEC_TRACE', 'traitement_contradictoire' => null, 'facteur_public_autorise' => true],
    ['critere_reference' => 'CRT-B', 'obligatoire' => false, 'exclusion_dure' => false, 'poids' => 1.0, 'traitement_inconnu' => 'IGNORER_AVEC_TRACE', 'traitement_contradictoire' => null, 'facteur_public_autorise' => true],
];
$evaluations = [
    'CRT-A' => ['etat' => 'SATISFAIT', 'confiance_source' => 0.9],
    'CRT-B' => ['etat' => 'DEFAVORABLE', 'confiance_source' => 0.6],
];
$r1 = EvaluateurDeterministe::evaluer($profilPondere, $evaluations);
$r2 = EvaluateurDeterministe::evaluer($profilPondere, $evaluations);
$verifier($r1 === $r2, '66. même entrée, même politique → résultat exactement reproductible');
$verifier($r1['pertinence'] === round(2.0 / 3.0, 4), '66bis. pertinence = somme(contributions admissibles) / somme(poids admissibles)');
$verifier($r1['confiance'] !== $r1['pertinence'], '67. confiance et pertinence restent des valeurs distinctes');

$profilSansPoids = [
    ['critere_reference' => 'CRT-INFO', 'obligatoire' => false, 'exclusion_dure' => false, 'poids' => null, 'traitement_inconnu' => 'IGNORER_AVEC_TRACE', 'traitement_contradictoire' => null, 'facteur_public_autorise' => true],
];
$resultatSansPoids = EvaluateurDeterministe::evaluer($profilSansPoids, ['CRT-INFO' => ['etat' => 'SATISFAIT', 'confiance_source' => 1.0]]);
$verifier($resultatSansPoids['pertinence'] === null && $resultatSansPoids['classe'] === 'INDETERMINE', '68. aucun critère pondéré admissible → score null, jamais 0 par défaut');

$precisionProfil = [
    ['critere_reference' => 'CRT-X', 'obligatoire' => false, 'exclusion_dure' => false, 'poids' => 1.0, 'traitement_inconnu' => 'IGNORER_AVEC_TRACE', 'traitement_contradictoire' => null, 'facteur_public_autorise' => true],
    ['critere_reference' => 'CRT-Y', 'obligatoire' => false, 'exclusion_dure' => false, 'poids' => 1.0, 'traitement_inconnu' => 'IGNORER_AVEC_TRACE', 'traitement_contradictoire' => null, 'facteur_public_autorise' => true],
    ['critere_reference' => 'CRT-Z', 'obligatoire' => false, 'exclusion_dure' => false, 'poids' => 1.0, 'traitement_inconnu' => 'IGNORER_AVEC_TRACE', 'traitement_contradictoire' => null, 'facteur_public_autorise' => true],
];
$evalArrondi = [
    'CRT-X' => ['etat' => 'SATISFAIT', 'confiance_source' => 1.0],
    'CRT-Y' => ['etat' => 'DEFAVORABLE', 'confiance_source' => 1.0],
    'CRT-Z' => ['etat' => 'DEFAVORABLE', 'confiance_source' => 1.0],
];
$resultatArrondi = EvaluateurDeterministe::evaluer($profilPondere = $precisionProfil, $evalArrondi, ['precision_arrondi' => 2]);
$verifier($resultatArrondi['pertinence'] === round(1 / 3, 2), '69. arrondi appliqué à la précision déclarée par le profil, stable');

$critereContradictoirePondere = [
    ['critere_reference' => 'CRT-C', 'obligatoire' => false, 'exclusion_dure' => false, 'poids' => 1.0, 'traitement_inconnu' => 'IGNORER_AVEC_TRACE', 'traitement_contradictoire' => 'IGNORER_AVEC_TRACE', 'facteur_public_autorise' => true],
    ['critere_reference' => 'CRT-D', 'obligatoire' => false, 'exclusion_dure' => false, 'poids' => 1.0, 'traitement_inconnu' => 'IGNORER_AVEC_TRACE', 'traitement_contradictoire' => null, 'facteur_public_autorise' => true],
];
$resultatContradictoirePondere = EvaluateurDeterministe::evaluer($critereContradictoirePondere, [
    'CRT-C' => ['etat' => 'CONTRADICTOIRE', 'confiance_source' => 0.8],
    'CRT-D' => ['etat' => 'SATISFAIT', 'confiance_source' => 0.8],
]);
$verifier($resultatContradictoirePondere['pertinence'] === 1.0, 'un critère pondéré contradictoire n’altère pas la contribution admissible de calcul (exclu du dénominateur par défaut)');

echo "\nClassement — tri déterministe de plusieurs résultats\n";

$candidats = [
    ['candidat_reference' => 'CAND-B', 'admissible' => true, 'classe' => 'CORRESPONDANCE_PARTIELLE', 'pertinence' => 0.4, 'confiance' => 0.9, 'regles_secondaires' => []],
    ['candidat_reference' => 'CAND-A', 'admissible' => true, 'classe' => 'CORRESPONDANCE_FORTE', 'pertinence' => 0.95, 'confiance' => 0.5, 'regles_secondaires' => []],
    ['candidat_reference' => 'CAND-C', 'admissible' => false, 'classe' => 'CORRESPONDANCE_FORTE', 'pertinence' => 1.0, 'confiance' => 1.0, 'regles_secondaires' => []],
    ['candidat_reference' => 'CAND-D', 'admissible' => true, 'classe' => 'INDETERMINE', 'pertinence' => null, 'confiance' => null, 'regles_secondaires' => []],
];
$classe1 = Classement::classer($candidats);
$classe2 = Classement::classer($candidats);
$verifier($classe1 === $classe2, '70. classement stable : même entrée, même ordre à chaque appel');
$verifier($classe1[0]['candidat_reference'] === 'CAND-A' && $classe1[0]['rang'] === 1, 'admissibilité et classe priment sur la pertinence brute (CAND-A avant CAND-C non admissible)');
$verifier($classe1[1]['candidat_reference'] === 'CAND-B' && $classe1[1]['rang'] === 2, 'ordre de classe respecté (FORTE avant PARTIELLE)');
$dernier = end($classe1);
$verifier($dernier['candidat_reference'] === 'CAND-C' && $dernier['rang'] === null, 'un candidat non admissible n’est jamais classé, quelle que soit sa pertinence brute');

$exAequo = [
    ['candidat_reference' => 'CAND-Z', 'admissible' => true, 'classe' => 'CORRESPONDANCE_PROBABLE', 'pertinence' => 0.6, 'confiance' => 0.6, 'regles_secondaires' => []],
    ['candidat_reference' => 'CAND-Y', 'admissible' => true, 'classe' => 'CORRESPONDANCE_PROBABLE', 'pertinence' => 0.6, 'confiance' => 0.6, 'regles_secondaires' => []],
];
$classeExAequo = Classement::classer($exAequo);
$verifier($classeExAequo[0]['candidat_reference'] === 'CAND-Y', '71. départage stable par référence lorsque tout le reste est identique');
$verifier(array_keys($classeExAequo[0]) === array_keys($exAequo[0] + ['rang' => null]), '72. le classement n’ajoute aucun attribut caché : seul `rang` est produit en plus des champs fournis');

echo "\nCompilateurPolitique — compilation déterministe et reproductible\n";

$specificationValide = [
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0',
    'contexte_reference' => 'WASPLEX_AUDIENCE', 'contrat_reference' => 'CTR-MAT-05', 'contrat_version' => '1.0.0',
    'criteres' => [
        ['critere_reference' => 'CRT-TERRITOIRE', 'operateur' => 'EQ', 'traitement_inconnu' => 'IGNORER_AVEC_TRACE', 'poids' => 2.0, 'sources_autorisees' => ['SRC-ORGANISATIONS']],
        ['critere_reference' => 'CRT-AGE', 'operateur' => 'BETWEEN', 'traitement_inconnu' => 'INDETERMINE', 'poids' => 1.0, 'obligatoire' => true, 'sources_autorisees' => ['SRC-IDENTITES']],
    ],
    'seuils_classe' => ['fort' => 0.75, 'moyen' => 0.5, 'bas' => 0.25],
    'precision_arrondi' => 4,
];
$compilation1 = CompilateurPolitique::compiler($specificationValide);
$compilation2 = CompilateurPolitique::compiler($specificationValide);
$verifier(!isset($compilation1['refus']), 'compilation d’une spécification valide acceptée');
$verifier($compilation1['plan_hash'] === $compilation2['plan_hash'], '17bis. compilation déterministe : même spécification → même empreinte de plan à chaque appel');
$verifier(strlen($compilation1['plan_hash']) === 64, 'empreinte de plan au format SHA-256 (64 caractères hexadécimaux)');

$specificationOperateurInvalide = $specificationValide;
$specificationOperateurInvalide['criteres'][0]['operateur'] = 'SELECT * FROM organisations';
$refusOperateur = CompilateurPolitique::compiler($specificationOperateurInvalide);
$verifier(($refusOperateur['refus'] ?? null) === 'OPERATEUR_INCONNU', '19. code exécutable ou expression libre refusé (opérateur hors liste close)');

$specificationCritereInconnu = $specificationValide;
$specificationCritereInconnu['criteres'][0]['operateur'] = 'REGEX_LIBRE';
$refusCritereInconnu = CompilateurPolitique::compiler($specificationCritereInconnu);
$verifier(($refusCritereInconnu['refus'] ?? null) === 'OPERATEUR_INCONNU', '20. critère non canonique refusé');

$specificationSeuilsIncoherents = $specificationValide;
$specificationSeuilsIncoherents['seuils_classe'] = ['fort' => 0.2, 'moyen' => 0.5, 'bas' => 0.8];
$refusSeuils = CompilateurPolitique::compiler($specificationSeuilsIncoherents);
$verifier(($refusSeuils['refus'] ?? null) === 'SEUILS_INCOHERENTS', 'seuils incohérents (fort < bas) refusés plutôt qu’acceptés silencieusement');

$specificationChampAbsent = $specificationValide;
unset($specificationChampAbsent['contexte_reference']);
$refusChamp = CompilateurPolitique::compiler($specificationChampAbsent);
$verifier(($refusChamp['refus'] ?? null) === 'CHAMP_ABSENT', 'spécification incomplète refusée plutôt que complétée par une valeur inventée');

$specificationVersionB = $specificationValide;
$specificationVersionB['politique_version'] = '2.0.0';
$compilationB = CompilateurPolitique::compiler($specificationVersionB);
$verifier($compilationB['plan_hash'] !== $compilation1['plan_hash'], '75. une politique de version différente produit un plan — et donc un résultat — lié à sa propre version');

echo "\nSegments — construction protégée et vérification d’appartenance\n";

$sujetsInsuffisants = ['SUJ-001', 'SUJ-002'];
$refusPetitePopulation = Segments::construire($sujetsInsuffisants, 25);
$verifier(($refusPetitePopulation['refus'] ?? null) === 'MATCHING_POPULATION_TOO_SMALL', '94. petite population refusée plutôt qu’un segment fabriqué');

$sujetsSuffisants = array_map(static fn (int $i): string => "SUJ-" . str_pad((string) $i, 3, '0', STR_PAD_LEFT), range(1, 30));
$segmentA = Segments::construire($sujetsSuffisants, 25);
$segmentB = Segments::construire(array_reverse($sujetsSuffisants), 25);
$verifier(!isset($segmentA['refus']), 'population suffisante : segment construit');
$verifier($segmentA['membres_hash'] === $segmentB['membres_hash'], '95. empreinte des membres stable, indépendante de l’ordre d’entrée');
$verifier($segmentA['population_nombre'] === 30, 'population_nombre reflète exactement le nombre de sujets admissibles');

$tokens = array_column($segmentA['membres'], 'membre_token');
$verifier(count($tokens) === count(array_unique($tokens)), '90bis. chaque membre reçoit un token distinct');
$verifier(str_starts_with($tokens[0], 'MTK-') && strlen($tokens[0]) === 68, 'token opaque au format attendu (préfixe + 256 bits hexadécimaux)');

$instant = '2026-08-06T00:00:00Z';
$membresActifs = [
    ['membre_token' => $tokens[0], 'sujet_reference_interne' => 'SUJ-001', 'statut' => 'ACTIF', 'valide_jusqua' => '2026-12-31T00:00:00Z'],
];
$verifier(Segments::verifierAppartenance($membresActifs, $tokens[0], $instant) === 'APPARTIENT', 'vérification d’appartenance : token actif et valide reconnu');
$verifier(Segments::verifierAppartenance($membresActifs, 'MTK-' . bin2hex(random_bytes(32)), $instant) === 'N_APPARTIENT_PAS', 'un token inconnu ne révèle jamais la liste, seulement N_APPARTIENT_PAS');
$membresExpires = [['membre_token' => $tokens[0], 'sujet_reference_interne' => 'SUJ-001', 'statut' => 'ACTIF', 'valide_jusqua' => '2020-01-01T00:00:00Z']];
$verifier(Segments::verifierAppartenance($membresExpires, $tokens[0], $instant) === 'N_APPARTIENT_PAS', '98. un token expiré n’appartient plus, même retrouvé');
$membresRevoques = [['membre_token' => $tokens[0], 'sujet_reference_interne' => 'SUJ-001', 'statut' => 'REVOQUE', 'valide_jusqua' => '2026-12-31T00:00:00Z']];
$verifier(Segments::verifierAppartenance($membresRevoques, $tokens[0], $instant) === 'N_APPARTIENT_PAS', '96/99. un membre révoqué n’appartient plus au segment');

echo "\nExplication — projection consommateur filtrée\n";

$resultatType = [
    'classe' => 'CORRESPONDANCE_PROBABLE', 'pertinence' => 0.6, 'confiance' => 0.4,
    'non_decisionnel' => true, 'expire_le' => '2026-12-31T00:00:00Z',
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0',
    'preuve_reference' => 'PRV-GAMAD-TEST', 'obligations' => ['NO_RAW_EXPORT', 'PURPOSE_BOUND'],
];
$facteursMixtes = [
    ['critere_reference' => 'CRT-A', 'nature' => 'FAVORABLE', 'code_explication' => 'territoire_correspond', 'public_autorise' => true],
    ['critere_reference' => 'CRT-B', 'nature' => 'DEFAVORABLE', 'code_explication' => 'age_hors_plage', 'public_autorise' => true],
    ['critere_reference' => 'CRT-C', 'nature' => 'NON_ETABLI', 'code_explication' => 'permis_non_verifie', 'public_autorise' => true],
    ['critere_reference' => 'CRT-SENSIBLE', 'nature' => 'DEFAVORABLE', 'code_explication' => 'source_interne_sensible', 'public_autorise' => false],
];
$projection = Explication::projeter($resultatType, $facteursMixtes, 'WASPLEX_AUDIENCE');
$verifier($projection['facteurs_favorables'] === ['territoire_correspond'], '76. facteurs favorables projetés');
$verifier($projection['facteurs_defavorables'] === ['age_hors_plage'], '77. facteurs défavorables projetés');
$verifier($projection['facteurs_non_etablis'] === ['permis_non_verifie'], '78. facteurs non établis projetés');
$verifier(!in_array('source_interne_sensible', $projection['facteurs_defavorables'], true), '79. facteur marqué non public jamais exposé au consommateur');
$verifier($projection['non_decisionnel'] === true, '81. non_decisionnel=true toujours présent dans la projection');
$verifier($projection['confiance'] !== $projection['pertinence'], '83/84. confiance faible reste visible séparément, la pertinence ne la masque pas');

$exceptionLevee = false;
try {
    Explication::projeter(['classe' => 'X', 'pertinence' => null, 'confiance' => null, 'non_decisionnel' => false, 'expire_le' => '2026-01-01', 'politique_reference' => 'P', 'politique_version' => '1', 'preuve_reference' => null, 'obligations' => []], []);
} catch (\Gamad\MoteurMatching\ExceptionMatching) {
    $exceptionLevee = true;
}
$verifier($exceptionLevee, 'non_decisionnel=false est un invariant violé, jamais projeté silencieusement');

$verifier(Explication::estExpire('2026-01-01T00:00:00Z', '2026-06-01T00:00:00Z') === true, '82. un résultat expiré est détecté, jamais présenté comme actuel');
$verifier(Explication::estExpire('2026-12-31T00:00:00Z', '2026-06-01T00:00:00Z') === false, '82bis. un résultat encore valide n’est pas marqué expiré');

echo "\nActivation — vérification déterministe des préconditions\n";

$faitsValides = [
    'segment_etat' => 'ACTIF', 'segment_expire_le' => '2026-12-31T00:00:00Z', 'instant_reference' => '2026-08-06T00:00:00Z',
    'segment_consommateur_produit' => 'PRD-GAMAD-002', 'demande_consommateur_produit' => 'PRD-GAMAD-002',
    'segment_finalite_reference' => 'AUDIENCE_TEST', 'demande_finalite_reference' => 'AUDIENCE_TEST',
    'segment_realm_reference' => 'RLM-GAMAD-CORE', 'demande_realm_reference' => 'RLM-GAMAD-CORE',
    'produit_actif' => true, 'contrat_actif' => true, 'politique_active' => true,
    'autorisation_decision' => 'PERMIS', 'decision_formelle_requise' => false, 'decision_formelle_presente' => false,
    'risque_bloquant' => false, 'incident_bloquant' => false, 'obligations_acceptees' => true,
];
$verifier(Activation::verifier($faitsValides)['decision'] === 'AUTORISEE', '101. activation valide autorisée');

$sansAutorisation = $faitsValides;
$sansAutorisation['autorisation_decision'] = 'REFUSE';
$verifier(Activation::verifier($sansAutorisation) === ['decision' => 'REFUSEE', 'motif_code' => 'MATCHING_ACTIVATION_DENIED'], '102. activation sans autorisation CAP-CORE-004 refusée');

$autreProduit = $faitsValides;
$autreProduit['demande_consommateur_produit'] = 'PRD-GAMAD-004';
$verifier(Activation::verifier($autreProduit)['motif_code'] === 'MATCHING_CONSUMER_NOT_ALLOWED', '104. activation pour un autre produit refusée');

$autreFinalite = $faitsValides;
$autreFinalite['demande_finalite_reference'] = 'AUTRE_FINALITE';
$verifier(Activation::verifier($autreFinalite)['motif_code'] === 'MATCHING_PURPOSE_NOT_ALLOWED', '105. activation pour une autre finalité refusée');

$autreRealm = $faitsValides;
$autreRealm['demande_realm_reference'] = 'RLM-AUTRE';
$verifier(Activation::verifier($autreRealm)['motif_code'] === 'MATCHING_REALM_NOT_ALLOWED', '106. activation dans un autre realm refusée');

$produitSuspendu = $faitsValides;
$produitSuspendu['produit_actif'] = false;
$verifier(Activation::verifier($produitSuspendu)['motif_code'] === 'MATCHING_PRODUCT_INACTIVE', '112. produit suspendu suspend l’activation');

$incidentBloquant = $faitsValides;
$incidentBloquant['incident_bloquant'] = true;
$verifier(Activation::verifier($incidentBloquant)['motif_code'] === 'MATCHING_INCIDENT_BLOCKED', '113. incident bloquant suspend l’activation');

$risqueBloquant = $faitsValides;
$risqueBloquant['risque_bloquant'] = true;
$verifier(Activation::verifier($risqueBloquant)['motif_code'] === 'MATCHING_RISK_BLOCKED', '114. risque bloquant refuse l’activation');

$segmentExpire = $faitsValides;
$segmentExpire['segment_etat'] = 'EXPIRE';
$verifier(Activation::verifier($segmentExpire)['motif_code'] === 'MATCHING_SEGMENT_EXPIRED', '115. segment expiré refuse toute activation');

echo "\nMesure — validation et agrégation\n";

$mesureValide = ['contrat_actif' => true, 'finalite_identique' => true, 'nominative' => false, 'nominatif_autorise_contrat' => false];
$verifier(Mesure::valider($mesureValide)['valide'] === true, '116. mesure valide acceptée');

$mesureHorsFinalite = $mesureValide;
$mesureHorsFinalite['finalite_identique'] = false;
$verifier(Mesure::valider($mesureHorsFinalite) === ['valide' => false, 'motif_code' => 'MATCHING_PURPOSE_NOT_ALLOWED'], '117. mesure hors finalité refusée');

$mesureNominativeNonContractuelle = $mesureValide;
$mesureNominativeNonContractuelle['nominative'] = true;
$verifier(Mesure::valider($mesureNominativeNonContractuelle)['motif_code'] === 'MATCHING_RAW_EXPORT_FORBIDDEN', '118. mesure nominative non contractuelle refusée');

$mesures = [
    ['mesure_code' => 'VUE', 'valeur_numerique' => 10.0],
    ['mesure_code' => 'VUE', 'valeur_numerique' => 20.0],
    ['mesure_code' => 'PLAINTE', 'valeur_numerique' => null],
];
$agregation = Mesure::agreger($mesures);
$verifier($agregation['VUE']['nombre'] === 2 && $agregation['VUE']['somme'] === 30.0 && $agregation['VUE']['moyenne'] === 15.0, '120. agrégation correcte, mécanique, sans pondération inventée');
$verifier($agregation['PLAINTE']['moyenne'] === null, '125. absence de valeur numérique honnêtement signalée (moyenne null, pas 0)');

echo "\nContestations — recevabilité et réexamen\n";

$contestationRecevable = ['contestant_autorise' => true, 'resultat_existe' => true, 'motif_code' => 'DESACCORD_CRITERE'];
$verifier(Contestations::verifierRecevabilite($contestationRecevable)['recevable'] === true, '139. contestation recevable acceptée');

$contestantNonAutorise = $contestationRecevable;
$contestantNonAutorise['contestant_autorise'] = false;
$verifier(Contestations::verifierRecevabilite($contestantNonAutorise)['recevable'] === false, '140. contestant non autorisé refusé');

$verifier(Contestations::determinerVerdict('CORRESPONDANCE_FORTE', 'CORRESPONDANCE_FORTE') === 'CONFIRME', '144. résultat confirmé après réexécution identique');
$verifier(Contestations::determinerVerdict('CORRESPONDANCE_FORTE', 'CORRESPONDANCE_PARTIELLE') === 'MODIFIE', '145. résultat modifié après réexécution divergente');
$verifier(Contestations::determinerVerdict('CORRESPONDANCE_FORTE', 'INDETERMINE') === 'DEVENU_INDETERMINE', '146. résultat devenu indéterminé après correction de source');
$verifier(Contestations::determinerVerdict('CORRESPONDANCE_FORTE', null) === 'ANNULE', 'un résultat retiré après réexamen est annulé, pas silencieusement conservé');

echo "\nResolutionSources — utilisabilité d’un signal\n";

$critereSource = ['sources_autorisees' => ['SRC-ORGANISATIONS'], 'fraicheur_max_secondes' => 3600];
$signalValide = ['source_reference' => 'SRC-ORGANISATIONS', 'source_active' => true, 'finalite_reference' => 'AUDIENCE_TEST', 'statut' => 'VALIDE', 'observation_le' => '2026-08-06T00:00:00Z'];
$verifier(ResolutionSources::verifierSignal($critereSource, $signalValide, 'AUDIENCE_TEST', '2026-08-06T00:10:00Z')['utilisable'] === true, '26. source active acceptée');

$signalAutreSource = $signalValide;
$signalAutreSource['source_reference'] = 'SRC-INCONNUE';
$verifier(ResolutionSources::verifierSignal($critereSource, $signalAutreSource, 'AUDIENCE_TEST', '2026-08-06T00:10:00Z')['motif_code'] === 'MATCHING_SOURCE_UNKNOWN', 'source hors liste autorisée par le critère refusée');

$signalSourceSuspendue = $signalValide;
$signalSourceSuspendue['source_active'] = false;
$verifier(ResolutionSources::verifierSignal($critereSource, $signalSourceSuspendue, 'AUDIENCE_TEST', '2026-08-06T00:10:00Z')['motif_code'] === 'MATCHING_SOURCE_UNUSABLE', '27. source suspendue refusée');

$signalMauvaiseFinalite = $signalValide;
$verifier(ResolutionSources::verifierSignal($critereSource, $signalMauvaiseFinalite, 'AUTRE_FINALITE', '2026-08-06T00:10:00Z')['motif_code'] === 'MATCHING_PURPOSE_NOT_ALLOWED', '28. mauvaise finalité refusée');

$signalPerime = $signalValide;
$signalPerime['statut'] = 'PERIME';
$verifier(ResolutionSources::verifierSignal($critereSource, $signalPerime, 'AUDIENCE_TEST', '2026-08-06T00:10:00Z')['motif_code'] === 'MATCHING_SIGNAL_EXPIRED', '30. signal expiré refusé');

$signalRevoque = $signalValide;
$signalRevoque['statut'] = 'REVOQUE';
$verifier(ResolutionSources::verifierSignal($critereSource, $signalRevoque, 'AUDIENCE_TEST', '2026-08-06T00:10:00Z')['motif_code'] === 'MATCHING_SOURCE_UNUSABLE', '31. signal révoqué refusé');

$signalContradictoire = $signalValide;
$signalContradictoire['statut'] = 'CONTRADICTOIRE';
$resultatContradictoireSource = ResolutionSources::verifierSignal($critereSource, $signalContradictoire, 'AUDIENCE_TEST', '2026-08-06T00:10:00Z');
$verifier($resultatContradictoireSource['utilisable'] === true && $resultatContradictoireSource['etat_signal'] === 'CONTRADICTOIRE', '32. signal contradictoire reste visible, pas rejeté en silence');

$signalHorsFraicheur = $signalValide;
$verifier(ResolutionSources::verifierSignal($critereSource, $signalHorsFraicheur, 'AUDIENCE_TEST', '2026-08-06T05:00:00Z')['motif_code'] === 'MATCHING_SIGNAL_EXPIRED', 'signal au-delà de la fraîcheur maximale déclarée par le critère refusé');

if ($echecs > 0) {
    fwrite(STDERR, "\n{$echecs} épreuve(s) en échec.\n");
    exit(1);
}
echo "\nToutes les épreuves sont vertes.\n";
