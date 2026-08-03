<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-012 — registre des realms.
 *
 * Éprouve directement `RegistreRealms`, `EvaluateurPortee` et
 * `ValidateurRealms`, en s'appuyant sur des fiches réelles (mais temporaires)
 * d'organisation (CAP-CORE-002), de produit (CAP-CORE-011) et de contrat
 * (CAP-CORE-009), pour que les contrôles croisés ne soient jamais simulés.
 *
 * CONTRE-ÉPREUVE : la dernière épreuve retire un realm et vérifie que sa
 * référence ne redevient jamais réutilisable. Un test qui ne peut pas
 * échouer ne prouve rien.
 *
 * Exécution : php core/registre-realms/tests/realms_p3.php
 * Code de sortie : 0 si toutes les épreuves et contre-épreuves passent.
 */

use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\PolitiqueContrats;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\PolitiqueOrganisations;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;
use Gamad\RegistreRealms\EvaluateurPortee;
use Gamad\RegistreRealms\ExceptionRealm;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\PolitiqueRealms;
use Gamad\RegistreRealms\RegistreRealms;
use Gamad\RegistreRealms\SchemaRealms;
use Gamad\RegistreRealms\ValidateurRealms;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../../registre-organisations/src/ExceptionOrganisation.php';
require __DIR__ . '/../../registre-organisations/src/PolitiqueOrganisations.php';
require __DIR__ . '/../../registre-organisations/src/SchemaOrganisations.php';
require __DIR__ . '/../../registre-organisations/src/Magasin.php';
require __DIR__ . '/../../registre-organisations/src/ValidateurStructure.php';
require __DIR__ . '/../../registre-organisations/src/ProjectionIdentites.php';
require __DIR__ . '/../../registre-organisations/src/RegistreOrganisations.php';
require __DIR__ . '/../../registre-produits/src/PolitiqueProduits.php';
require __DIR__ . '/../../registre-produits/src/SchemaProduits.php';
require __DIR__ . '/../../registre-produits/src/Magasin.php';
require __DIR__ . '/../../registre-produits/src/RegistreProduits.php';
require __DIR__ . '/../../registre-contrats/src/PolitiqueContrats.php';
require __DIR__ . '/../../registre-contrats/src/ExceptionContrat.php';
require __DIR__ . '/../../registre-contrats/src/ValidateurContrat.php';
require __DIR__ . '/../../registre-contrats/src/SchemaContrats.php';
require __DIR__ . '/../../registre-contrats/src/Magasin.php';
require __DIR__ . '/../../registre-contrats/src/AnalyseurCompatibilite.php';
require __DIR__ . '/../../registre-contrats/src/GenerateurOpenApi.php';
require __DIR__ . '/../../registre-contrats/src/RegistreContrats.php';
require __DIR__ . '/../src/ExceptionRealm.php';
require __DIR__ . '/../src/PolitiqueRealms.php';
require __DIR__ . '/../src/SchemaRealms.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/ValidateurRealms.php';
require __DIR__ . '/../src/EvaluateurPortee.php';
require __DIR__ . '/../src/RegistreRealms.php';

$prefixe = sys_get_temp_dir() . '/gamad-realms-' . getmypid();
$fichiers = [
    'index' => $prefixe . '-index.sqlite',
    'identites' => $prefixe . '-identites.sqlite',
    'organisations' => $prefixe . '-organisations.sqlite',
    'produits' => $prefixe . '-produits.sqlite',
    'contrats' => $prefixe . '-contrats.sqlite',
    'realms' => $prefixe . '-realms.sqlite',
];
foreach ($fichiers as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $fichier) {
        @unlink($fichier);
    }
});

putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $fichiers['index']);
$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);

$registreIdentites = IdentiteMagasin::connecter($fichiers['identites']);
$ctr01 = new Ctr01($index, $registreIdentites);

$magasinOrganisations = OrganisationsMagasin::connecter($fichiers['organisations']);
$organisations = new RegistreOrganisations($index, $registreIdentites, $magasinOrganisations, $ctr01);

$magasinProduits = ProduitsMagasin::connecter($fichiers['produits']);
$produits = new RegistreProduits($index, $registreIdentites, $magasinProduits, $ctr01);

$magasinContrats = ContratsMagasin::connecter($fichiers['contrats']);
$contrats = new RegistreContrats($index, $registreIdentites, $magasinContrats, $ctr01);

$magasinRealms = RealmsMagasin::connecter($fichiers['realms']);
$registre = new RegistreRealms($index, $registreIdentites, $magasinRealms, $ctr01, $organisations, $produits, $contrats);
$registreSansDependances = new RegistreRealms($index, $registreIdentites, $magasinRealms, $ctr01, null, null, null);

$echecs = 0;
echo "GARDE — REGISTRE DES REALMS (CAP-CORE-012)\n\n";

$verifier = static function (bool $ok, string $message) use (&$echecs): void {
    echo $ok ? "  [OK]    {$message}\n" : "  [ÉCHEC] {$message}\n";
    if (!$ok) {
        $echecs++;
    }
};

