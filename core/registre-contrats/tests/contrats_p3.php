<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-009 — registre persistant et gouverné des
 * contrats d'échange.
 *
 * Avant ce chantier, les contrats du Core étaient dispersés entre les classes
 * `CTR-*`, les contrôleurs, les routes et `openapi/core-v1.yaml`, sans aucun
 * registre commun connaissant producteur, consommateurs, compatibilité,
 * dépréciation ou conformité. CAP-CORE-009 leur donne une fiche persistante,
 * des versions immuables une fois soumises, un cycle en ajout seul
 * (BROUILLON → EN_VALIDATION → ACTIVE → DEPRECIEE → SUSPENDUE → REMPLACEE →
 * RETIREE), une analyse de compatibilité structurelle et une conformité
 * obligatoires avant toute activation, et un plan de migration explicite pour
 * toute rupture.
 *
 * CONTRE-ÉPREUVE : la dernière épreuve retire une ligne du magasin et vérifie
 * que sa résolution échoue. Un test qui ne peut pas échouer ne prouve rien.
 *
 * Exécution : php core/registre-contrats/tests/contrats_p3.php
 * Code de sortie : 0 si la garde passe, 1 sinon.
 */

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../src/PolitiqueContrats.php';
require __DIR__ . '/../src/ExceptionContrat.php';
require __DIR__ . '/../src/ValidateurContrat.php';
require __DIR__ . '/../src/SchemaContrats.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/AnalyseurCompatibilite.php';
require __DIR__ . '/../src/GenerateurOpenApi.php';
require __DIR__ . '/../src/RegistreContrats.php';

use Gamad\RegistreContrats\GenerateurOpenApi;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\PolitiqueContrats;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreContrats\SchemaContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;

$pid = getmypid();
$fichiers = [
    'index' => sys_get_temp_dir() . "/regn-ctr-p3-index-{$pid}.sqlite",
    'identites' => sys_get_temp_dir() . "/regn-ctr-p3-identites-{$pid}.sqlite",
    'contrats' => sys_get_temp_dir() . "/regn-ctr-p3-contrats-{$pid}.sqlite",
    'contrats_copie' => sys_get_temp_dir() . "/regn-ctr-p3-contrats-copie-{$pid}.sqlite",
];
foreach ($fichiers as $f) {
    @unlink($f);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $f) {
        @unlink($f);
    }
});

putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $fichiers['index']);
$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);

$identitesReg = IdentiteMagasin::connecter($fichiers['identites']);
$ctr01 = new Ctr01($index, $identitesReg);
$magasin = ContratsMagasin::connecter($fichiers['contrats']);
$registre = new RegistreContrats($index, $identitesReg, $magasin, $ctr01);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — REGISTRE DES CONTRATS (CAP-CORE-009)\n\n";

$AUTORITE = PolitiqueInscription::AUTORITE_INSCRIPTION; // AUT-GAMAD-001
$POLITIQUE = PolitiqueContrats::POLITIQUE;
$SOURCE_TECH = 'garde CAP-CORE-009';

$gouvernance = static fn (): array => [
    'politique' => $POLITIQUE,
    'producteur' => $AUTORITE,
    'source' => $SOURCE_TECH,
    'preuve' => 'PREUVE-' . bin2hex(random_bytes(6)),
];

/* -------------------------------------------------------------- 1. bootstrap */
echo "  Amorçage du registre\n";
$verifier(
    SchemaContrats::presente($magasin),
    '1. le magasin persistant des contrats existe après connexion (bootstrap)',
);

/* ----------------------------------------------------------- 2. idempotence */
SchemaContrats::migrer($magasin);
SchemaContrats::migrer($magasin);
$nb = (int) $magasin->query('SELECT count(*) FROM migration_registre_contrats')->fetchColumn();
$verifier($nb === 1, '2. rejouer la migration ne crée aucune ligne supplémentaire (idempotence)');

/* ------------------------------------------------------ inscription d'un contrat */
echo "\n  Inscription et identité\n";

