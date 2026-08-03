<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-010 — registre persistant et gouverné du
 * vocabulaire canonique.
 *
 * Avant ce chantier, les valeurs canoniques (états, types, rôles) étaient
 * dispersées entre contraintes `CHECK` SQL et constantes PHP de chaque
 * capacité, sans aucun registre commun connaissant leur définition, leurs
 * libellés localisés, leurs alias historiques, leurs relations sémantiques,
 * leurs mappings externes ou leurs consommateurs. CAP-CORE-010 leur donne une
 * fiche persistante, des versions immuables une fois soumises, un cycle en
 * ajout seul (BROUILLON → EN_VALIDATION → ACTIVE → DEPRECIEE/REMPLACEE →
 * RETIREE), une analyse de compatibilité structurelle et une conformité
 * obligatoires avant toute activation. Ce registre décrit le vocabulaire ; il
 * ne remplace aucune contrainte `CHECK` ni aucune constante PHP existante
 * (`GenerateurProjection::comparerCodes()` ne fait que détecter l'écart).
 *
 * CONTRE-ÉPREUVE : la dernière épreuve retire une ligne du magasin et vérifie
 * que sa résolution échoue. Un test qui ne peut pas échouer ne prouve rien.
 *
 * Exécution : php core/registre-vocabulaire/tests/vocabulaire_p3.php
 * Code de sortie : 0 si la garde passe, 1 sinon.
 */

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../src/ExceptionVocabulaire.php';
require __DIR__ . '/../src/PolitiqueVocabulaire.php';
require __DIR__ . '/../src/ValidateurTerme.php';
require __DIR__ . '/../src/SchemaVocabulaire.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/AnalyseurCompatibilite.php';
require __DIR__ . '/../src/GenerateurProjection.php';
require __DIR__ . '/../src/RegistreVocabulaire.php';

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreVocabulaire\GenerateurProjection;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Gamad\RegistreVocabulaire\PolitiqueVocabulaire;
use Gamad\RegistreVocabulaire\RegistreVocabulaire;
use Gamad\RegistreVocabulaire\SchemaVocabulaire;

$pid = getmypid();
$fichiers = [
    'index' => sys_get_temp_dir() . "/regn-voc-p3-index-{$pid}.sqlite",
    'identites' => sys_get_temp_dir() . "/regn-voc-p3-identites-{$pid}.sqlite",
    'vocabulaire' => sys_get_temp_dir() . "/regn-voc-p3-vocabulaire-{$pid}.sqlite",
    'vocabulaire_copie' => sys_get_temp_dir() . "/regn-voc-p3-vocabulaire-copie-{$pid}.sqlite",
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
$magasin = VocabulaireMagasin::connecter($fichiers['vocabulaire']);
$registre = new RegistreVocabulaire($index, $identitesReg, $magasin, $ctr01);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — REGISTRE DU VOCABULAIRE CANONIQUE (CAP-CORE-010)\n\n";

$AUTORITE = PolitiqueInscription::AUTORITE_INSCRIPTION; // AUT-GAMAD-001
$POLITIQUE = PolitiqueVocabulaire::POLITIQUE;
$SOURCE_TECH = 'garde CAP-CORE-010';

$gouvernance = static fn (): array => [
    'politique' => $POLITIQUE,
    'producteur' => $AUTORITE,
    'source' => $SOURCE_TECH,
    'preuve' => 'PREUVE-' . bin2hex(random_bytes(6)),
];

/* -------------------------------------------------------------- 1. bootstrap */
echo "  Amorçage du registre\n";
$verifier(
    SchemaVocabulaire::presente($magasin),
    '1. le magasin persistant du vocabulaire existe après connexion (bootstrap)',
);

/* ----------------------------------------------------------- 2. idempotence */
SchemaVocabulaire::migrer($magasin);
SchemaVocabulaire::migrer($magasin);
$nb = (int) $magasin->query('SELECT count(*) FROM migration_registre_vocabulaire')->fetchColumn();
$verifier($nb === 1, '2. rejouer la migration ne crée aucune ligne supplémentaire (idempotence)');

/* ------------------------------------------------------ inscription d'un vocabulaire */
echo "\n  Inscription et identité\n";

$dossierBase = static fn (string $ref, string $ns): array => [
    'reference' => $ref, 'namespace' => $ns, 'nom' => 'x', 'domaine' => 'test',
    'portee' => 'CORE', 'proprietaire_reference' => $AUTORITE, 'source_reference' => 'garde',
];

$sansChamp = $registre->inscrireVocabulaire(['reference' => 'VOC-P3-X', ...$gouvernance()]);
$verifier(
    ($sansChamp['refus'] ?? null) === 'DOSSIER_INCOMPLET',
    '3. un dossier d’inscription incomplet est refusé',
);

$porteeInconnue = $registre->inscrireVocabulaire([...$dossierBase('VOC-P3-PORTEE', 'gamad.p3.portee'), 'portee' => 'INEXISTANTE', ...$gouvernance()]);
$verifier(
    ($porteeInconnue['refus'] ?? null) === 'PORTEE_INCONNUE',
    '4. une portée hors liste close est refusée',
);

$registre->inscrireVocabulaire([...$dossierBase('VOC-P3-DOUBLON', 'gamad.p3.doublon'), ...$gouvernance()]);
$rejeu = $registre->inscrireVocabulaire([...$dossierBase('VOC-P3-DOUBLON', 'gamad.p3.doublon2'), ...$gouvernance()]);
$verifier(
    ($rejeu['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    '5. une référence de vocabulaire déjà utilisée est refusée (référence unique)',
);

$nsDoublon = $registre->inscrireVocabulaire([...$dossierBase('VOC-P3-AUTRE', 'gamad.p3.doublon'), ...$gouvernance()]);
$verifier(
    ($nsDoublon['refus'] ?? null) === 'NAMESPACE_DEJA_UTILISE',
    '6. un namespace déjà utilisé est refusé, même sous une autre référence',
);

$proprietaireInconnu = $registre->inscrireVocabulaire([...$dossierBase('VOC-P3-PROP', 'gamad.p3.prop'), 'proprietaire_reference' => 'AUT-INEXISTANT', ...$gouvernance()]);
$verifier(
    ($proprietaireInconnu['refus'] ?? null) === 'PROPRIETAIRE_INCONNU',
    '7. un propriétaire inconnu du registre des identités est refusé',
);

/* ------------------------------------------------------------- vocabulaire de travail */
$registre->inscrireVocabulaire([...$dossierBase('VOC-P3-BASE', 'gamad.p3.base'), ...$gouvernance()]);

$versionInconnue = $registre->creerVersion('VOC-P3-INEXISTANT', ['version' => '1.0.0', ...$gouvernance()]);
$verifier(
    ($versionInconnue['refus'] ?? null) === 'VOCABULAIRE_INCONNU',
    '8. créer une version pour un vocabulaire inconnu est refusé',
);

$sansGouvernance = $registre->creerVersion('VOC-P3-BASE', ['version' => '1.0.0']);
$verifier(
    ($sansGouvernance['refus'] ?? null) === 'COMMANDE_NON_GOUVERNEE',
    '9. une commande sans champ de gouvernance complet est refusée (politique, producteur, source, preuve)',
);

$versionInvalide = $registre->creerVersion('VOC-P3-BASE', ['version' => '1.x', ...$gouvernance()]);
$verifier(
    ($versionInvalide['refus'] ?? null) === 'VERSION_INVALIDE',
    '10. une version hors format X.Y.Z est refusée',
);

$creation = $registre->creerVersion('VOC-P3-BASE', ['version' => '1.0.0', ...$gouvernance()]);
$verifier(
    ($creation['etat'] ?? null) === 'BROUILLON',
    '11. une version nouvellement créée démarre en BROUILLON',
);

$versionDoublon = $registre->creerVersion('VOC-P3-BASE', ['version' => '1.0.0', ...$gouvernance()]);
$verifier(
    ($versionDoublon['refus'] ?? null) === 'VERSION_DEJA_UTILISEE',
    '12. une version déjà utilisée pour ce vocabulaire est refusée',
);

/* ------------------------------------------------------------------- termes */
echo "\n  Déclaration des termes\n";

$codeInvalide = $registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-1', 'code' => 'minuscule', 'type_semantique' => 'ETAT', 'definition' => 'x', ...$gouvernance(),
]);
$verifier(
    ($codeInvalide['refus'] ?? null) === 'CODE_INVALIDE',
    '13. un code hors format MAJUSCULES_SOULIGNEES est refusé',
);

$typeInconnu = $registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-1', 'code' => 'ACTIF', 'type_semantique' => 'INEXISTANT', 'definition' => 'x', ...$gouvernance(),
]);
$verifier(
    ($typeInconnu['refus'] ?? null) === 'TYPE_SEMANTIQUE_INCONNU',
    '14. un type sémantique hors liste close est refusé',
);