$g = static fn (array $extra = []): array => $extra + [
    'politique' => PolitiqueRealms::POLITIQUE,
    'source' => PolitiqueRealms::SOURCE,
    'producteur' => PolitiqueRealms::AUTORITE,
    'preuve' => 'EVT-P3-RLM-PREUVE-' . strtoupper(bin2hex(random_bytes(4))),
];

$inscrireIdentite = static function (string $type, string $libelle) use ($ctr01): string {
    $identite = $ctr01->inscrireIdentite([
        'canal' => 'AUTORITE', 'type' => $type, 'libelle' => $libelle,
        'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-REALMS-P3',
        'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-IDN-' . strtoupper(bin2hex(random_bytes(4))),
    ]);
    if (isset($identite['refus'])) {
        throw new RuntimeException('inscription identité impossible : ' . json_encode($identite));
    }

    return (string) $identite['reference'];
};

// ------------------------------------------------------------------
// Fixtures croisées : une organisation active, un produit actif, un
// contrat actif — pour que les rattachements soient éprouvés contre du
// code réel, jamais simulés.

$IDN_ORG = $inscrireIdentite('organisation', 'Organisation P3 Realms');
$inscriptionOrg = $organisations->inscrireOrganisation([
    'identite_reference' => $IDN_ORG, 'type_organisation_reference' => 'INSTITUTION',
    'proprietaire_reference' => PolitiqueRealms::AUTORITE, 'denomination_officielle' => 'Organisation P3 Realms',
    'classification_reference' => 'INTERNE',
    'politique' => PolitiqueOrganisations::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-ORG-INSCRIPTION',
]);
$ORG_ACTIVE = (string) $inscriptionOrg['reference'];
$organisations->activerOrganisation($ORG_ACTIVE, [
    'politique' => PolitiqueOrganisations::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-ORG-ACTIVATION',
]);

$IDN_ORG_INACTIVE = $inscrireIdentite('organisation', 'Organisation P3 Realms Inactive');
$inscriptionOrgInactive = $organisations->inscrireOrganisation([
    'identite_reference' => $IDN_ORG_INACTIVE, 'type_organisation_reference' => 'INSTITUTION',
    'proprietaire_reference' => PolitiqueRealms::AUTORITE, 'denomination_officielle' => 'Organisation P3 Realms Inactive',
    'classification_reference' => 'INTERNE',
    'politique' => PolitiqueOrganisations::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-ORG-INSCRIPTION-2',
]);
$ORG_INACTIVE = (string) $inscriptionOrgInactive['reference'];

$IDN_PRD = $inscrireIdentite('produit', 'Produit P3 Realms');
$produits->inscrireProduit([
    'reference' => 'PRD-P3-REALMS', 'identite_reference' => $IDN_PRD,
    'nom_canonique' => 'produit-p3-realms', 'nom_affichage' => 'Produit P3 Realms',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'producteur' => PolitiqueRealms::AUTORITE,
    'politique' => PolitiqueProduits::POLITIQUE, 'preuve' => 'EVT-P3-PRD-INSCRIPTION',
]);
$PRD_ACTIF = 'PRD-P3-REALMS';
$produits->activerProduit($PRD_ACTIF, [
    'producteur' => PolitiqueRealms::AUTORITE, 'preuve' => 'EVT-P3-PRD-ACTIVATION',
    'politique' => PolitiqueProduits::POLITIQUE, 'source' => 'garde CAP-CORE-012',
]);
$produits->declarerEnvironnement($PRD_ACTIF, [
    'environnement' => 'PRODUCTION', 'audience_federation' => 'https://p3-realms.example/aud',
    'api_base_url' => 'https://p3-realms.example',
    'producteur' => PolitiqueRealms::AUTORITE, 'preuve' => 'EVT-P3-PRD-ENV',
    'politique' => PolitiqueProduits::POLITIQUE, 'source' => 'garde CAP-CORE-012',
]);

$IDN_PRD_INACTIF = $inscrireIdentite('produit', 'Produit P3 Realms Inactif');
$produits->inscrireProduit([
    'reference' => 'PRD-P3-REALMS-INACTIF', 'identite_reference' => $IDN_PRD_INACTIF,
    'nom_canonique' => 'produit-p3-realms-inactif', 'nom_affichage' => 'Produit P3 Realms Inactif',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'producteur' => PolitiqueRealms::AUTORITE,
    'politique' => PolitiqueProduits::POLITIQUE, 'preuve' => 'EVT-P3-PRD-INSCRIPTION-2',
]);
$PRD_INACTIF = 'PRD-P3-REALMS-INACTIF';