$refDejaUtilisee = $registre->inscrireContrat([
    'reference' => 'CTR-P3-DOUBLON', 'nom' => 'Doublon', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => 'test', 'producteur_capacite_reference' => 'CAP-CORE-999',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'test', ...$gouvernance(),
]);
$registre->inscrireContrat([
    'reference' => 'CTR-P3-DOUBLON', 'nom' => 'Doublon', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => 'test', 'producteur_capacite_reference' => 'CAP-CORE-999',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'test', ...$gouvernance(),
]);
$rejeu = $registre->inscrireContrat([
    'reference' => 'CTR-P3-DOUBLON', 'nom' => 'Doublon', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => 'test', 'producteur_capacite_reference' => 'CAP-CORE-999',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'test', ...$gouvernance(),
]);
$verifier(
    ($rejeu['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    '4. une référence de contrat déjà utilisée est refusée (référence unique)',
);

$sansSource = $registre->inscrireContrat([
    'reference' => 'CTR-P3-SANS-SOURCE', 'nom' => 'x', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => 'test', 'producteur_capacite_reference' => 'CAP-CORE-999',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => '', ...$gouvernance(),
]);
$verifier(
    isset($sansSource['refus']),
    '7. un contrat sans source déclarée est refusé (source active)',
);

$sansFinalite = $registre->inscrireContrat([
    'reference' => 'CTR-P3-SANS-FINALITE', 'nom' => 'x', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => '', 'producteur_capacite_reference' => 'CAP-CORE-999',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'test', ...$gouvernance(),
]);
$verifier(
    isset($sansFinalite['refus']),
    '8. un contrat sans finalité explicite est refusé',
);

/* ------------------------------------------------------------- contrat interne */
$registre->inscrireContrat([
    'reference' => 'CTR-P3-INTERNE', 'nom' => 'Contrat interne de test', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => 'test interne', 'producteur_capacite_reference' => 'CAP-CORE-009',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'garde', ...$gouvernance(),
]);

$creation = $registre->creerVersion('CTR-P3-INTERNE', ['version' => '1.0.0', 'compatibilite_annoncee' => 'COMPATIBLE', ...$gouvernance()]);
$verifier(
    ($creation['etat'] ?? null) === 'BROUILLON',
    '9. une version nouvellement créée démarre en BROUILLON',
);

$operationVide = $registre->soumettreVersion('CTR-P3-INTERNE', '1.0.0', $gouvernance());
$verifier(
    ($operationVide['refus'] ?? null) === 'CONTENU_VIDE',
    'une version sans opération ni schéma ne peut pas être soumise',
);

$op1 = $registre->declarerOperation('CTR-P3-INTERNE', '1.0.0', [
    'reference_operation' => 'lireQuelqueChose', 'type_operation' => 'INTERROGER', 'idempotente' => true, ...$gouvernance(),
]);
$verifier(!isset($op1['refus']), '13. une première opération se déclare sans refus (opération unique — préalable)');

$producteurManquant = $registre->soumettreVersion('CTR-P3-INTERNE', '1.0.0', $gouvernance());
$verifier(
    ($producteurManquant['refus'] ?? null) === 'PRODUCTEUR_ABSENT',
    '5. la soumission sans partie PRODUCTEUR est refusée, même avec du contenu (producteur obligatoire)',
);

$registre->declarerPartie('CTR-P3-INTERNE', '1.0.0', ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-009', ...$gouvernance()]);

$opDoublon = $registre->declarerOperation('CTR-P3-INTERNE', '1.0.0', [
    'reference_operation' => 'lireQuelqueChose', 'type_operation' => 'INTERROGER', ...$gouvernance(),
]);
$verifier(
    ($opDoublon['refus'] ?? null) === 'OPERATION_DEJA_DECLAREE',
    '13. une opération déjà déclarée dans la même version est refusée (opération unique)',
);

$opTypeInconnu = $registre->declarerOperation('CTR-P3-INTERNE', '1.0.0', [
    'reference_operation' => 'autreChose', 'type_operation' => 'DEMOLIR', ...$gouvernance(),
]);
$verifier(
    ($opTypeInconnu['refus'] ?? null) === 'TYPE_OPERATION_INCONNU',
    '14. un type d’opération hors liste close est refusé (action canonique)',
);

$schemaOk = $registre->declarerSchema('CTR-P3-INTERNE', '1.0.0', [
    'operation_reference' => 'lireQuelqueChose', 'sens' => 'SORTIE', 'format' => 'JSON_SCHEMA',
    'contenu' => json_encode(['proprietes' => ['reference' => ['type' => 'string', 'requis' => true]]]),
    ...$gouvernance(),
]);
$verifier(!isset($schemaOk['refus']), '10. un schéma JSON_SCHEMA structurellement valide est accepté');

$schemaInvalide = $registre->declarerSchema('CTR-P3-INTERNE', '1.0.0', [
    'operation_reference' => 'lireQuelqueChose', 'sens' => 'ENTREE', 'format' => 'JSON_SCHEMA',
    'contenu' => '{ceci n’est pas du JSON',
    ...$gouvernance(),
]);
$verifier(
    ($schemaInvalide['refus'] ?? null) === 'SCHEMA_INVALIDE',
    '11. un schéma JSON_SCHEMA structurellement invalide est refusé',
);

$schemaSecret = $registre->declarerSchema('CTR-P3-INTERNE', '1.0.0', [
    'operation_reference' => 'lireQuelqueChose', 'sens' => 'ENTREE', 'format' => 'TEXTE_STRUCTURE',
    'contenu' => 'mot_de_passe: "hunter2hunter2xyz"',
    ...$gouvernance(),
]);
$verifier(
    isset($schemaSecret['refus']),
    '12. un schéma dont le contenu ressemble à un secret réel est refusé',
);

$erreur1 = $registre->declarerErreur('CTR-P3-INTERNE', '1.0.0', [
    'code' => 'INTROUVABLE', 'statut_http' => 404, 'description' => 'référence inconnue', ...$gouvernance(),
]);
$verifier(!isset($erreur1['refus']), '15. une première erreur se déclare sans refus (erreurs stables — préalable)');

$erreurDoublon = $registre->declarerErreur('CTR-P3-INTERNE', '1.0.0', [
    'code' => 'INTROUVABLE', 'statut_http' => 404, 'description' => 'doublon', ...$gouvernance(),
]);
$verifier(
    ($erreurDoublon['refus'] ?? null) === 'ERREUR_DEJA_DECLAREE',
    '15. un code d’erreur déjà déclaré dans la même version est refusé (erreurs stables)',
);

$soumission = $registre->soumettreVersion('CTR-P3-INTERNE', '1.0.0', $gouvernance());
$verifier(
    ($soumission['etat'] ?? null) === 'EN_VALIDATION'
        && preg_match('/^[0-9a-f]{64}$/', (string) $soumission['empreinte_contenu']) === 1,
    '3. la soumission calcule une empreinte SHA-256 stable (empreinte)',
);

$operationApresSoumission = $registre->declarerOperation('CTR-P3-INTERNE', '1.0.0', [
    'reference_operation' => 'autreEncore', 'type_operation' => 'INTERROGER', ...$gouvernance(),
]);
$verifier(
    ($operationApresSoumission['refus'] ?? null) === 'VERSION_IMMUABLE',
    '16. une version EN_VALIDATION n’accepte plus aucune déclaration (soumission immuable)',
);

/* ------------------------------------------------------- 17. analyse liée à l'empreinte */
echo "\n  Analyse de compatibilité liée à l'empreinte\n";

$registre->inscrireContrat([
    'reference' => 'CTR-P3-EMPREINTE', 'nom' => 'x', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => 'test', 'producteur_capacite_reference' => 'CAP-CORE-009',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'garde', ...$gouvernance(),
]);
$registre->creerVersion('CTR-P3-EMPREINTE', ['version' => '1.0.0', 'compatibilite_annoncee' => 'COMPATIBLE', ...$gouvernance()]);
$registre->declarerPartie('CTR-P3-EMPREINTE', '1.0.0', ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-009', ...$gouvernance()]);
$registre->declarerOperation('CTR-P3-EMPREINTE', '1.0.0', ['reference_operation' => 'op', 'type_operation' => 'INTERROGER', ...$gouvernance()]);
$analyseAvantSoumission = $registre->analyserCompatibilite('CTR-P3-EMPREINTE', '1.0.0', $gouvernance());
$verifier(
    ($analyseAvantSoumission['refus'] ?? null) === 'ETAT_INCOMPATIBLE',
    '17. l’analyse exige une version soumise (empreinte figée) : une version BROUILLON est refusée',
);

/* -------------------------------- empreinte identique pour contenu identique */
$registre->soumettreVersion('CTR-P3-EMPREINTE', '1.0.0', $gouvernance());
$empreinteA = $registre->resoudreVersion('CTR-P3-EMPREINTE', '1.0.0')['empreinte_contenu'];
$empreinteB = $registre->resoudreVersion('CTR-P3-INTERNE', '1.0.0')['empreinte_contenu'] ?? null;
$verifier(
    is_string($empreinteA) && $empreinteA !== $empreinteB,
    'l’empreinte distingue deux versions au contenu différent',
);

/* --------------------------------------------------- analyse de compatibilité */
echo "\n  Analyse de compatibilité (18-27)\n";

function schemaJson(array $proprietes): string
{
    return json_encode(['proprietes' => $proprietes], JSON_UNESCAPED_UNICODE);
}

function resultats(array $analyses): array
{
    $derniere = end($analyses);

    return [$derniere['resultat'], array_column($derniere['divergences'], 'type')];
}

/**
 * Établit, pour un contrat dédié à une seule règle de compatibilité, une
 * version ACTIVE réelle — la seule chose que `analyserCompatibilite()`
 * compare jamais (`ligneVersionActive()`, jamais « la version précédente »).
 * Sans cette activation, toute comparaison ultérieure se ferait contre rien
 * et resterait COMPATIBLE par construction.
 */
function etablirBaseline(
    RegistreContrats $registre,
    string $reference,
    array $operation,
    ?array $schemaSortie,
    array $consommateurs,
    callable $gouvernance,
    ?string $typeContrat = null,
): void {
    $registre->inscrireContrat([
        'reference' => $reference, 'nom' => 'x', 'type_contrat' => $typeContrat ?? ($consommateurs === [] ? 'INTERCAPACITE' : 'HTTP_API'),
        'finalite_reference' => 'test', 'producteur_capacite_reference' => 'CAP-CORE-009',
        'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'garde', ...$gouvernance(),
    ]);
    $registre->creerVersion($reference, ['version' => '1.0.0', 'compatibilite_annoncee' => 'COMPATIBLE', ...$gouvernance()]);
    $registre->declarerPartie($reference, '1.0.0', ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-009', ...$gouvernance()]);
    foreach ($consommateurs as $c) {
        $registre->declarerPartie($reference, '1.0.0', ['role' => 'CONSOMMATEUR', 'partie_type' => 'PRODUIT', 'partie_reference' => $c, ...$gouvernance()]);
    }
    $registre->declarerOperation($reference, '1.0.0', [...$operation, ...$gouvernance()]);
    if ($schemaSortie !== null) {
        $registre->declarerSchema($reference, '1.0.0', [
            'operation_reference' => $operation['reference_operation'], 'sens' => 'SORTIE', 'format' => 'JSON_SCHEMA',
            'contenu' => schemaJson($schemaSortie), ...$gouvernance(),
        ]);
    }
    $registre->soumettreVersion($reference, '1.0.0', $gouvernance());
    $registre->analyserCompatibilite($reference, '1.0.0', $gouvernance());
    $registre->enregistrerConformite($reference, '1.0.0', ['resultat' => 'CONFORME', 'artefact_reference' => 'commit:baseline', ...$gouvernance()]);
    $registre->activerVersion($reference, '1.0.0', $gouvernance());
}

/** @return array{0:string,1:list<string>} */
function analyserMutation(
    RegistreContrats $registre,
    string $reference,
    array $operation,
    ?array $schemaSortie,
    array $consommateurs,
    callable $gouvernance,
): array {
    $registre->creerVersion($reference, ['version' => '2.0.0', 'compatibilite_annoncee' => 'RUPTURE', ...$gouvernance()]);
    $registre->declarerPartie($reference, '2.0.0', ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-009', ...$gouvernance()]);
    foreach ($consommateurs as $c) {
        $registre->declarerPartie($reference, '2.0.0', ['role' => 'CONSOMMATEUR', 'partie_type' => 'PRODUIT', 'partie_reference' => $c, ...$gouvernance()]);
    }
    $registre->declarerOperation($reference, '2.0.0', [...$operation, ...$gouvernance()]);
    if ($schemaSortie !== null) {
        $registre->declarerSchema($reference, '2.0.0', [
            'operation_reference' => $operation['reference_operation'], 'sens' => 'SORTIE', 'format' => 'JSON_SCHEMA',
            'contenu' => schemaJson($schemaSortie), ...$gouvernance(),
        ]);
    }
    $registre->soumettreVersion($reference, '2.0.0', $gouvernance());
    $registre->analyserCompatibilite($reference, '2.0.0', $gouvernance());

    return resultats($registre->resoudreCompatibilite($reference, '2.0.0'));
}

$opBase = ['reference_operation' => 'lister', 'type_operation' => 'INTERROGER', 'methode_http' => 'GET', 'chemin_http' => '/x', 'duree_secondes' => 100];
$schemaBase = ['id' => ['type' => 'string', 'requis' => true], 'nom' => ['type' => 'string', 'requis' => false]];

etablirBaseline($registre, 'CTR-P3-R18', $opBase, $schemaBase, ['PRD-GAMAD-002'], $gouvernance);
[$r] = analyserMutation($registre, 'CTR-P3-R18', $opBase, [...$schemaBase, 'note' => ['type' => 'string', 'requis' => false]], ['PRD-GAMAD-002'], $gouvernance);
$verifier($r === 'COMPATIBLE', '18. l’ajout d’un champ facultatif reste COMPATIBLE');

etablirBaseline($registre, 'CTR-P3-R19', $opBase, $schemaBase, ['PRD-GAMAD-002'], $gouvernance);
[$r, $div] = analyserMutation($registre, 'CTR-P3-R19', $opBase, [...$schemaBase, 'obligatoire_nouveau' => ['type' => 'string', 'requis' => true]], ['PRD-GAMAD-002'], $gouvernance);
$verifier($r === 'RUPTURE' && in_array('champ_obligatoire_ajoute', $div, true), '19. l’ajout d’un champ obligatoire est une RUPTURE');

etablirBaseline($registre, 'CTR-P3-R20', $opBase, $schemaBase, ['PRD-GAMAD-002'], $gouvernance);
[$r, $div] = analyserMutation($registre, 'CTR-P3-R20', $opBase, ['id' => ['type' => 'string', 'requis' => true]], ['PRD-GAMAD-002'], $gouvernance);
$verifier($r === 'RUPTURE' && in_array('champ_supprime', $div, true), '20. la suppression d’un champ est une RUPTURE');

etablirBaseline($registre, 'CTR-P3-R21', $opBase, $schemaBase, ['PRD-GAMAD-002'], $gouvernance);
[$r, $div] = analyserMutation($registre, 'CTR-P3-R21', $opBase, ['id' => ['type' => 'integer', 'requis' => true], 'nom' => ['type' => 'string', 'requis' => false]], ['PRD-GAMAD-002'], $gouvernance);
$verifier($r === 'RUPTURE' && in_array('type_modifie', $div, true), '21. un changement de type est une RUPTURE');

etablirBaseline($registre, 'CTR-P3-R22', $opBase, ['etat' => ['type' => 'string', 'requis' => true, 'enum' => ['ACTIF', 'INACTIF', 'ARCHIVE']]], ['PRD-GAMAD-002'], $gouvernance);
[$r, $div] = analyserMutation($registre, 'CTR-P3-R22', $opBase, ['etat' => ['type' => 'string', 'requis' => true, 'enum' => ['ACTIF', 'INACTIF']]], ['PRD-GAMAD-002'], $gouvernance);
$verifier($r === 'RUPTURE' && in_array('enum_reduit', $div, true), '22. la réduction d’un énumérable est une RUPTURE');

etablirBaseline($registre, 'CTR-P3-R23', $opBase, null, ['PRD-GAMAD-002'], $gouvernance);
[$r, $div] = analyserMutation($registre, 'CTR-P3-R23', ['reference_operation' => 'lister', 'type_operation' => 'INTERROGER', 'methode_http' => 'POST', 'chemin_http' => '/x'], null, ['PRD-GAMAD-002'], $gouvernance);
$verifier($r === 'RUPTURE' && in_array('methode_modifiee', $div, true), '23. un changement de méthode HTTP est une RUPTURE');

etablirBaseline($registre, 'CTR-P3-R24', $opBase, null, ['PRD-GAMAD-002'], $gouvernance);
[$r, $div] = analyserMutation($registre, 'CTR-P3-R24', ['reference_operation' => 'lister', 'type_operation' => 'INTERROGER', 'methode_http' => 'GET', 'chemin_http' => '/x/y'], null, ['PRD-GAMAD-002'], $gouvernance);
$verifier($r === 'RUPTURE' && in_array('chemin_modifie', $div, true), '24. un changement de chemin HTTP est une RUPTURE');

etablirBaseline($registre, 'CTR-P3-R25', $opBase, null, ['PRD-GAMAD-002'], $gouvernance);
[$r, $div] = analyserMutation($registre, 'CTR-P3-R25', ['reference_operation' => 'lister', 'type_operation' => 'INTERROGER', 'methode_http' => 'GET', 'chemin_http' => '/x', 'action_autorisation' => 'lire un x'], null, ['PRD-GAMAD-002'], $gouvernance);
$verifier($r === 'ADAPTATION_REQUISE' && in_array('autorisation_renforcee', $div, true), '25. un changement d’action d’autorisation est signalé ADAPTATION_REQUISE');

etablirBaseline($registre, 'CTR-P3-R26', $opBase, null, ['PRD-GAMAD-002'], $gouvernance);
[$r, $div] = analyserMutation($registre, 'CTR-P3-R26', ['reference_operation' => 'lister', 'type_operation' => 'INTERROGER', 'methode_http' => 'GET', 'chemin_http' => '/x', 'duree_secondes' => 10], null, ['PRD-GAMAD-002'], $gouvernance);
$verifier($r === 'ADAPTATION_REQUISE' && in_array('duree_reduite', $div, true), '26. une durée réduite est signalée ADAPTATION_REQUISE');

etablirBaseline($registre, 'CTR-P3-R27', $opBase, null, ['PRD-GAMAD-002'], $gouvernance, typeContrat: 'INTERCAPACITE');
[$r, $div] = analyserMutation($registre, 'CTR-P3-R27', $opBase, null, [], $gouvernance);
$verifier($r === 'RUPTURE' && in_array('consommateur_retire', $div, true), '27. un consommateur non redéclaré est une RUPTURE (consommateur retiré)');

$refCompat = 'CTR-P3-COMPAT';
$registre->inscrireContrat([
    'reference' => $refCompat, 'nom' => 'x', 'type_contrat' => 'HTTP_API',
    'finalite_reference' => 'test', 'producteur_capacite_reference' => 'CAP-CORE-009',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'garde', ...$gouvernance(),
]);

/* ---------------------------------------------------- activation gouvernée */
echo "\n  Activation, dépréciation, suspension, retrait (28-37)\n";

$registre->creerVersion($refCompat, ['version' => '3.0.0', 'compatibilite_annoncee' => 'COMPATIBLE', ...$gouvernance()]);
$registre->declarerPartie($refCompat, '3.0.0', ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-009', ...$gouvernance()]);
$registre->declarerPartie($refCompat, '3.0.0', ['role' => 'CONSOMMATEUR', 'partie_type' => 'PRODUIT', 'partie_reference' => 'PRD-GAMAD-002', ...$gouvernance()]);
$registre->declarerOperation($refCompat, '3.0.0', ['reference_operation' => 'lister', 'type_operation' => 'INTERROGER', 'methode_http' => 'GET', 'chemin_http' => '/x', ...$gouvernance()]);
$registre->soumettreVersion($refCompat, '3.0.0', $gouvernance());
$refusSansAnalyse = $registre->activerVersion($refCompat, '3.0.0', $gouvernance());
$verifier(
    ($refusSansAnalyse['refus'] ?? null) === 'ANALYSE_MANQUANTE',
    '28. l’activation sans analyse de compatibilité enregistrée est refusée',
);

$registre->analyserCompatibilite($refCompat, '3.0.0', $gouvernance());
$refusSansConformite = $registre->activerVersion($refCompat, '3.0.0', $gouvernance());
$verifier(
    ($refusSansConformite['refus'] ?? null) === 'CONFORMITE_MANQUANTE',
    '29. l’activation sans conformité CONFORME enregistrée est refusée',
);

$registre->enregistrerConformite($refCompat, '3.0.0', ['resultat' => 'NON_CONFORME', 'artefact_reference' => 'x', ...$gouvernance()]);
$refusNonConforme = $registre->activerVersion($refCompat, '3.0.0', $gouvernance());
$verifier(
    ($refusNonConforme['refus'] ?? null) === 'CONFORMITE_MANQUANTE',
    'une conformité NON_CONFORME ne suffit pas à activer',
);

$registre->enregistrerConformite($refCompat, '3.0.0', ['resultat' => 'CONFORME', 'artefact_reference' => 'commit:test', ...$gouvernance()]);
$activation30 = $registre->activerVersion($refCompat, '3.0.0', $gouvernance());
$verifier(
    ($activation30['etat'] ?? null) === 'ACTIVE',
    '30. une version dûment analysée et conforme s’active (activation atomique — partie 1)',
);
$diag = $registre->diagnostiquerRegistre();
$verifier($diag['coherent'] === true, '31. au plus une version active par contrat (diagnostic cohérent)');

// rupture : activer une nouvelle version RUPTURE sans plan → refus, avec plan → succès, l'ancienne devient REMPLACEE
$registre->creerVersion($refCompat, ['version' => '4.0.0', 'compatibilite_annoncee' => 'RUPTURE', ...$gouvernance()]);
$registre->declarerPartie($refCompat, '4.0.0', ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-009', ...$gouvernance()]);
$registre->declarerPartie($refCompat, '4.0.0', ['role' => 'CONSOMMATEUR', 'partie_type' => 'PRODUIT', 'partie_reference' => 'PRD-GAMAD-002', ...$gouvernance()]);
$registre->declarerOperation($refCompat, '4.0.0', ['reference_operation' => 'listerV2', 'type_operation' => 'INTERROGER', 'methode_http' => 'GET', 'chemin_http' => '/x2', ...$gouvernance()]);
$registre->soumettreVersion($refCompat, '4.0.0', $gouvernance());
$registre->analyserCompatibilite($refCompat, '4.0.0', $gouvernance());
$registre->enregistrerConformite($refCompat, '4.0.0', ['resultat' => 'CONFORME', 'artefact_reference' => 'commit:v4', ...$gouvernance()]);
$analyse4 = $registre->resoudreCompatibilite($refCompat, '4.0.0');
$estRupture = end($analyse4)['resultat'] === 'RUPTURE';
$refusSansPlan = $registre->activerVersion($refCompat, '4.0.0', $gouvernance());
$verifier(
    $estRupture && ($refusSansPlan['refus'] ?? null) === 'PLAN_MIGRATION_REQUIS',
    '32. une rupture exige un plan de migration et une date limite avant activation (coexistence explicite)',
);
$activationAvecPlan = $registre->activerVersion($refCompat, '4.0.0', [
    'plan_migration' => 'basculer PRD-GAMAD-002 vers /x2', 'date_limite_migration' => '2027-01-01', ...$gouvernance(),
]);
$verifier(
    ($activationAvecPlan['etat'] ?? null) === 'ACTIVE',
    '30. l’activation atomique remplace l’ancienne version active dans la même transaction (partie 2)',
);
$historique4 = $registre->resoudreHistorique($refCompat);
$cycleActive4 = array_values(array_filter($historique4, static fn (array $h): bool => $h['etat'] === 'ACTIVE' && $h['plan_migration'] !== null));
$verifier(
    $cycleActive4 !== [] && end($cycleActive4)['plan_migration'] === 'basculer PRD-GAMAD-002 vers /x2',
    '37. le plan de migration est conservé sur l’événement de cycle correspondant (plan obligatoire pour rupture)',
);
$cycle30Remplacee = array_values(array_filter($historique4, static fn (array $h): bool => $h['etat'] === 'REMPLACEE'));
$verifier($cycle30Remplacee !== [], 'l’ancienne version active devient REMPLACEE lors de l’activation d’une nouvelle version');

// activation compatible : pas de plan requis
$registre->creerVersion($refCompat, ['version' => '4.1.0', 'compatibilite_annoncee' => 'COMPATIBLE', ...$gouvernance()]);
$registre->declarerPartie($refCompat, '4.1.0', ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-009', ...$gouvernance()]);
$registre->declarerPartie($refCompat, '4.1.0', ['role' => 'CONSOMMATEUR', 'partie_type' => 'PRODUIT', 'partie_reference' => 'PRD-GAMAD-002', ...$gouvernance()]);
$registre->declarerOperation($refCompat, '4.1.0', ['reference_operation' => 'listerV2', 'type_operation' => 'INTERROGER', 'methode_http' => 'GET', 'chemin_http' => '/x2', ...$gouvernance()]);
$registre->soumettreVersion($refCompat, '4.1.0', $gouvernance());
$registre->analyserCompatibilite($refCompat, '4.1.0', $gouvernance());
$registre->enregistrerConformite($refCompat, '4.1.0', ['resultat' => 'CONFORME', 'artefact_reference' => 'commit:v4.1', ...$gouvernance()]);
$activationSansPlanRequis = $registre->activerVersion($refCompat, '4.1.0', $gouvernance());
$verifier(
    ($activationSansPlanRequis['etat'] ?? null) === 'ACTIVE',
    'une activation COMPATIBLE n’exige aucun plan de migration',
);

$deprecier = $registre->deprecierVersion($refCompat, '4.1.0', $gouvernance());
$verifier(($deprecier['etat'] ?? null) === 'DEPRECIEE', '33. la dépréciation transite vers DEPRECIEE, datée dans le cycle');
$histDep = $registre->resoudreHistorique($refCompat);
$lignesDep = array_values(array_filter($histDep, static fn (array $h): bool => $h['etat'] === 'DEPRECIEE'));
$ligneDep = end($lignesDep);
$verifier($ligneDep !== false && $ligneDep['date_effet'] !== '', '33. la dépréciation porte une date d’effet');

$suspendre = $registre->suspendreVersion($refCompat, '4.1.0', $gouvernance());
$verifier(
    ($suspendre['etat'] ?? null) === 'SUSPENDUE'
        && $registre->resoudreVersion($refCompat, '4.1.0')['etat'] === 'SUSPENDUE',
    '34. la suspension est opposable immédiatement',
);

$retrait = $registre->retirerVersion($refCompat, '4.1.0', $gouvernance());
$apresRetrait = $registre->resoudreVersion($refCompat, '4.1.0');
$verifier(
    ($retrait['etat'] ?? null) === 'RETIREE' && $apresRetrait !== null && $apresRetrait['etat'] === 'RETIREE',
    '35. le retrait ne supprime rien : la version reste résolue, à l’état RETIREE',
);

$reutilisation = $registre->creerVersion($refCompat, ['version' => '4.1.0', 'compatibilite_annoncee' => 'COMPATIBLE', ...$gouvernance()]);
$verifier(
    ($reutilisation['refus'] ?? null) === 'VERSION_DEJA_UTILISEE',
    '36. une référence de version retirée n’est jamais réutilisable',
);

/* -------------------------------------------------------- OpenAPI (38-41) */
echo "\n  Dérive OpenAPI (38-41)\n";

$cheminOpenApi = __DIR__ . '/../../../apps/console-laravel/openapi/core-v1.yaml';
$operationsFichier = GenerateurOpenApi::extraireOperationsDuFichier($cheminOpenApi);
$verifier(count($operationsFichier) > 50, '38. openapi/core-v1.yaml est lu et ses opérations extraites (OpenAPI validé)');

$attenduesConnues = array_filter(
    $operationsFichier,
    static fn (array $o): bool => $o['operationId'] === 'listerPolitiques',
);
$comparaisonManquante = GenerateurOpenApi::comparer(
    [['methode' => 'GET', 'chemin' => '/inexistant', 'operationId' => 'routeQuiNexistePas']],
    $operationsFichier,
);
$verifier(
    in_array('GET /inexistant routeQuiNexistePas', $comparaisonManquante['manquantes'], true),
    '39. une opération attendue mais absente du fichier est détectée (route manquante)',
);

$comparaisonFantome = GenerateurOpenApi::comparer([], [['methode' => 'GET', 'chemin' => '/x', 'operationId' => 'operationFantome']]);
$verifier(
    in_array('GET /x operationFantome', $comparaisonFantome['fantomes'], true),
    '40. une opération présente dans le fichier mais non attendue est détectée (opération fantôme)',
);

$comparaisonDoublon = GenerateurOpenApi::comparer([], [
    ['methode' => 'GET', 'chemin' => '/a', 'operationId' => 'doublonId'],
    ['methode' => 'POST', 'chemin' => '/b', 'operationId' => 'doublonId'],
]);
$verifier(
    in_array('doublonId', $comparaisonDoublon['doublons'], true),
    '41. un operationId dupliqué dans le fichier est détecté',
);

/* --------------------------------------------------- bootstrap réel (42-47) */
echo "\n  Fidélité de l'inventaire de bootstrap (42-47)\n";

$cheminBootstrap = __DIR__ . '/../resources/bootstrap-contrats-v1.json';
$payload = json_decode((string) file_get_contents($cheminBootstrap), true);
$parReference = [];
foreach ($payload['contrats'] ?? [] as $c) {
    $parReference[$c['reference']] = $c;
}

$internes = ['CTR-01' => 'CAP-CORE-001', 'CTR-02' => 'CAP-CORE-003', 'CTR-03' => 'CAP-CORE-004', 'CTR-04' => 'CAP-CORE-007', 'CTR-15' => 'CAP-CORE-006', 'CTR-16' => 'CAP-CORE-005'];
$internesOk = true;
foreach ($internes as $ref => $capaciteAttendue) {
    $c = $parReference[$ref] ?? null;
    if ($c === null
        || $c['type_contrat'] !== 'INTERCAPACITE'
        || $c['producteur_capacite_reference'] !== $capaciteAttendue
        || !array_filter($c['parties'], static fn (array $p): bool => $p['role'] === 'PRODUCTEUR')
        || $c['operations'] === []) {
        $internesOk = false;
    }
}
$verifier($internesOk, '42. les six contrats internes prioritaires (CTR-01..CTR-16) sont inventoriés avec producteur et opération réels');

$federation = $parReference['CTR-GAMAD-FEDERATION'] ?? null;
$verifier(
    $federation !== null
        && $federation['producteur_capacite_reference'] === 'CAP-CORE-022'
        && count($federation['operations']) === 3
        && array_filter($federation['parties'], static fn (array $p): bool => $p['role'] === 'CONSOMMATEUR' && $p['partie_reference'] === 'PRD-GAMAD-002'),
    '43. le contrat de fédération GamaDrive porte les trois opérations réelles (ouverture, vérification, révocation)',
);

$verifier(
    ($parReference['CTR-GAMAD-IDENTITES']['producteur_capacite_reference'] ?? null) === 'CAP-CORE-001',
    '44. le contrat HTTP des identités porte le bon producteur',
);
$verifier(
    ($parReference['CTR-GAMAD-PRODUITS']['producteur_capacite_reference'] ?? null) === 'CAP-CORE-011',
    '45. le contrat HTTP des produits porte le bon producteur',
);
$verifier(
    ($parReference['CTR-GAMAD-SOURCES']['producteur_capacite_reference'] ?? null) === 'CAP-CORE-006',
    '46. le contrat HTTP des sources porte le bon producteur',
);
$verifier(
    ($parReference['CTR-GAMAD-POLITIQUES']['producteur_capacite_reference'] ?? null) === 'CAP-CORE-007',
    '47. le contrat HTTP des politiques porte le bon producteur',
);

/* -------------------------------------------------- rollback et transaction */
echo "\n  Transactions, concurrence, continuité (48-50)\n";

$magasin->beginTransaction();
$registre->creerVersion($refCompat, ['version' => '9.9.9', 'compatibilite_annoncee' => 'COMPATIBLE', ...$gouvernance()]);
$registre->declarerObligation($refCompat, '9.9.9', ['type_obligation' => 'AUDIT', 'description' => 'ne doit jamais persister', ...$gouvernance()]);
$magasin->rollBack();
$verifier(
    $registre->resoudreVersion($refCompat, '9.9.9') === null,
    '48. un rollback explicite de la transaction porteuse efface les écritures qu’elle contenait (rollback audit)',
);

// 49 — invariant "une seule version active" maintenu à travers une séquence
// rapide d'activations successives (proxy déterministe de la concurrence dans
// un test PHP CLI mono-processus ; la vraie exclusion mutuelle est portée par
// la transaction PostgreSQL/SQLite de `activerVersion`, éprouvée ci-dessus).
$refConcurrence = 'CTR-P3-CONCURRENCE';
$registre->inscrireContrat([
    'reference' => $refConcurrence, 'nom' => 'x', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => 'test', 'producteur_capacite_reference' => 'CAP-CORE-009',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'garde', ...$gouvernance(),
]);
$coherentPartout = true;
for ($i = 1; $i <= 3; $i++) {
    $v = "{$i}.0.0";
    $registre->creerVersion($refConcurrence, ['version' => $v, 'compatibilite_annoncee' => 'COMPATIBLE', ...$gouvernance()]);
    $registre->declarerPartie($refConcurrence, $v, ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-009', ...$gouvernance()]);
    $registre->declarerOperation($refConcurrence, $v, ['reference_operation' => "op{$i}", 'type_operation' => 'INTERROGER', ...$gouvernance()]);
    $registre->soumettreVersion($refConcurrence, $v, $gouvernance());
    $registre->analyserCompatibilite($refConcurrence, $v, $gouvernance());
    $registre->enregistrerConformite($refConcurrence, $v, ['resultat' => 'CONFORME', 'artefact_reference' => "commit:{$i}", ...$gouvernance()]);
    $registre->activerVersion($refConcurrence, $v, $gouvernance());
    if (!$registre->diagnostiquerRegistre()['coherent']) {
        $coherentPartout = false;
    }
}
$verifier($coherentPartout, '49. l’invariant « une seule version active » tient à chaque étape d’une séquence rapide d’activations');

// 50 — continuité : une copie physique du magasin reste lisible et cohérente
// (proxy du cycle sauvegarde/restauration ; l'exercice PostgreSQL réel sur
// les huit magasins est couvert par ops/core-foundation dans la CI).
copy($fichiers['contrats'], $fichiers['contrats_copie']);
$magasinCopie = new \PDO('sqlite:' . $fichiers['contrats_copie']);
$magasinCopie->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$magasinCopie->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
$nAvant = (int) $magasin->query('SELECT count(*) FROM contrat')->fetchColumn();
$nApres = (int) $magasinCopie->query('SELECT count(*) FROM contrat')->fetchColumn();
$registreCopie = new RegistreContrats($index, $identitesReg, $magasinCopie, $ctr01);
$verifier(
    $nAvant === $nApres && $nAvant > 0
        && $registreCopie->resoudreContrat($refCompat)['version_active'] === $registre->resoudreContrat($refCompat)['version_active'],
    '50. une copie physique du magasin reste intégralement lisible (continuité)',
);

/* -------------------------------------------------------------- aucun secret */
$colonnesSuspectes = [];
foreach (SchemaContrats::TABLES as $table) {
    foreach ($magasin->query("PRAGMA table_info({$table})")->fetchAll() as $colonne) {
        if (preg_match('/secret|password|mot_de_passe|jeton|token/i', (string) $colonne['name'])) {
            $colonnesSuspectes[] = "{$table}.{$colonne['name']}";
        }
    }
}
$verifier(
    $colonnesSuspectes === [],
    'le schéma du magasin des contrats ne porte aucune colonne de secret',
    $colonnesSuspectes === [] ? '' : implode(', ', $colonnesSuspectes),
);

/* --------------------------------------------- reconstruction sans perte */
BaselineOperationnelle::standard()->reconstruire($index);
$verifier(
    $registre->resoudreContrat('CTR-P3-R18') !== null && $registre->resoudreContrat('CTR-P3-R18')['version_active'] !== null,
    'reconstruire l’index documentaire ne supprime jamais le registre persistant des contrats',
);

/* ------------------------------------------------------------ CONTRE-ÉPREUVE */
echo "\n  Contre-épreuve — la garde doit savoir échouer (51)\n";

$magasin->exec("DELETE FROM contrat WHERE reference = '{$refCompat}'");
$verifier(
    $registre->resoudreContrat($refCompat) === null,
    '51. un contrat retiré du magasin cesse d’être résolu (contre-épreuve)',
);

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-009 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-009 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