$definitionVide = $registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-1', 'code' => 'ACTIF', 'type_semantique' => 'ETAT', 'definition' => '   ', ...$gouvernance(),
]);
$verifier(
    ($definitionVide['refus'] ?? null) === 'DEFINITION_INVALIDE',
    '15. une définition vide est refusée',
);

$definitionExecutable = $registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-1', 'code' => 'ACTIF', 'type_semantique' => 'ETAT',
    'definition' => 'contient <?php eval($x); ?>', ...$gouvernance(),
]);
$verifier(
    ($definitionExecutable['refus'] ?? null) === 'DEFINITION_INVALIDE'
        && ($definitionExecutable['detail'] ?? null) === 'EXPRESSION_EXECUTABLE',
    '16. une définition portant une expression exécutable est refusée',
);

$definitionSecret = $registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-1', 'code' => 'ACTIF', 'type_semantique' => 'ETAT',
    'definition' => 'mot_de_passe: "hunter2hunter2xyz"', ...$gouvernance(),
]);
$verifier(
    ($definitionSecret['refus'] ?? null) === 'DEFINITION_INVALIDE'
        && ($definitionSecret['detail'] ?? null) === 'SECRET_DETECTE',
    '17. une définition ressemblant à un secret réel est refusée',
);

$ajoutTerme = $registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-ACTIF', 'code' => 'ACTIF', 'type_semantique' => 'ETAT',
    'definition' => 'le terme est actif', ...$gouvernance(),
]);
$verifier(
    ($ajoutTerme['code'] ?? null) === 'ACTIF',
    '18. un terme structurellement valide est accepté',
);

$termeRefDoublon = $registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-ACTIF', 'code' => 'AUTRE', 'type_semantique' => 'ETAT', 'definition' => 'x', ...$gouvernance(),
]);
$verifier(
    ($termeRefDoublon['refus'] ?? null) === 'TERME_REFERENCE_DEJA_UTILISEE',
    '19. une référence de terme déjà utilisée est refusée',
);

$registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-INACTIF', 'code' => 'INACTIF', 'type_semantique' => 'ETAT',
    'definition' => 'le terme est inactif', ...$gouvernance(),
]);
$codeAutreSens = $registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-NOUVEAU', 'code' => 'ACTIF', 'type_semantique' => 'ETAT', 'definition' => 'x', ...$gouvernance(),
]);
$verifier(
    ($codeAutreSens['refus'] ?? null) === 'CODE_DEJA_UTILISE_AUTRE_SENS',
    '20. un code déjà porté par une autre référence de terme dans ce vocabulaire est refusé',
);

/* ------------------------------------------------------------------ libellés */
echo "\n  Libellés localisés\n";

$localeInconnue = $registre->ajouterLibelle('TERM-P3-ACTIF', ['locale' => 'de', 'libelle' => 'Aktiv', ...$gouvernance()]);
$verifier(
    ($localeInconnue['refus'] ?? null) === 'LOCALE_INCONNUE',
    '21. une locale hors liste close est refusée',
);

$libelleVide = $registre->ajouterLibelle('TERM-P3-ACTIF', ['locale' => 'fr', 'libelle' => '', ...$gouvernance()]);
$verifier(
    ($libelleVide['refus'] ?? null) === 'LIBELLE_VIDE',
    '22. un libellé vide est refusé',
);

$registre->ajouterLibelle('TERM-P3-ACTIF', ['locale' => 'fr', 'libelle' => 'Actif', ...$gouvernance()]);
$libellePrincipalDoublon = $registre->ajouterLibelle('TERM-P3-ACTIF', ['locale' => 'fr', 'libelle' => 'Actif (bis)', ...$gouvernance()]);
$verifier(
    ($libellePrincipalDoublon['refus'] ?? null) === 'LIBELLE_PRINCIPAL_DEJA_DEFINI',
    '23. un second libellé principal pour la même locale est refusé',
);

$libelleSecondaireOk = $registre->ajouterLibelle('TERM-P3-ACTIF', ['locale' => 'fr', 'libelle' => 'En service', 'principal' => false, ...$gouvernance()]);
$verifier(
    !isset($libelleSecondaireOk['refus']),
    '24. un libellé non principal coexiste avec le libellé principal de la même locale',
);

/* ---------------------------------------------------------------------- alias */
echo "\n  Alias\n";

$aliasVide = $registre->ajouterAlias('TERM-P3-ACTIF', ['alias' => '', 'type_alias' => 'ANCIEN_CODE', 'source_reference' => 'x', ...$gouvernance()]);
$verifier(
    ($aliasVide['refus'] ?? null) === 'ALIAS_VIDE',
    '25. un alias vide est refusé',
);

$aliasTypeInconnu = $registre->ajouterAlias('TERM-P3-ACTIF', ['alias' => 'ENABLED', 'type_alias' => 'INEXISTANT', 'source_reference' => 'x', ...$gouvernance()]);
$verifier(
    ($aliasTypeInconnu['refus'] ?? null) === 'TYPE_ALIAS_INCONNU',
    '26. un type d’alias hors liste close est refusé',
);

$aliasSansSource = $registre->ajouterAlias('TERM-P3-ACTIF', ['alias' => 'ENABLED', 'type_alias' => 'ANCIEN_CODE', 'source_reference' => '', ...$gouvernance()]);
$verifier(
    ($aliasSansSource['refus'] ?? null) === 'SOURCE_ABSENTE',
    '27. un alias sans source déclarée est refusé',
);

$registre->ajouterAlias('TERM-P3-ACTIF', ['alias' => 'ENABLED', 'type_alias' => 'ANCIEN_CODE', 'source_reference' => 'legacy', ...$gouvernance()]);
$aliasAmbigu = $registre->ajouterAlias('TERM-P3-INACTIF', ['alias' => 'ENABLED', 'type_alias' => 'ANCIEN_CODE', 'source_reference' => 'legacy', ...$gouvernance()]);
$verifier(
    ($aliasAmbigu['refus'] ?? null) === 'ALIAS_AMBIGU',
    '28. le même alias pointant vers un autre terme du même vocabulaire est refusé (ambiguïté interdite)',
);

/* ------------------------------------------------------------------ relations */
echo "\n  Relations entre termes\n";

$relSourceInconnue = $registre->declarerRelation('TERM-P3-INEXISTANT', 'TERM-P3-INACTIF', ['type_relation' => 'ASSOCIE_A', 'preuve' => 'x', ...$gouvernance()]);
$verifier(
    ($relSourceInconnue['refus'] ?? null) === 'TERME_SOURCE_INCONNU',
    '29. une relation depuis un terme source inconnu est refusée',
);

$relCibleInconnue = $registre->declarerRelation('TERM-P3-ACTIF', 'TERM-P3-INEXISTANT', ['type_relation' => 'ASSOCIE_A', 'preuve' => 'x', ...$gouvernance()]);
$verifier(
    ($relCibleInconnue['refus'] ?? null) === 'TERME_CIBLE_INCONNU',
    '30. une relation vers un terme cible inconnu est refusée',
);

$autoRelation = $registre->declarerRelation('TERM-P3-ACTIF', 'TERM-P3-ACTIF', ['type_relation' => 'ASSOCIE_A', 'preuve' => 'x', ...$gouvernance()]);
$verifier(
    ($autoRelation['refus'] ?? null) === 'AUTO_RELATION_REFUSEE',
    '31. un terme ne peut pas être en relation avec lui-même',
);

$relTypeInconnu = $registre->declarerRelation('TERM-P3-ACTIF', 'TERM-P3-INACTIF', ['type_relation' => 'INEXISTANT', 'preuve' => 'x', ...$gouvernance()]);
$verifier(
    ($relTypeInconnu['refus'] ?? null) === 'TYPE_RELATION_INCONNU',
    '32. un type de relation hors liste close est refusé',
);