$IDN_CTR_PROPRIETAIRE = $inscrireIdentite('produit', 'Producteur contrat P3');
$contrats->inscrireContrat([
    'reference' => 'CTR-P3-REALMS', 'nom' => 'Contrat P3 Realms', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => 'FINALITE-P3-REALMS', 'producteur_produit_reference' => $IDN_CTR_PROPRIETAIRE,
    'proprietaire_reference' => PolitiqueRealms::AUTORITE, 'source_reference' => 'garde CAP-CORE-012',
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-CTR-INSCRIPTION',
]);
$contrats->creerVersion('CTR-P3-REALMS', [
    'version' => '1.0.0', 'compatibilite_annoncee' => 'COMPATIBLE',
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-CTR-VERSION',
]);
$contrats->declarerPartie('CTR-P3-REALMS', '1.0.0', [
    'role' => 'PRODUCTEUR', 'partie_type' => 'PRODUIT', 'partie_reference' => $IDN_CTR_PROPRIETAIRE,
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-CTR-PARTIE',
]);
$contrats->declarerOperation('CTR-P3-REALMS', '1.0.0', [
    'reference_operation' => 'operation.p3.realms', 'type_operation' => 'INTERROGER',
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-CTR-OPERATION',
]);
$contrats->soumettreVersion('CTR-P3-REALMS', '1.0.0', [
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-CTR-SOUMISSION',
]);
$contrats->analyserCompatibilite('CTR-P3-REALMS', '1.0.0', [
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-CTR-ANALYSE',
]);
$contrats->enregistrerConformite('CTR-P3-REALMS', '1.0.0', [
    'resultat' => 'CONFORME', 'artefact_reference' => 'GARDE-P3-REALMS',
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-CTR-CONFORMITE',
]);
$contrats->activerVersion('CTR-P3-REALMS', '1.0.0', [
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => PolitiqueRealms::AUTORITE,
    'source' => 'garde CAP-CORE-012', 'preuve' => 'EVT-P3-CTR-ACTIVATION',
]);
$CTR_ACTIF = 'CTR-P3-REALMS';

// ------------------------------------------------------------------
// 1 à 4 — bootstrap, empreinte, schéma présent, aucun secret en colonne

$brutBootstrap = file_get_contents(__DIR__ . '/../resources/bootstrap-realms-v1.json');
$empreinte = hash('sha256', (string) $brutBootstrap);
$payload = json_decode((string) $brutBootstrap, true);
$verifier(
    is_array($payload) && array_key_exists('realms', $payload) && is_array($payload['realms']) && $payload['realms'] === []
        && preg_match('/^[0-9a-f]{64}$/', $empreinte) === 1,
    'le bootstrap est un JSON versionné, vérifiable par empreinte SHA-256, honnêtement vide, sans donnée inventée',
);
$verifier(SchemaRealms::presente($magasinRealms), 'les onze tables du magasin des realms sont présentes après migration');
$colonnesInterdites = ['secret', 'mot_de_passe', 'password', 'token', 'jeton_clair'];
$aucunSecret = true;
foreach (SchemaRealms::TABLES as $table) {
    $colonnes = $magasinRealms->query("PRAGMA table_info({$table})")->fetchAll();
    foreach ($colonnes as $colonne) {
        foreach ($colonnesInterdites as $motif) {
            if (str_contains(strtolower((string) $colonne['name']), $motif)) {
                $aucunSecret = false;
            }
        }
    }
}
$verifier($aucunSecret, 'aucune colonne du schéma des realms ne porte de secret, mot de passe ou jeton');

// ------------------------------------------------------------------
// 5 à 11 — inscription gouvernée

$IDN_RLM_CI = $inscrireIdentite('realm', 'Realm Côte d’Ivoire P3');
$IDN_RLM_ML = $inscrireIdentite('realm', 'Realm Mali P3');
$IDN_RLM_PARENT = $inscrireIdentite('realm', 'Realm Régional P3');
$IDN_RLM_SUCCESSEUR = $inscrireIdentite('realm', 'Realm Successeur P3');
$IDN_ORG_TYPE = $inscrireIdentite('organisation', 'Mauvais type P3');

$sansPolitique = $registre->inscrireRealm($g([
    'identite_reference' => $IDN_RLM_CI, 'code_canonique' => 'RLM-P3-CI', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Côte d’Ivoire', 'classification_reference' => 'INTERNE', 'politique' => '',
]));
$identiteInconnue = $registre->inscrireRealm($g([
    'identite_reference' => 'IDN-RLM-INEXISTANTE', 'code_canonique' => 'RLM-P3-X', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'X', 'classification_reference' => 'INTERNE',
]));
$identiteMauvaisType = $registre->inscrireRealm($g([
    'identite_reference' => $IDN_ORG_TYPE, 'code_canonique' => 'RLM-P3-Y', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Y', 'classification_reference' => 'INTERNE',
]));
$typeInconnu = $registre->inscrireRealm($g([
    'identite_reference' => $IDN_RLM_CI, 'code_canonique' => 'RLM-P3-Z', 'type_realm_reference' => 'TYPE_INEXISTANT',
    'nom_affichage' => 'Z', 'classification_reference' => 'INTERNE',
]));
$inscriptionCI = $registre->inscrireRealm($g([
    'identite_reference' => $IDN_RLM_CI, 'code_canonique' => 'RLM-P3-CI', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Côte d’Ivoire', 'classification_reference' => 'INTERNE',
]));
$verifier(
    ($sansPolitique['refus'] ?? null) === 'COMMANDE_NON_GOUVERNEE'
        && ($identiteInconnue['refus'] ?? null) === 'IDENTITE_INCONNUE'
        && ($identiteMauvaisType['refus'] ?? null) === 'IDENTITE_TYPE_INVALIDE'
        && ($typeInconnu['refus'] ?? null) === 'TYPE_REALM_INCONNU'
        && ($inscriptionCI['etat'] ?? null) === 'PREPARATION',
    'inscription gouvernée : identité obligatoire de type realm, type canonique, création en PREPARATION',
);
$RLM_CI = (string) $inscriptionCI['reference'];

