<?php

declare(strict_types=1);

/**
 * Garde de sécurité applicative de CAP-CORE-021 (doc de chantier 05 §14).
 *
 * Contrôle statique préalable (déjà vérifié, pas répété ici à chaque
 * exécution) : aucune fonction dangereuse (`eval`, `unserialize`, `extract`,
 * `call_user_func` sur une entrée non fermée, `create_function`) n'existe
 * dans `core/moteur-matching/src` ni dans la façade HTTP.
 *
 * Ce fichier éprouve à l'exécution : injection SQL par les champs texte,
 * refus d'une politique portant une charge non sérialisable, comportement
 * sur des valeurs numériques extrêmes et des dates invalides, non-invention
 * de données sur idempotency_key rejouée avec un contenu différent, refus
 * des volumes hors bornes, et l'absence de tout champ permettant au client
 * de dicter directement une pertinence, une confiance ou une classe de
 * résultat.
 *
 * Ne couvre pas (réserve, voir README) : tests de charge réels, SSRF, path
 * traversal (aucune opération de ce module ne lit un chemin fourni par le
 * client), énumération temporelle de tokens (nécessiterait une mesure de
 * timing, hors périmètre d'une garde fonctionnelle).
 *
 * Exécution : php core/moteur-matching/tests/matching_p6.php
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

use Gamad\MoteurMatching\Apparieur;
use Gamad\MoteurMatching\CompilateurPolitique;
use Gamad\MoteurMatching\ExceptionMatching;
use Gamad\MoteurMatching\Magasin;
use Gamad\MoteurMatching\PolitiqueMatching;
use Gamad\MoteurMatching\RegistreMatching;

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — SÉCURITÉ APPLICATIVE DU MATCHING (CAP-CORE-021, doc 05 §14)\n\n";

$pdo = Magasin::connecter(':memory:');
$registre = new RegistreMatching($pdo);
$acteur = 'IDN-TEST-SECURITE';

function objetCandidatP6(string $ref): array
{
    return [
        'role_objet' => 'CANDIDAT', 'objet_type' => 'PERSONNE', 'objet_reference_externe' => $ref,
        'source_reference' => 'SRC-TEST', 'contrat_reference' => 'CTR-MAT-02', 'valide_depuis' => '2026-01-01T00:00:00Z',
        'classification' => 'INTERNE',
    ];
}

$contexte = $registre->inscrireContexte([
    'code_canonique' => 'WASPLEX_AUDIENCE', 'nom' => 'Test sécurité', 'finalite' => 'Épreuve automatisée',
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0', 'classification' => 'INTERNE',
    'supervision_humaine' => 'AUCUNE', 'score_autorise' => true, 'segment_autorise' => true,
    'activation_autorisee' => true, 'mesure_autorisee' => true, 'source_reference' => 'SRC-TEST',
], $acteur);
$registre->activerContexte($contexte['reference'], $acteur);
$profil = $registre->compilerProfil([
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0', 'contexte_reference' => $contexte['reference'],
    'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'criteres' => [['critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'traitement_inconnu' => 'INDETERMINE', 'obligatoire' => true, 'poids' => 1.0, 'sources_autorisees' => ['SRC-TEST'], 'facteur_public_autorise' => true]],
], $acteur);
$registre->activerProfil($profil['reference'], 'PRV-TEST-SIMULATION', $acteur);

echo "Injection SQL par les champs texte\n";

$chargeSql = "x'; DROP TABLE matching_demande; --";
$demandeInjection = $registre->soumettreDemande([
    'idempotency_key' => $chargeSql, 'consommateur_produit' => 'PRD-TEST-MATCHING', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => $chargeSql, 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST',
    'mode_resultat' => 'CLASSEMENT', 'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'correlation_id' => 'COR-SEC-001', 'objets' => [objetCandidatP6($chargeSql)],
    'criteres' => [['critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'valeur_normalisee' => $chargeSql, 'obligatoire' => true, 'origine' => 'POLITIQUE']],
], $acteur);
$verifier(!isset($demandeInjection['refus']), 'une charge SQL malveillante est acceptée comme simple texte, sans erreur');
$tableIntacte = (int) $pdo->query('SELECT COUNT(*) FROM matching_demande')->fetchColumn();
$verifier($tableIntacte >= 1, "1. la table matching_demande existe toujours après la tentative d'injection (requêtes préparées)");

$lecture = $registre->resoudreDemande((string) $demandeInjection['reference']);
$verifier($lecture !== null && $lecture['finalite_reference'] === $chargeSql, 'la charge est conservée verbatim comme donnée, jamais interprétée');

echo "\nRefus d'une politique portant une charge non sérialisable\n";

$exceptionLevee = false;
try {
    CompilateurPolitique::compiler([
        'politique_reference' => 'POL-X', 'politique_version' => '1', 'contexte_reference' => 'CTX-X',
        'contrat_reference' => 'CTR-X', 'contrat_version' => '1',
        'criteres' => [['critere_reference' => 'CRT-X', 'operateur' => 'EQ', 'traitement_inconnu' => 'INDETERMINE', 'poids_objet_suspect' => new stdClass()]],
    ]);
} catch (ExceptionMatching) {
    $exceptionLevee = true;
}
$verifier($exceptionLevee, "2. une spécification de politique contenant un objet PHP est rejetée avant compilation, jamais sérialisée dans le plan");

echo "\nValeurs numériques extrêmes et dates invalides (Apparieur)\n";

$verifier(Apparieur::evaluer('GTE', NAN, 10) === null, "3. NAN ne produit jamais un résultat déterminé (ni vrai ni faux)");
$verifier(Apparieur::evaluer('GTE', INF, 10) === true, '4. une valeur infinie positive reste comparable sans crash');
$verifier(Apparieur::evaluer('GTE', -INF, 10) === false, '5. une valeur infinie négative reste comparable sans crash');
$verifier(Apparieur::evaluer('GTE', '1e400', 10) === true, "6. un nombre au-delà de la plage flottante ne fait pas planter la comparaison (PHP le traite comme INF)");
$verifier(Apparieur::evaluer('EQ', "1' OR '1'='1", 'ABJ') === false, "7. une charge d'injection comme simple chaîne comparée normalement, jamais évaluée comme condition");
$verifier(Apparieur::evaluer('VALID_AT', ['depuis' => 'ceci-nest-pas-une-date', 'jusqua' => null], null, '2026-01-01T00:00:00Z') === null, '8. une date malformée produit INDETERMINE (null), jamais une exception ni un résultat inventé');
$verifier(Apparieur::evaluer('BETWEEN', 'texte', [1, 10]) === null, '9. une valeur non numérique sur un opérateur numérique ne plante pas, reste INDETERMINE');

echo "\nRejeu d'idempotency_key avec un contenu différent\n";

$premiereSoumission = $registre->soumettreDemande([
    'idempotency_key' => 'IDEMP-SEC-REJEU', 'consommateur_produit' => 'PRD-TEST-MATCHING', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'Original', 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST',
    'mode_resultat' => 'CLASSEMENT', 'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'correlation_id' => 'COR-SEC-002', 'objets' => [objetCandidatP6('CAND-SEC-A')],
    'criteres' => [['critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'valeur_normalisee' => 'ABJ', 'obligatoire' => true, 'origine' => 'POLITIQUE']],
], $acteur);
$rejeuAutreContenu = $registre->soumettreDemande([
    'idempotency_key' => 'IDEMP-SEC-REJEU', 'consommateur_produit' => 'PRD-AUTRE-TENTATIVE', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'Contenu falsifié', 'realm_reference' => 'RLM-GAMAD-999', 'environnement' => 'PRODUCTION',
    'mode_resultat' => 'CLASSEMENT', 'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'correlation_id' => 'COR-SEC-003', 'objets' => [objetCandidatP6('CAND-SEC-B')],
    'criteres' => [],
], $acteur);
$verifier(
    ($rejeuAutreContenu['idempotent'] ?? false) === true && $rejeuAutreContenu['reference'] === $premiereSoumission['reference'],
    "10. rejouer la même idempotency_key avec un contenu différent renvoie la demande d'origine, jamais le nouveau contenu",
);
$demandeVerifiee = $registre->resoudreDemande((string) $premiereSoumission['reference']);
$verifier($demandeVerifiee['consommateur_produit'] === 'PRD-TEST-MATCHING' && $demandeVerifiee['realm_reference'] === 'RLM-GAMAD-001', "11. la demande d'origine n'est jamais modifiée par une tentative de rejeu falsifiée");

echo "\nVolumes hors bornes\n";

$troisCentCriteres = [];
for ($i = 0; $i < PolitiqueMatching::MATCHING_MAX_CRITERIA + 1; $i++) {
    $troisCentCriteres[] = ['critere_reference' => "CRT-{$i}", 'operateur' => 'EQ', 'valeur_normalisee' => 'x', 'origine' => 'POLITIQUE'];
}
$refusCriteres = $registre->soumettreDemande([
    'idempotency_key' => 'IDEMP-SEC-CRITERES', 'consommateur_produit' => 'PRD-TEST-MATCHING', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'x', 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST', 'mode_resultat' => 'CLASSEMENT',
    'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1', 'correlation_id' => 'COR-SEC-004',
    'objets' => [objetCandidatP6('CAND-SEC-C')], 'criteres' => $troisCentCriteres,
], $acteur);
$verifier(($refusCriteres['refus'] ?? null) === 'MATCHING_LIMIT_EXCEEDED', '12. une demande avec plus de critères que la limite autorisée est refusée, jamais silencieusement tronquée');

$troisCentUnObjets = [];
for ($i = 0; $i < PolitiqueMatching::MATCHING_MAX_CANDIDATES + 1; $i++) {
    $troisCentUnObjets[] = objetCandidatP6("CAND-VOLUME-{$i}");
}
$refusObjets = $registre->soumettreDemande([
    'idempotency_key' => 'IDEMP-SEC-OBJETS', 'consommateur_produit' => 'PRD-TEST-MATCHING', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'x', 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST', 'mode_resultat' => 'CLASSEMENT',
    'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1', 'correlation_id' => 'COR-SEC-005',
    'objets' => $troisCentUnObjets, 'criteres' => [],
], $acteur);
$verifier(($refusObjets['refus'] ?? null) === 'MATCHING_LIMIT_EXCEEDED', '13. une population soumise au-delà de la limite autorisée est refusée, jamais silencieusement tronquée');

echo "\nAucun champ client ne dicte un score, une confiance ou une classe de résultat\n";

$colonnesObjet = array_column($pdo->query('PRAGMA table_info(matching_objet)')->fetchAll(), 'name');
$colonnesCritereDemande = array_column($pdo->query('PRAGMA table_info(matching_critere_demande)')->fetchAll(), 'name');
$verifier(
    array_intersect(['pertinence', 'confiance', 'classe_resultat', 'rang'], array_merge($colonnesObjet, $colonnesCritereDemande)) === [],
    '14. aucune table renseignée directement par le client (matching_objet, matching_critere_demande) ne porte de colonne pertinence/confiance/classe_resultat/rang — schéma, pas seulement convention applicative',
);
$injectionScore = $registre->soumettreDemande([
    'idempotency_key' => 'IDEMP-SEC-SCORE', 'consommateur_produit' => 'PRD-TEST-MATCHING', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'x', 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST', 'mode_resultat' => 'CLASSEMENT',
    'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1', 'correlation_id' => 'COR-SEC-006',
    'objets' => [objetCandidatP6('CAND-SEC-D')],
    'criteres' => [[
        'critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'valeur_normalisee' => 'ABJ', 'obligatoire' => true, 'origine' => 'POLITIQUE',
        // Champs étrangers au schéma, tentant d'influencer directement le résultat.
        'pertinence' => 1.0, 'confiance' => 1.0, 'classe_resultat' => 'CORRESPONDANCE_FORTE', 'rang' => 1,
    ]],
], $acteur);
$critereEnregistre = $pdo->prepare('SELECT poids_effectif FROM matching_critere_demande WHERE demande_reference = ?');
$critereEnregistre->execute([$injectionScore['reference']]);
$verifier(
    !isset($injectionScore['refus']),
    '15. des champs étrangers (pertinence, confiance, classe_resultat, rang) dans le corps de la demande sont ignorés, pas rejetés bruyamment ni acceptés comme score',
);
$execInjection = $registre->executer((string) $injectionScore['reference'], [], $acteur, '2026-08-15T00:00:00Z');
$resultatInjection = $registre->resoudreResultat((string) $execInjection['resultats'][0]);
$verifier(
    $resultatInjection['classe_resultat'] === 'INDETERMINE' && $resultatInjection['pertinence'] === null,
    '16. le résultat réel est calculé par le moteur (INDETERMINE, aucun signal fourni), jamais par les champs étrangers envoyés par le client',
);

echo "\n";
if ($echecs === 0) {
    echo "Toutes les épreuves sont vertes.\n";
    exit(0);
}
printf("%d épreuve(s) en échec.\n", $echecs);
exit(1);