// `preuve` sert à la fois de porte de gouvernance (contrôlée en premier par
// `controlerGouvernance()`) et de valeur métier `preuve_reference` : la vider
// est donc rattrapée en amont sous COMMANDE_NON_GOUVERNEE, jamais sous
// PREUVE_ABSENTE — cette dernière est un filet mort avec la forme actuelle
// du dossier.
$relSansPreuve = $registre->declarerRelation('TERM-P3-ACTIF', 'TERM-P3-INACTIF', [...$gouvernance(), 'type_relation' => 'ASSOCIE_A', 'preuve' => '']);
$verifier(
    ($relSansPreuve['refus'] ?? null) === 'COMMANDE_NON_GOUVERNEE',
    '33. une relation sans preuve déclarée est refusée (rattrapée par la porte de gouvernance)',
);

$relationOk = $registre->declarerRelation('TERM-P3-ACTIF', 'TERM-P3-INACTIF', ['type_relation' => 'PLUS_LARGE_QUE', 'preuve' => 'garde', ...$gouvernance()]);
$verifier(
    !isset($relationOk['refus']),
    '34. une relation hiérarchique valide est acceptée',
);

$cycle = $registre->declarerRelation('TERM-P3-INACTIF', 'TERM-P3-ACTIF', ['type_relation' => 'PLUS_LARGE_QUE', 'preuve' => 'garde', ...$gouvernance()]);
$verifier(
    ($cycle['refus'] ?? null) === 'CYCLE_HIERARCHIQUE_REFUSE',
    '35. une relation qui créerait un cycle hiérarchique est refusée',
);

/* ------------------------------------------------------------- mappings externes */
echo "\n  Mappings externes\n";

$mappingIncomplet = $registre->declarerMappingExterne('TERM-P3-ACTIF', [
    'systeme_reference' => '', 'vocabulaire_externe' => 'x', 'code_externe' => 'x',
    'sens' => 'ENTRANT', 'statut_mapping' => 'EXACT', 'preuve' => 'x', ...$gouvernance(),
]);
$verifier(
    ($mappingIncomplet['refus'] ?? null) === 'MAPPING_INCOMPLET',
    '36. un mapping externe incomplet est refusé',
);

$sensInconnu = $registre->declarerMappingExterne('TERM-P3-ACTIF', [
    'systeme_reference' => 'sys', 'vocabulaire_externe' => 'ext', 'code_externe' => 'X',
    'sens' => 'INEXISTANT', 'statut_mapping' => 'EXACT', 'preuve' => 'x', ...$gouvernance(),
]);
$verifier(
    ($sensInconnu['refus'] ?? null) === 'SENS_INCONNU',
    '37. un sens de mapping hors liste close est refusé',
);

$statutInconnu = $registre->declarerMappingExterne('TERM-P3-ACTIF', [
    'systeme_reference' => 'sys', 'vocabulaire_externe' => 'ext', 'code_externe' => 'X',
    'sens' => 'ENTRANT', 'statut_mapping' => 'INEXISTANT', 'preuve' => 'x', ...$gouvernance(),
]);
$verifier(
    ($statutInconnu['refus'] ?? null) === 'STATUT_MAPPING_INCONNU',
    '38. un statut de mapping hors liste close est refusé',
);

$mappingOk = $registre->declarerMappingExterne('TERM-P3-ACTIF', [
    'systeme_reference' => 'sys-externe', 'vocabulaire_externe' => 'ext', 'code_externe' => 'X-ACTIF',
    'sens' => 'BIDIRECTIONNEL', 'statut_mapping' => 'EXACT', 'preuve' => 'garde', ...$gouvernance(),
]);
$verifier(
    !isset($mappingOk['refus']),
    '39. un mapping externe structurellement valide est accepté',
);

/* -------------------------------------------------------------------- usages */
echo "\n  Usages déclarés\n";

$usageTypeInconnu = $registre->declarerUsage('TERM-P3-ACTIF', ['usage_type' => 'INEXISTANT', 'capacite_reference' => 'CAP-CORE-999', ...$gouvernance()]);
$verifier(
    ($usageTypeInconnu['refus'] ?? null) === 'TYPE_USAGE_INCONNU',
    '40. un type d’usage hors liste close est refusé',
);

$usageSansConsommateur = $registre->declarerUsage('TERM-P3-ACTIF', ['usage_type' => 'ETAT_PERSISTE', ...$gouvernance()]);
$verifier(
    ($usageSansConsommateur['refus'] ?? null) === 'CONSOMMATEUR_ABSENT',
    '41. un usage sans aucun consommateur désigné est refusé',
);

$usageOk = $registre->declarerUsage('TERM-P3-ACTIF', [
    'usage_type' => 'ETAT_PERSISTE', 'contrat_reference' => 'CTR-P3-DEPENDANT', 'obligatoire' => true, ...$gouvernance(),
]);
$verifier(
    !isset($usageOk['refus']),
    '42. un usage désignant un consommateur explicite est accepté',
);

/* -------------------------------------------------------------- cycle de version */
echo "\n  Cycle de vie de la version (soumission, analyse, activation)\n";

$registre->inscrireVocabulaire([...$dossierBase('VOC-P3-VIDE', 'gamad.p3.vide'), ...$gouvernance()]);
$registre->creerVersion('VOC-P3-VIDE', ['version' => '1.0.0', ...$gouvernance()]);
$soumissionVide = $registre->soumettreVersion('VOC-P3-VIDE', '1.0.0', $gouvernance());
$verifier(
    ($soumissionVide['refus'] ?? null) === 'AUCUN_TERME',
    '43. une version sans terme ne peut pas être soumise',
);