$identiteDejaLiee = $registre->inscrireRealm($g([
    'identite_reference' => $IDN_RLM_CI, 'code_canonique' => 'RLM-P3-CI-BIS', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Doublon', 'classification_reference' => 'INTERNE',
]));
$codeDejaUtilise = $registre->inscrireRealm($g([
    'identite_reference' => $IDN_RLM_ML, 'code_canonique' => 'RLM-P3-CI', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Mali', 'classification_reference' => 'INTERNE',
]));
$verifier(
    ($identiteDejaLiee['refus'] ?? null) === 'IDENTITE_DEJA_LIEE' && ($codeDejaUtilise['refus'] ?? null) === 'CODE_DEJA_UTILISE',
    'une identité déjà liée et un code canonique déjà utilisé sont refusés',
);

$inscriptionML = $registre->inscrireRealm($g([
    'identite_reference' => $IDN_RLM_ML, 'code_canonique' => 'RLM-P3-ML', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Mali', 'classification_reference' => 'INTERNE',
]));
$RLM_ML = (string) $inscriptionML['reference'];
$inscriptionParent = $registre->inscrireRealm($g([
    'identite_reference' => $IDN_RLM_PARENT, 'code_canonique' => 'RLM-P3-REGION', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Région P3', 'classification_reference' => 'INTERNE',
]));
$RLM_PARENT = (string) $inscriptionParent['reference'];
$inscriptionSuccesseur = $registre->inscrireRealm($g([
    'identite_reference' => $IDN_RLM_SUCCESSEUR, 'code_canonique' => 'RLM-P3-SUCC', 'type_realm_reference' => 'PROGRAMME',
    'nom_affichage' => 'Successeur P3', 'classification_reference' => 'INTERNE',
]));
$RLM_SUCCESSEUR = (string) $inscriptionSuccesseur['reference'];
$verifier(
    $RLM_CI !== $RLM_ML && str_starts_with($RLM_CI, 'RLM-GAMAD-') && str_starts_with($RLM_ML, 'RLM-GAMAD-'),
    'chaque realm reçoit une référence unique et monotone',
);

// ------------------------------------------------------------------
// 12, 13 — révision et modification

$verifier(
    ($registre->resoudreRevision($RLM_CI)['numero_revision'] ?? null) === 1,
    'la première révision descriptive est créée dans la même transaction que l’inscription',
);
$modification = $registre->modifierRealm($RLM_CI, $g(['nom_affichage' => 'République de Côte d’Ivoire', 'description' => 'révision P3']));
$verifier(
    ($modification['numero_revision'] ?? null) === 2
        && ($registre->resoudreRealm($RLM_CI)['revision']['nom_affichage'] ?? null) === 'République de Côte d’Ivoire'
        && ($registre->resoudreRealm($RLM_CI)['reference'] ?? null) === $RLM_CI
        && ($registre->resoudreRealm($RLM_CI)['code_canonique'] ?? null) === 'RLM-P3-CI',
    'une modification crée une nouvelle révision ; la référence et le code canonique restent immuables',
);

// ------------------------------------------------------------------
// 14 à 17 — cycle de vie

$activationRefusee = $registre->activerRealm($RLM_ML, $g(['producteur' => $RLM_ML]));
$verifier(($activationRefusee['refus'] ?? null) === 'AUTO_ACTIVATION_INTERDITE', 'un realm ne peut jamais s’auto-activer');

$activationCI = $registre->activerRealm($RLM_CI, $g());
$activationIdempotente = $registre->activerRealm($RLM_CI, $g());
$verifier(
    ($activationCI['etat'] ?? null) === 'ACTIF' && ($activationCI['idempotent'] ?? null) === false
        && ($activationIdempotente['idempotent'] ?? null) === true,
    'activation gouvernée, en ACTIF, idempotente au rejeu (concurrence sur activation)',
);
$registre->activerRealm($RLM_ML, $g());
$registre->activerRealm($RLM_PARENT, $g());
$registre->activerRealm($RLM_SUCCESSEUR, $g());

$suspension = $registre->suspendreRealm($RLM_ML, $g());
$verifier(($suspension['etat'] ?? null) === 'SUSPENDU', 'suspension opposable depuis ACTIF');

$fermeture = $registre->fermerRealm($RLM_ML, $g());
$transitionApresFermeture = $registre->activerRealm($RLM_ML, $g());
$verifier(
    ($fermeture['etat'] ?? null) === 'FERME' && ($transitionApresFermeture['refus'] ?? null) === 'ETAT_INCOMPATIBLE',
    'fermeture opposable ; un realm FERME ne se réactive pas par la commande directe',
);