$soumission = $registre->soumettreVersion('VOC-P3-BASE', '1.0.0', $gouvernance());
$verifier(
    ($soumission['etat'] ?? null) === 'EN_VALIDATION' && ($soumission['idempotent'] ?? null) === false,
    '44. la soumission d’une version BROUILLON dotée de termes passe à EN_VALIDATION',
);

$resoumission = $registre->soumettreVersion('VOC-P3-BASE', '1.0.0', $gouvernance());
$verifier(
    ($resoumission['etat'] ?? null) === 'EN_VALIDATION' && ($resoumission['idempotent'] ?? null) === true,
    '45. resoumettre une version déjà EN_VALIDATION est idempotent',
);

$ajoutApresSoumission = $registre->ajouterTerme('VOC-P3-BASE', '1.0.0', [
    'reference' => 'TERM-P3-TROP-TARD', 'code' => 'TROP_TARD', 'type_semantique' => 'ETAT', 'definition' => 'x', ...$gouvernance(),
]);
$verifier(
    ($ajoutApresSoumission['refus'] ?? null) === 'VERSION_IMMUABLE',
    '46. une version EN_VALIDATION n’accepte plus aucune déclaration de terme (soumission immuable)',
);

$activerSansAnalyse = $registre->activerVersion('VOC-P3-BASE', '1.0.0', $gouvernance());
$verifier(
    ($activerSansAnalyse['refus'] ?? null) === 'ANALYSE_MANQUANTE',
    '47. l’activation sans analyse de compatibilité enregistrée est refusée',
);

$analyse1 = $registre->analyserCompatibilite('VOC-P3-BASE', '1.0.0', $gouvernance());
$verifier(
    ($analyse1['resultat'] ?? null) === 'COMPATIBLE',
    '48. l’analyse de la toute première version d’un vocabulaire est toujours COMPATIBLE (rien à rompre)',
);

$activerSansProjection = $registre->activerVersion('VOC-P3-BASE', '1.0.0', $gouvernance());
$verifier(
    ($activerSansProjection['refus'] ?? null) === 'PROJECTION_MANQUANTE',
    '49. l’activation sans projection générée est refusée',
);

$typeProjectionInconnu = $registre->genererProjection('VOC-P3-BASE', '1.0.0', ['type_projection' => 'INEXISTANT', ...$gouvernance()]);
$verifier(
    ($typeProjectionInconnu['refus'] ?? null) === 'TYPE_PROJECTION_INCONNU',
    '50. un type de projection hors liste close est refusé',
);

$registre->genererProjection('VOC-P3-BASE', '1.0.0', ['type_projection' => 'JSON', ...$gouvernance()]);
$activerSansConformite = $registre->activerVersion('VOC-P3-BASE', '1.0.0', $gouvernance());
$verifier(
    ($activerSansConformite['refus'] ?? null) === 'CONFORMITE_MANQUANTE',
    '51. l’activation sans conformité CONFORME enregistrée est refusée',
);

$conformiteNonConforme = $registre->enregistrerConformite('VOC-P3-BASE', '1.0.0', [
    'resultat' => 'NON_CONFORME', 'consommateur_reference' => 'CAP-CORE-999', 'type_consommateur' => 'CAPACITE', ...$gouvernance(),
]);
$activerNonConforme = $registre->activerVersion('VOC-P3-BASE', '1.0.0', $gouvernance());
$verifier(
    !isset($conformiteNonConforme['refus']) && ($activerNonConforme['refus'] ?? null) === 'CONFORMITE_MANQUANTE',
    '52. une conformité NON_CONFORME enregistrée ne suffit pas à activer',
);

$registre->enregistrerConformite('VOC-P3-BASE', '1.0.0', [
    'resultat' => 'CONFORME', 'consommateur_reference' => 'CAP-CORE-999', 'type_consommateur' => 'CAPACITE', ...$gouvernance(),
]);
$activation = $registre->activerVersion('VOC-P3-BASE', '1.0.0', $gouvernance());
$verifier(
    ($activation['etat'] ?? null) === 'ACTIVE' && ($activation['idempotent'] ?? null) === false,
    '53. une version dûment analysée, projetée et conforme s’active',
);

$reactivation = $registre->activerVersion('VOC-P3-BASE', '1.0.0', $gouvernance());
$verifier(
    ($reactivation['etat'] ?? null) === 'ACTIVE' && ($reactivation['idempotent'] ?? null) === true,
    '54. réactiver une version déjà ACTIVE est idempotent',
);

$diag = $registre->diagnostiquerRegistre();
$verifier($diag['coherent'] === true, '55. au plus une version active par vocabulaire (diagnostic cohérent)');

$codeActif = $registre->resoudreCodeActif('VOC-P3-BASE', 'ACTIF');
$verifier(
    $codeActif !== null && $codeActif['reference'] === 'TERM-P3-ACTIF',
    '56. resoudreCodeActif retrouve un code dans la version active sans dupliquer le registre',
);

/* ------------------------------------------------ compatibilité (57-66) */
echo "\n  Analyse de compatibilité — algorithme (57-60)\n";

// `terme.reference` est une clé primaire globale (une ligne pour toute la vie
// du terme, jamais réattribuée) : `evoluerTerme()` insère donc toujours une
// ligne neuve sous une référence neuve pour porter un terme d'une version à
// la suivante, et relie l'ancienne référence à la nouvelle par
// `remplace_par_reference`. `AnalyseurCompatibilite::analyser()` suit cette
// lignée quand la référence de la version précédente n'a pas de jumeau
// direct dans la nouvelle — sans quoi toute évolution légitime se lirait à
// tort comme une suppression pure suivie d'un ajout. Ces quatre premières
// épreuves valident l'algorithme de diff lui-même, unitairement.
$avant = [
    ['reference' => 'TERM-P3-C-A', 'code' => 'A', 'type_semantique' => 'ETAT', 'definition' => 'a'],
    ['reference' => 'TERM-P3-C-B', 'code' => 'B', 'type_semantique' => 'ETAT', 'definition' => 'b'],
];

$analyseCode = \Gamad\RegistreVocabulaire\AnalyseurCompatibilite::analyser($avant, [
    ['reference' => 'TERM-P3-C-A', 'code' => 'A_RENOMME', 'type_semantique' => 'ETAT', 'definition' => 'a'],
    ['reference' => 'TERM-P3-C-B', 'code' => 'B', 'type_semantique' => 'ETAT', 'definition' => 'b'],
]);
$verifier(
    $analyseCode['resultat'] === 'RUPTURE'
        && in_array('code_modifie', array_column($analyseCode['divergences'], 'type'), true),
    '57. [algorithme] changer le code d’une référence de terme stable est une RUPTURE',
);

$analyseSuppr = \Gamad\RegistreVocabulaire\AnalyseurCompatibilite::analyser($avant, [
    ['reference' => 'TERM-P3-C-A', 'code' => 'A', 'type_semantique' => 'ETAT', 'definition' => 'a'],
]);
$verifier(
    $analyseSuppr['resultat'] === 'RUPTURE'
        && in_array('terme_supprime', array_column($analyseSuppr['divergences'], 'type'), true),
    '58. [algorithme] la disparition d’une référence de terme est une RUPTURE',
);

$analyseType = \Gamad\RegistreVocabulaire\AnalyseurCompatibilite::analyser($avant, [
    ['reference' => 'TERM-P3-C-A', 'code' => 'A', 'type_semantique' => 'ACTION', 'definition' => 'a'],
    ['reference' => 'TERM-P3-C-B', 'code' => 'B', 'type_semantique' => 'ETAT', 'definition' => 'b'],
]);
$verifier(
    $analyseType['resultat'] === 'RUPTURE'
        && in_array('type_semantique_modifie', array_column($analyseType['divergences'], 'type'), true),
    '59. [algorithme] changer le type sémantique d’une référence stable est une RUPTURE',
);

$analyseDef = \Gamad\RegistreVocabulaire\AnalyseurCompatibilite::analyser($avant, [
    ['reference' => 'TERM-P3-C-A', 'code' => 'A', 'type_semantique' => 'ETAT', 'definition' => 'nouvelle définition'],
    ['reference' => 'TERM-P3-C-B', 'code' => 'B', 'type_semantique' => 'ETAT', 'definition' => 'b'],
]);
$verifier(
    $analyseDef['resultat'] === 'ADAPTATION_REQUISE'
        && in_array('definition_modifiee', array_column($analyseDef['divergences'], 'type'), true),
    '60. [algorithme] changer la définition d’une référence stable est une ADAPTATION_REQUISE, pas une RUPTURE',
);

/* ---------------------------------------------- évolution de terme — intégration (61-66) */
echo "\n  Évolution de terme entre versions — intégration réelle (61-65)\n";

function nouvelleVersionAvecTermes(RegistreVocabulaire $registre, string $reference, string $version, array $termes, callable $gouvernance): array
{
    $registre->creerVersion($reference, ['version' => $version, ...$gouvernance()]);
    foreach ($termes as $t) {
        $registre->ajouterTerme($reference, $version, [...$t, ...$gouvernance()]);
    }
    $registre->soumettreVersion($reference, $version, $gouvernance());

    return $registre->analyserCompatibilite($reference, $version, $gouvernance());
}

function activerPleinement(RegistreVocabulaire $registre, string $reference, string $version, callable $gouvernance): void
{
    $registre->genererProjection($reference, $version, ['type_projection' => 'JSON', ...$gouvernance()]);
    $registre->enregistrerConformite($reference, $version, [
        'resultat' => 'CONFORME', 'consommateur_reference' => 'CAP-CORE-999', 'type_consommateur' => 'CAPACITE', ...$gouvernance(),
    ]);
    $registre->activerVersion($reference, $version, $gouvernance());
}

$registre->inscrireVocabulaire([...$dossierBase('VOC-P3-COMPAT', 'gamad.p3.compat'), ...$gouvernance()]);
nouvelleVersionAvecTermes($registre, 'VOC-P3-COMPAT', '1.0.0', [
    ['reference' => 'TERM-P3-G-A', 'code' => 'A', 'type_semantique' => 'ETAT', 'definition' => 'a'],
    ['reference' => 'TERM-P3-G-B', 'code' => 'B', 'type_semantique' => 'ETAT', 'definition' => 'b'],
], $gouvernance);
activerPleinement($registre, 'VOC-P3-COMPAT', '1.0.0', $gouvernance);

$evoluerInconnu = $registre->evoluerTerme('TERM-P3-INEXISTANT', '2.0.0', ['reference' => 'X', ...$gouvernance()]);
$verifier(
    ($evoluerInconnu['refus'] ?? null) === 'TERME_INCONNU',
    '61. évoluer un terme inconnu est refusé',
);