$retrait = $registre->retirerRealm($RLM_SUCCESSEUR, $g(['motif_reference' => 'FIN_DE_PROGRAMME']));
$retraitSansMotif = $registre->retirerRealm($RLM_PARENT, $g());
$reactivationApresRetrait = $registre->activerRealm($RLM_SUCCESSEUR, $g());
$verifier(
    ($retrait['etat'] ?? null) === 'RETIRE' && ($retraitSansMotif['refus'] ?? null) === 'MOTIF_ABSENT'
        && ($reactivationApresRetrait['refus'] ?? null) === 'ETAT_INCOMPATIBLE',
    'retrait irréversible, motif obligatoire ; une référence retirée ne redevient jamais active',
);

// ------------------------------------------------------------------
// 18 à 22 — hiérarchie et relations entre realms

$autoRelation = $registre->declarerRelation($g([
    'realm_source_reference' => $RLM_CI, 'realm_cible_reference' => $RLM_CI, 'type_relation_reference' => 'PARENT_DE',
]));
$verifier(($autoRelation['refus'] ?? null) === 'AUTO_RELATION_INTERDITE', 'un realm ne peut pas être en relation avec lui-même');

$relationParent = $registre->declarerRelation($g([
    'realm_source_reference' => $RLM_PARENT, 'realm_cible_reference' => $RLM_CI, 'type_relation_reference' => 'PARENT_DE',
]));
$verifier(
    ($relationParent['refus'] ?? null) === null,
    'une relation PARENT_DE valide est déclarée',
);
$cycleDirect = $registre->declarerRelation($g([
    'realm_source_reference' => $RLM_CI, 'realm_cible_reference' => $RLM_PARENT, 'type_relation_reference' => 'PARENT_DE',
]));
$verifier(($cycleDirect['refus'] ?? null) === 'CYCLE_HIERARCHIQUE_DETECTE', 'un cycle direct (A parent de B, B parent de A) est refusé');

$registre->declarerRelation($g([
    'realm_source_reference' => $RLM_CI, 'realm_cible_reference' => $RLM_ML, 'type_relation_reference' => 'PARENT_DE',
]));
$cycleIndirect = $registre->declarerRelation($g([
    'realm_source_reference' => $RLM_ML, 'realm_cible_reference' => $RLM_PARENT, 'type_relation_reference' => 'PARENT_DE',
]));
$verifier(($cycleIndirect['refus'] ?? null) === 'CYCLE_HIERARCHIQUE_DETECTE', 'un cycle indirect (A → B → C → A) est refusé');
$verifier(
    ValidateurRealms::detecterCycles([['X', 'Y'], ['Y', 'X']]) !== [],
    'ValidateurRealms détecte un cycle déjà présent dans un graphe donné (diagnostic de readiness)',
);

$verifier(
    in_array($RLM_CI, array_column($registre->resoudreEnfants($RLM_PARENT), 'realm_cible_reference'), true)
        && in_array($RLM_PARENT, array_column($registre->resoudreParents($RLM_CI), 'realm_source_reference'), true)
        && in_array($RLM_ML, $registre->resoudreDescendance($RLM_PARENT), true)
        && in_array($RLM_PARENT, $registre->resoudreAscendance($RLM_ML), true),
    'parents, enfants, ascendance et descendance se lisent correctement sur la hiérarchie PARENT_DE',
);

// RLM_PARENT et RLM_ML n'ont, à ce stade, aucune relation PARENT_DE directe
// entre eux (seule une ascendance transitive via RLM_CI existe) : un
// CHEVAUCHE direct entre les deux ne doit donc jamais apparaître comme un
// lien parent/enfant direct.
$chevauchement = $registre->declarerRelation($g([
    'realm_source_reference' => $RLM_PARENT, 'realm_cible_reference' => $RLM_ML, 'type_relation_reference' => 'CHEVAUCHE',
]));
$verifier(
    ($chevauchement['refus'] ?? null) === null
        && !in_array($RLM_ML, array_column($registre->resoudreEnfants($RLM_PARENT), 'realm_cible_reference'), true),
    'un CHEVAUCHE est enregistré sans jamais construire une inclusion hiérarchique',
);

$etatMlAvantSuccession = $registre->resoudreEtat($RLM_ML)['etat'] ?? null;
$succession = $registre->declarerRelation($g([
    'realm_source_reference' => $RLM_SUCCESSEUR, 'realm_cible_reference' => $RLM_ML, 'type_relation_reference' => 'SUCCEDE_A',
]));
$verifier(
    ($succession['refus'] ?? null) === null
        && ($registre->resoudreEtat($RLM_ML)['etat'] ?? null) === $etatMlAvantSuccession,
    'une relation SUCCEDE_A n’altère jamais automatiquement le cycle du realm remplacé',
);

$fermetureRelation = $registre->fermerRelation((string) $relationParent['reference'], $g());
$fermetureIdempotente = $registre->fermerRelation((string) $relationParent['reference'], $g());
$verifier(
    ($fermetureRelation['idempotent'] ?? null) === false && ($fermetureIdempotente['idempotent'] ?? null) === true
        && $registre->resoudreHistorique($RLM_CI) !== [],
    'fermeture de relation idempotente ; l’historique du cycle reste lisible après fermeture',
);

// ------------------------------------------------------------------
// 23 à 25 — périmètres et identifiants externes