$registre->creerVersion('VOC-P3-COMPAT', ['version' => '2.0.0', ...$gouvernance()]);
$registre->evoluerTerme('TERM-P3-G-A', '2.0.0', [
    'reference' => 'TERM-P3-G-A2', 'code' => 'A_RENOMME', ...$gouvernance(),
]);
$evoluerDejaRemplace = $registre->evoluerTerme('TERM-P3-G-A', '2.0.0', ['reference' => 'TERM-P3-G-A3', ...$gouvernance()]);
$verifier(
    ($evoluerDejaRemplace['refus'] ?? null) === 'TERME_DEJA_REMPLACE',
    '62. un terme qui a déjà un successeur ne peut pas évoluer une seconde fois',
);

// TERM-P3-G-B évolue sans rien changer : reconduit tel quel dans la nouvelle
// version, uniquement pour prouver la lignée — pas de champ modifié.
$registre->evoluerTerme('TERM-P3-G-B', '2.0.0', ['reference' => 'TERM-P3-G-B2', ...$gouvernance()]);
$analyseRenomme = $registre->soumettreVersion('VOC-P3-COMPAT', '2.0.0', $gouvernance());
$analyseRenomme = $registre->analyserCompatibilite('VOC-P3-COMPAT', '2.0.0', $gouvernance());
$verifier(
    $analyseRenomme['resultat'] === 'RUPTURE'
        && in_array('code_modifie', array_column($analyseRenomme['divergences'], 'type'), true)
        && !in_array('terme_supprime', array_column($analyseRenomme['divergences'], 'type'), true),
    '63. un code changé via evoluerTerme est reconnu par lignée comme code_modifie, pas comme suppression',
);
$verifier(
    !in_array('terme_ajoute', array_column($analyseRenomme['divergences'], 'type'), true),
    '64. un terme reconduit à l’identique via evoluerTerme n’est signalé ni ajouté ni supprimé',
);
activerPleinement($registre, 'VOC-P3-COMPAT', '2.0.0', $gouvernance);

// évolution avec seulement la définition modifiée : ADAPTATION_REQUISE, pas RUPTURE
$registre->creerVersion('VOC-P3-COMPAT', ['version' => '3.0.0', ...$gouvernance()]);
$registre->evoluerTerme('TERM-P3-G-A2', '3.0.0', ['reference' => 'TERM-P3-G-A4', 'definition' => 'a, précisée', ...$gouvernance()]);
$registre->evoluerTerme('TERM-P3-G-B2', '3.0.0', ['reference' => 'TERM-P3-G-B4', ...$gouvernance()]);
$registre->soumettreVersion('VOC-P3-COMPAT', '3.0.0', $gouvernance());
$analyseDef = $registre->analyserCompatibilite('VOC-P3-COMPAT', '3.0.0', $gouvernance());
$verifier(
    $analyseDef['resultat'] === 'ADAPTATION_REQUISE'
        && in_array('definition_modifiee', array_column($analyseDef['divergences'], 'type'), true),
    '65. une définition changée via evoluerTerme reste ADAPTATION_REQUISE via lignée',
);

/* -------------------------------------------------------------- projections (66-67) */
echo "\n  Projections dérivées (66-67)\n";

$projPhp = $registre->genererProjection('VOC-P3-COMPAT', '3.0.0', ['type_projection' => 'PHP_CONSTANTS', ...$gouvernance()]);
$verifier(
    str_contains((string) $projPhp['contenu'], "'A_RENOMME'") && str_contains((string) $projPhp['contenu'], "'B'"),
    '66. la projection PHP_CONSTANTS porte les codes réels de la version',
);

$projSql = GenerateurProjection::genererContrainteSql('code', [['code' => 'A_RENOMME'], ['code' => 'B']]);
$verifier(
    str_contains($projSql, "CHECK (code IN ('A_RENOMME','B'))"),
    '67. la projection SQL_CHECK produit une contrainte directement exploitable',
);

/* --------------------------------------------------- cycle de vie de la version (68-70) */
echo "\n  Dépréciation et retrait de version (68-70)\n";

$deprecierNonActive = $registre->deprecierVersion('VOC-P3-COMPAT', '3.0.0', $gouvernance());
$verifier(
    ($deprecierNonActive['refus'] ?? null) === 'ETAT_INCOMPATIBLE',
    '68. déprécier une version qui n’est pas ACTIVE est refusé',
);

activerPleinement($registre, 'VOC-P3-COMPAT', '3.0.0', $gouvernance);
$deprecier = $registre->deprecierVersion('VOC-P3-COMPAT', '3.0.0', $gouvernance());
$verifier(($deprecier['etat'] ?? null) === 'DEPRECIEE', '69. la dépréciation d’une version ACTIVE transite vers DEPRECIEE');

$retrait = $registre->retirerVersion('VOC-P3-COMPAT', '3.0.0', $gouvernance());
$reutilisation = $registre->creerVersion('VOC-P3-COMPAT', ['version' => '3.0.0', ...$gouvernance()]);
$verifier(
    ($retrait['etat'] ?? null) === 'RETIREE' && ($reutilisation['refus'] ?? null) === 'VERSION_DEJA_UTILISEE',
    '70. une version retirée n’est jamais réutilisable',
);

/* ------------------------------------------------------- cycle de vie du terme (71-75) */
echo "\n  Dépréciation et retrait de terme (71-75)\n";

$deprecierTermeInconnu = $registre->deprecierTerme('TERM-P3-INEXISTANT', $gouvernance());
$verifier(
    ($deprecierTermeInconnu['refus'] ?? null) === 'TERME_INCONNU',
    '71. déprécier un terme inconnu est refusé',
);