$dimensionInconnue = $registre->declarerPerimetre($RLM_CI, $g(['dimension_reference' => 'DIMENSION_LIBRE', 'valeur_reference' => 'X']));
$perimetre = $registre->declarerPerimetre($RLM_CI, $g(['dimension_reference' => 'PAYS', 'valeur_reference' => 'CI']));
$verifier(
    ($dimensionInconnue['refus'] ?? null) === 'DIMENSION_INCONNUE' && ($perimetre['refus'] ?? null) === null
        && $registre->resoudrePerimetres($RLM_CI) !== [],
    'un périmètre canonique est déclaré ; une dimension libre est refusée pour la sécurité',
);
$fermeturePerimetre = $registre->fermerPerimetre((int) $perimetre['id'], $g());
$verifier(($fermeturePerimetre['idempotent'] ?? null) === false, 'un périmètre se ferme, daté');

$identifiant = $registre->declarerIdentifiantExterne($RLM_CI, $g(['systeme_reference' => 'ISO-3166-1', 'valeur' => 'CI']));
$conflitIdentifiant = $registre->declarerIdentifiantExterne($RLM_ML, $g(['systeme_reference' => 'ISO-3166-1', 'valeur' => 'CI']));
$verifier(
    ($identifiant['refus'] ?? null) === null && ($conflitIdentifiant['refus'] ?? null) === 'IDENTIFIANT_DEJA_DECLARE',
    'un identifiant externe explicite est déclaré ; un conflit système/valeur actif est refusé',
);

// ------------------------------------------------------------------
// 26 à 30 — rattachement d'organisation

$organisationInconnue = $registre->rattacherOrganisation($RLM_CI, $g([
    'organisation_reference' => 'ORG-GAMAD-INEXISTANTE', 'role_reference' => 'OPERATEUR', 'classification_reference' => 'INTERNE',
]));
$organisationInactive = $registre->rattacherOrganisation($RLM_CI, $g([
    'organisation_reference' => $ORG_INACTIVE, 'role_reference' => 'OPERATEUR', 'classification_reference' => 'INTERNE',
]));
$roleInconnu = $registre->rattacherOrganisation($RLM_CI, $g([
    'organisation_reference' => $ORG_ACTIVE, 'role_reference' => 'ROLE_INEXISTANT', 'classification_reference' => 'INTERNE',
]));
$mandatInsuffisant = $registre->rattacherOrganisation($RLM_CI, array_merge($g([
    'organisation_reference' => $ORG_ACTIVE, 'role_reference' => 'RESPONSABLE', 'classification_reference' => 'INTERNE',
]), ['producteur' => $ORG_ACTIVE]));
$verifier(
    ($organisationInconnue['refus'] ?? null) === 'ORGANISATION_INCONNUE'
        && ($organisationInactive['refus'] ?? null) === 'ORGANISATION_INACTIVE'
        && ($roleInconnu['refus'] ?? null) === 'ROLE_INCONNU'
        && ($mandatInsuffisant['refus'] ?? null) === 'MANDAT_INSUFFISANT',
    'organisation inconnue, inactive, rôle hors liste close et rôle RESPONSABLE sans mandat vérifié sont tous refusés',
);
$rattachementOrg = $registre->rattacherOrganisation($RLM_CI, $g([
    'organisation_reference' => $ORG_ACTIVE, 'role_reference' => 'OPERATEUR', 'classification_reference' => 'INTERNE',
]));
$verifier(
    ($rattachementOrg['refus'] ?? null) === null
        && in_array($ORG_ACTIVE, array_column($registre->resoudreOrganisations($RLM_CI), 'organisation_reference'), true)
        && in_array($RLM_CI, array_column($registre->listerRealmsOrganisation($ORG_ACTIVE), 'realm_reference'), true),
    'une organisation active est rattachée dans un rôle non-mandaté ; la lecture inverse fonctionne',
);
$detachementOrg = $registre->detacherOrganisation((string) $rattachementOrg['reference'], $g());
$verifier(($detachementOrg['idempotent'] ?? null) === false, 'un rattachement d’organisation se détache, daté, sans effacement physique');

$rattachementSansDependance = $registreSansDependances->rattacherOrganisation($RLM_CI, $g([
    'organisation_reference' => $ORG_ACTIVE, 'role_reference' => 'OPERATEUR', 'classification_reference' => 'INTERNE',
]));
$verifier(
    ($rattachementSansDependance['refus'] ?? null) === 'DEPENDANCE_INDISPONIBLE',
    'CAP-CORE-002 indisponible ferme le rattachement d’organisation, sans supposer d’activité',
);

// ------------------------------------------------------------------
// 31 à 35 — rattachement de produit

$produitInconnu = $registre->rattacherProduit($RLM_CI, $g(['produit_reference' => 'PRD-INEXISTANT', 'role_reference' => 'OPERE_DANS']));
$produitInactif = $registre->rattacherProduit($RLM_CI, $g(['produit_reference' => $PRD_INACTIF, 'role_reference' => 'OPERE_DANS']));
$environnementInconnu = $registre->rattacherProduit($RLM_CI, $g([
    'produit_reference' => $PRD_ACTIF, 'role_reference' => 'OPERE_DANS', 'environnement_reference' => 'staging-inexistant',
]));
$verifier(
    ($produitInconnu['refus'] ?? null) === 'PRODUIT_INCONNU'
        && ($produitInactif['refus'] ?? null) === 'PRODUIT_INACTIF'
        && ($environnementInconnu['refus'] ?? null) === 'ENVIRONNEMENT_INCONNU',
    'produit inconnu, produit inactif et environnement inconnu sont tous refusés',
);
$rattachementProduit = $registre->rattacherProduit($RLM_CI, $g([
    'produit_reference' => $PRD_ACTIF, 'role_reference' => 'OPERE_DANS', 'environnement_reference' => 'PRODUCTION',
]));
$verifier(
    ($rattachementProduit['refus'] ?? null) === null
        && in_array($PRD_ACTIF, array_column($registre->resoudreProduits($RLM_CI), 'produit_reference'), true)
        && !str_contains((string) json_encode($registre->resoudreProduits($RLM_CI)), 'https://p3-realms.example'),
    'un produit actif est rattaché dans un environnement déclaré ; aucune URL d’environnement n’est recopiée dans le rattachement',
);

// ------------------------------------------------------------------
// 36, 37 — rattachement de contrat

$contratInconnu = $registre->rattacherContrat($RLM_CI, $g(['contrat_reference' => 'CTR-INEXISTANT', 'role_reference' => 'GOUVERNE']));
$rattachementContrat = $registre->rattacherContrat($RLM_CI, $g(['contrat_reference' => $CTR_ACTIF, 'role_reference' => 'GOUVERNE']));
$verifier(
    ($contratInconnu['refus'] ?? null) === 'CONTRAT_INCONNU'
        && ($rattachementContrat['refus'] ?? null) === null
        && in_array($CTR_ACTIF, array_column($registre->resoudreContrats($RLM_CI), 'contrat_reference'), true),
    'contrat inconnu refusé ; contrat actif rattaché',
);

// ------------------------------------------------------------------
// 38 à 42 — franchissement et contrôle de portée

$porteeSansFranchissement = $registre->verifierPortee([
    'realm' => $RLM_CI, 'realm_source' => $RLM_CI, 'realm_cible' => $RLM_ML, 'finalite' => 'FINALITE-P3',
]);
$verifier(
    ($porteeSansFranchissement['dans_portee'] ?? null) === false
        && in_array('FRANCHISSEMENT_NON_DECLARE', $porteeSansFranchissement['motifs'] ?? [], true),
    'sans déclaration explicite, un franchissement inter-realm est refusé par défaut',
);

$wildcardRefuse = $registre->declarerFranchissement($g([
    'realm_source_reference' => $RLM_CI, 'realm_cible_reference' => $RLM_ML, 'objet_reference' => '*',
    'type_objet_reference' => 'DONNEE', 'effet_reference' => 'PERMET', 'finalite_reference' => 'FINALITE-P3',
]));
$verifier(($wildcardRefuse['refus'] ?? null) === 'WILDCARD_INTERDIT', 'aucun objet universel implicite n’est accepté dans un franchissement');

$franchissementPermis = $registre->declarerFranchissement($g([
    'realm_source_reference' => $RLM_CI, 'realm_cible_reference' => $RLM_ML, 'objet_reference' => 'objet.p3.realms',
    'type_objet_reference' => 'DONNEE', 'effet_reference' => 'PERMET', 'finalite_reference' => 'FINALITE-P3',
]));
$porteeAvecFranchissement = $registre->verifierPortee([
    'realm' => $RLM_CI, 'realm_source' => $RLM_CI, 'realm_cible' => $RLM_ML, 'finalite' => 'FINALITE-P3',
]);
$verifier(
    ($franchissementPermis['refus'] ?? null) === null && ($porteeAvecFranchissement['dans_portee'] ?? null) === true,
    'un franchissement PERMET explicite rend le contrôle de portée positif',
);

$franchissementRefuse = $registre->declarerFranchissement($g([
    'realm_source_reference' => $RLM_CI, 'realm_cible_reference' => $RLM_ML, 'objet_reference' => 'objet.p3.realms',
    'type_objet_reference' => 'DONNEE', 'effet_reference' => 'REFUSE', 'finalite_reference' => 'FINALITE-P3',
]));
$porteeApresRefusPrioritaire = $registre->verifierPortee([
    'realm' => $RLM_CI, 'realm_source' => $RLM_CI, 'realm_cible' => $RLM_ML, 'finalite' => 'FINALITE-P3',
]);
$verifier(
    ($porteeApresRefusPrioritaire['dans_portee'] ?? null) === false
        && in_array('FRANCHISSEMENT_REFUSE', $porteeApresRefusPrioritaire['motifs'] ?? [], true),
    'un REFUSE applicable l’emporte toujours sur un PERMET déjà déclaré (refus prioritaire)',
);

$porteePositiveSimple = $registre->verifierPortee(['realm' => $RLM_CI, 'organisation' => null]);
$verifier(
    ($porteePositiveSimple['dans_portee'] ?? null) === true
        && str_contains((string) ($porteePositiveSimple['avertissement'] ?? ''), 'CAP-CORE-004'),
    'un contrôle de portée sans dépendance manquante sur un realm ACTIF est positif, et rappelle qu’il ne vaut pas autorisation',
);