$registre->inscrireVocabulaire([...$dossierBase('VOC-P3-TERME-CYCLE', 'gamad.p3.terme-cycle'), ...$gouvernance()]);
$registre->creerVersion('VOC-P3-TERME-CYCLE', ['version' => '1.0.0', ...$gouvernance()]);
$registre->ajouterTerme('VOC-P3-TERME-CYCLE', '1.0.0', [
    'reference' => 'TERM-P3-BROUILLON', 'code' => 'BROUILLON', 'type_semantique' => 'ETAT', 'definition' => 'x', ...$gouvernance(),
]);
$deprecierEnBrouillon = $registre->deprecierTerme('TERM-P3-BROUILLON', $gouvernance());
$verifier(
    ($deprecierEnBrouillon['refus'] ?? null) === 'ETAT_INCOMPATIBLE',
    '72. déprécier un terme d’une version encore BROUILLON est refusé',
);

$deprecierActif = $registre->deprecierTerme('TERM-P3-INACTIF', $gouvernance());
$verifier(
    ($deprecierActif['date_fin'] ?? null) !== null && ($deprecierActif['idempotent'] ?? null) === false,
    '73. déprécier un terme d’une version ACTIVE fixe sa date de fin',
);
$reDeprecier = $registre->deprecierTerme('TERM-P3-INACTIF', $gouvernance());
$verifier(($reDeprecier['idempotent'] ?? null) === true, '74. redéprécier un terme déjà déprécié est idempotent');

$remplacantInconnu = $registre->deprecierTerme('TERM-P3-ACTIF', ['remplace_par_reference' => 'TERM-P3-FANTOME', ...$gouvernance()]);
$verifier(
    ($remplacantInconnu['refus'] ?? null) === 'REMPLACANT_INCONNU',
    '75. déprécier un terme au profit d’un remplaçant inconnu est refusé',
);

$retirerAvecUsage = $registre->retirerTerme('TERM-P3-ACTIF', $gouvernance());
$verifier(
    ($retirerAvecUsage['refus'] ?? null) === 'USAGE_ACTIF_DEPENDANT',
    '76. retirer un terme dont un usage obligatoire actif dépend encore est refusé',
);

$retirerSansUsage = $registre->retirerTerme('TERM-P3-INACTIF', $gouvernance());
$verifier(
    !isset($retirerSansUsage['refus']) && $retirerSansUsage['date_fin'] !== null,
    '77. retirer un terme sans usage obligatoire actif dépendant est accepté',
);

/* --------------------------------------------------- transactions et continuité (78-79) */
echo "\n  Transactions et continuité (78-79)\n";

$magasin->beginTransaction();
$registre->inscrireVocabulaire([...$dossierBase('VOC-P3-ROLLBACK', 'gamad.p3.rollback'), ...$gouvernance()]);
$magasin->rollBack();
$verifier(
    $registre->resoudreVocabulaire('VOC-P3-ROLLBACK') === null,
    '78. un rollback explicite de la transaction porteuse efface les écritures qu’elle contenait',
);

copy($fichiers['vocabulaire'], $fichiers['vocabulaire_copie']);
$magasinCopie = new \PDO('sqlite:' . $fichiers['vocabulaire_copie']);
$magasinCopie->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$magasinCopie->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
$nAvant = (int) $magasin->query('SELECT count(*) FROM vocabulaire')->fetchColumn();
$nApres = (int) $magasinCopie->query('SELECT count(*) FROM vocabulaire')->fetchColumn();
$registreCopie = new RegistreVocabulaire($index, $identitesReg, $magasinCopie, $ctr01);
$verifier(
    $nAvant === $nApres && $nAvant > 0
        && $registreCopie->resoudreVocabulaire('VOC-P3-BASE')['version_active'] === $registre->resoudreVocabulaire('VOC-P3-BASE')['version_active'],
    '79. une copie physique du magasin reste intégralement lisible (continuité)',
);

/* -------------------------------------------------------------- aucun secret */
$colonnesSuspectes = [];
foreach (SchemaVocabulaire::TABLES as $table) {
    foreach ($magasin->query("PRAGMA table_info({$table})")->fetchAll() as $colonne) {
        if (preg_match('/secret|password|mot_de_passe|jeton|token/i', (string) $colonne['name'])) {
            $colonnesSuspectes[] = "{$table}.{$colonne['name']}";
        }
    }
}
$verifier(
    $colonnesSuspectes === [],
    'le schéma du magasin du vocabulaire ne porte aucune colonne de secret',
    $colonnesSuspectes === [] ? '' : implode(', ', $colonnesSuspectes),
);

/* --------------------------------------------- reconstruction sans perte */
BaselineOperationnelle::standard()->reconstruire($index);
$verifier(
    $registre->resoudreVocabulaire('VOC-P3-BASE') !== null && $registre->resoudreVocabulaire('VOC-P3-BASE')['version_active'] !== null,
    'reconstruire l’index documentaire ne supprime jamais le registre persistant du vocabulaire',
);

/* ------------------------------------------------------------ CONTRE-ÉPREUVE */
echo "\n  Contre-épreuve — la garde doit savoir échouer (80)\n";

$magasin->exec("DELETE FROM vocabulaire WHERE reference = 'VOC-P3-BASE'");
$verifier(
    $registre->resoudreVocabulaire('VOC-P3-BASE') === null,
    '80. un vocabulaire retiré du magasin cesse d’être résolu (contre-épreuve)',
);

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-010 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-010 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