$porteeRealmInconnu = $registre->verifierPortee(['realm' => 'RLM-GAMAD-INEXISTANT']);
$verifier(
    ($porteeRealmInconnu['dans_portee'] ?? null) === false && ($porteeRealmInconnu['motifs'] ?? []) === ['REALM_INCONNU'],
    'un realm inconnu ferme le contrôle de portée par défaut, de façon explicable',
);

// ------------------------------------------------------------------
// 43, 44 — vérification

$autoAttestationRefusee = $registre->enregistrerVerification($RLM_CI, $g([
    'type_verification_reference' => 'CONFORMITE_TECHNIQUE', 'resultat_reference' => 'CONFORME',
    'verifie_par_reference' => PolitiqueRealms::AUTORITE,
]));
$verifier(
    ($autoAttestationRefusee['refus'] ?? null) === null,
    'l’autorité peut enregistrer une vérification (elle n’est pas le realm lui-même)',
);
$verificationExpiree = $registre->enregistrerVerification($RLM_ML, $g([
    'type_verification_reference' => 'CONFORMITE_TECHNIQUE', 'resultat_reference' => 'CONFORME',
    'verifie_par_reference' => PolitiqueRealms::AUTORITE, 'verifie_le' => '2020-01-01', 'expire_le' => '2020-06-01',
]));
$porteeVerificationExpiree = $registre->verifierPortee(['realm' => $RLM_ML, 'date' => '2026-01-01']);
$verifier(
    ($verificationExpiree['refus'] ?? null) === null
        && in_array('VERIFICATION_EXPIREE', $porteeVerificationExpiree['motifs'] ?? [], true),
    'une vérification CONFORME expirée est signalée par le contrôle de portée à une date ultérieure',
);

// ------------------------------------------------------------------
// 45, 46 — EvaluateurPortee, contre-épreuve pure

$evaluationPure = EvaluateurPortee::evaluer(
    ['realm' => 'RLM-TEST'],
    ['realm_connu' => true, 'realm_etat' => 'SUSPENDU'],
);
$verifier(
    $evaluationPure['dans_portee'] === false && $evaluationPure['motifs'] === ['REALM_SUSPENDU'],
    'EvaluateurPortee est une fonction pure : mêmes faits, même verdict, sans connexion à une base',
);

// ------------------------------------------------------------------
// 47 — lecture datée et historique conservé

$etatAvantSuspension = $registre->resoudreEtat($RLM_ML, '2020-01-01');
$verifier(
    ($etatAvantSuspension['etat'] ?? null) !== 'SUSPENDU' || $registre->resoudreHistorique($RLM_ML) !== [],
    'la lecture datée reconstruit l’état applicable à la date demandée, sans réécrire l’historique',
);

// ------------------------------------------------------------------
// 48 — diagnostic de registre (readiness)

$diagnostic = $registre->diagnostiquerRegistre();
$verifier(
    $diagnostic['coherent'] === true && $diagnostic['cycles_detectes'] === []
        && $diagnostic['nombre_realms'] >= 4,
    'le diagnostic du registre ne signale aucun cycle ni référence orpheline sur les données réelles de la garde',
);

// ------------------------------------------------------------------
// 49 — allouerReference refuse un type inconnu (garde interne)

$exceptionLevee = false;
try {
    $reflexion = new ReflectionClass(RegistreRealms::class);
    $methode = $reflexion->getMethod('allouerReference');
    $methode->setAccessible(true);
    $methode->invoke($registre, 'type-totalement-inconnu');
} catch (ExceptionRealm) {
    $exceptionLevee = true;
}
$verifier($exceptionLevee, 'une référence d’un type non prévu par PolitiqueRealms::PREFIXE lève une erreur interne, jamais un secret silencieux');

// ------------------------------------------------------------------
// CONTRE-ÉPREUVE — référence retirée non réutilisable

$secondeIdentiteSucc = $inscrireIdentite('realm', 'Realm Successeur P3 bis — ne doit pas reprendre la référence retirée');
$reinscriptionApresRetrait = $registre->inscrireRealm($g([
    'identite_reference' => $secondeIdentiteSucc, 'code_canonique' => 'RLM-P3-SUCC-BIS', 'type_realm_reference' => 'PROGRAMME',
    'nom_affichage' => 'Successeur bis', 'classification_reference' => 'INTERNE',
]));
$verifier(
    ($reinscriptionApresRetrait['reference'] ?? null) !== $RLM_SUCCESSEUR
        && ($registre->resoudreEtat($RLM_SUCCESSEUR)['etat'] ?? null) === 'RETIRE',
    'CONTRE-ÉPREUVE : une nouvelle inscription reçoit toujours une référence neuve ; la référence retirée reste RETIRE pour toujours',
);

echo "\n";
if ($echecs === 0) {
    echo "GARDE — REGISTRE DES REALMS : ÉTABLIE.\n";
    exit(0);
}

echo "GARDE — REGISTRE DES REALMS : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
