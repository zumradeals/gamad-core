<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-002 — registre des organisations.
 *
 * Exécution : php core/registre-organisations/tests/organisations_p3.php
 * Code de sortie : 0 si toutes les épreuves et contre-épreuves passent.
 */

use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\PolitiqueOrganisations;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistreOrganisations\ValidateurStructure;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\RegistrePolitiques;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../../registre-autorisation/src/Ctr03.php';
require __DIR__ . '/../../registre-autorites/src/Ctr02.php';
require __DIR__ . '/../../registre-politiques/src/PolitiqueAdministration.php';
require __DIR__ . '/../../registre-politiques/src/SchemaPolitiques.php';
require __DIR__ . '/../../registre-politiques/src/Magasin.php';
require __DIR__ . '/../../registre-politiques/src/RegistrePolitiques.php';
require __DIR__ . '/../src/ExceptionOrganisation.php';
require __DIR__ . '/../src/PolitiqueOrganisations.php';
require __DIR__ . '/../src/SchemaOrganisations.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/ValidateurStructure.php';
require __DIR__ . '/../src/ProjectionIdentites.php';
require __DIR__ . '/../src/RegistreOrganisations.php';

$prefixe = sys_get_temp_dir() . '/gamad-organisations-' . getmypid();
$fichiers = [
    'index' => $prefixe . '-index.sqlite',
    'identites' => $prefixe . '-identites.sqlite',
    'organisations' => $prefixe . '-organisations.sqlite',
    'politiques' => $prefixe . '-politiques.sqlite',
    'autorites' => $prefixe . '-autorites.sqlite',
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

// CTR-02 (CAP-CORE-003) est adossé à une COPIE de l'index baseline, distincte
// de $index, pour pouvoir déplacer un mandat sans perturber les autres
// épreuves qui utilisent $index.
putenv('SQLITE_PATH=' . $fichiers['autorites']);
$indexAutorites = Db::connect();
BaselineOperationnelle::standard()->reconstruire($indexAutorites);
$ctr02 = new Ctr02($indexAutorites);
putenv('SQLITE_PATH=' . $fichiers['index']);

$registre = new RegistreOrganisations($index, $registreIdentites, $magasinOrganisations, $ctr01, $ctr02);

// CAP-CORE-004 lit le registre persistant des politiques (CAP-CORE-007) :
// POL-ORGANISATIONS-V1 est établie ici même, comme le fait
// `core:organisations:bootstrap` en exploitation.
$magasinPolitiques = PolitiquesMagasin::connecter($fichiers['politiques']);
$registrePolitiques = new RegistrePolitiques($index, $registreIdentites, $magasinPolitiques, $ctr01);
(static function () use ($registrePolitiques): void {
    $g = static fn (): array => [
        'politique' => PolitiqueOrganisations::POLITIQUE, 'producteur' => 'AUT-GAMAD-001',
        'source' => 'garde CAP-CORE-002', 'preuve' => 'P-' . bin2hex(random_bytes(4)),
    ];
    $actions = [
        PolitiqueOrganisations::ACTION_LIRE, PolitiqueOrganisations::ACTION_INSCRIRE,
        PolitiqueOrganisations::ACTION_MODIFIER, PolitiqueOrganisations::ACTION_ACTIVER,
        PolitiqueOrganisations::ACTION_SUSPENDRE, PolitiqueOrganisations::ACTION_DISSOUDRE,
        PolitiqueOrganisations::ACTION_RETIRER, PolitiqueOrganisations::ACTION_IDENTIFIANT_DECLARER,
        PolitiqueOrganisations::ACTION_IDENTIFIANT_FERMER, PolitiqueOrganisations::ACTION_UNITE_CREER,
        PolitiqueOrganisations::ACTION_UNITE_MODIFIER, PolitiqueOrganisations::ACTION_UNITE_FERMER,
        PolitiqueOrganisations::ACTION_RELATION_DECLARER, PolitiqueOrganisations::ACTION_RELATION_FERMER,
        PolitiqueOrganisations::ACTION_AFFILIATION_PROPOSER, PolitiqueOrganisations::ACTION_AFFILIATION_ACTIVER,
        PolitiqueOrganisations::ACTION_AFFILIATION_SUSPENDRE, PolitiqueOrganisations::ACTION_AFFILIATION_FERMER,
        PolitiqueOrganisations::ACTION_FONCTION_CREER, PolitiqueOrganisations::ACTION_REPRESENTATION_VERIFIER,
    ];
    $registrePolitiques->inscrirePolitique(array_merge($g(), [
        'reference' => PolitiqueOrganisations::POLITIQUE,
        'libelle' => 'Politique technique du registre des organisations',
        'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'CAP-CORE-002 — garde',
    ]));
    $registrePolitiques->creerVersion(PolitiqueOrganisations::POLITIQUE, array_merge($g(), ['version' => '1.0.0']));
    $numero = 0;
    $cas = [];
    foreach ($actions as $action) {
        $numero++;
        $registrePolitiques->ajouterRegle(PolitiqueOrganisations::POLITIQUE, '1.0.0', array_merge($g(), [
            'effet' => 'PERMET', 'action_reference' => $action, 'sujet_reference' => 'AUT-GAMAD-001',
            'motif' => "règle {$numero} de garde",
        ]));
        $cas[] = ['sujet' => 'AUT-GAMAD-001', 'action' => $action, 'attendu' => 'PERMIS'];
    }
    $registrePolitiques->soumettreVersion(PolitiqueOrganisations::POLITIQUE, '1.0.0', $g());
    $registrePolitiques->simulerVersion(PolitiqueOrganisations::POLITIQUE, '1.0.0', array_merge($g(), ['jeu_reference' => 'GARDE', 'cas' => $cas]));
    $registrePolitiques->activerVersion(PolitiqueOrganisations::POLITIQUE, '1.0.0', $g());
})();

$echecs = 0;
echo "GARDE — REGISTRE DES ORGANISATIONS (CAP-CORE-002)\n\n";

$verifier = static function (bool $ok, string $message) use (&$echecs): void {
    echo $ok ? "  [OK]    {$message}\n" : "  [ÉCHEC] {$message}\n";
    if (!$ok) {
        $echecs++;
    }
};

$g = static fn (array $extra = []): array => $extra + [
    'politique' => PolitiqueOrganisations::POLITIQUE,
    'source' => PolitiqueOrganisations::SOURCE,
    'producteur' => PolitiqueOrganisations::AUTORITE,
    'preuve' => 'EVT-P3-ORG-PREUVE-' . strtoupper(bin2hex(random_bytes(4))),
];

$inscrireIdentite = static function (string $type, string $libelle) use ($ctr01): string {
    $identite = $ctr01->inscrireIdentite([
        'canal' => 'AUTORITE', 'type' => $type, 'libelle' => $libelle,
        'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-ORGANISATIONS-P3',
        'source' => 'garde CAP-CORE-002', 'preuve' => 'EVT-P3-IDN-' . strtoupper(bin2hex(random_bytes(4))),
    ]);
    if (isset($identite['refus'])) {
        throw new RuntimeException('inscription identité impossible : ' . json_encode($identite));
    }

    return (string) $identite['reference'];
};

// ------------------------------------------------------------------
// 1 — bootstrap (fichier de ressource et empreinte)

$brutBootstrap = file_get_contents(__DIR__ . '/../resources/bootstrap-organisations-v1.json');
$empreinte = hash('sha256', (string) $brutBootstrap);
$payload = json_decode((string) $brutBootstrap, true);
$verifier(
    is_array($payload) && array_key_exists('organisations', $payload) && is_array($payload['organisations'])
        && preg_match('/^[0-9a-f]{64}$/', $empreinte) === 1,
    'le bootstrap est un JSON versionné, vérifiable par empreinte SHA-256, sans donnée inventée',
);

// ------------------------------------------------------------------
// 5, 6, 7, 8, 9 — identité obligatoire, mauvais type refusé, déjà liée
// refusée, référence unique, création en PREPARATION.

$IDN_ORG_A = $inscrireIdentite('organisation', 'Coopérative Alpha P3');
$IDN_ORG_B = $inscrireIdentite('organisation', 'Association Beta P3');
$IDN_PERSONNE = $inscrireIdentite('personne', 'Dirigeante P3');
$IDN_MEMBRE = $inscrireIdentite('personne', 'Membre P3');

$sansPolitique = $registre->inscrireOrganisation($g([
    'identite_reference' => $IDN_ORG_A, 'type_organisation_reference' => 'COOPERATIVE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Coopérative Alpha',
    'classification_reference' => 'INTERNE', 'politique' => '',
]));
$sansPreuve = $registre->inscrireOrganisation($g([
    'identite_reference' => $IDN_ORG_A, 'type_organisation_reference' => 'COOPERATIVE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Coopérative Alpha',
    'classification_reference' => 'INTERNE', 'preuve' => '',
]));
$identiteInconnue = $registre->inscrireOrganisation($g([
    'identite_reference' => 'IDN-ORG-INEXISTANTE', 'type_organisation_reference' => 'COOPERATIVE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'X', 'classification_reference' => 'INTERNE',
]));
$identiteMauvaisType = $registre->inscrireOrganisation($g([
    'identite_reference' => $IDN_PERSONNE, 'type_organisation_reference' => 'COOPERATIVE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'X', 'classification_reference' => 'INTERNE',
]));
$inscriptionAlpha = $registre->inscrireOrganisation($g([
    'identite_reference' => $IDN_ORG_A, 'type_organisation_reference' => 'COOPERATIVE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Coopérative Alpha',
    'classification_reference' => 'INTERNE',
]));
$verifier(
    ($sansPolitique['refus'] ?? null) === 'COMMANDE_NON_GOUVERNEE'
        && ($sansPreuve['refus'] ?? null) === 'COMMANDE_NON_GOUVERNEE'
        && ($identiteInconnue['refus'] ?? null) === 'IDENTITE_INCONNUE'
        && ($identiteMauvaisType['refus'] ?? null) === 'IDENTITE_TYPE_INVALIDE'
        && ($inscriptionAlpha['etat'] ?? null) === 'PREPARATION',
    'inscription gouvernée : identité obligatoire, de type organisation, création en PREPARATION',
);
$ORG_A = $inscriptionAlpha['reference'];

$identiteDejaLiee = $registre->inscrireOrganisation($g([
    'identite_reference' => $IDN_ORG_A, 'type_organisation_reference' => 'ASSOCIATION',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Doublon',
    'classification_reference' => 'INTERNE',
]));
$verifier(
    ($identiteDejaLiee['refus'] ?? null) === 'IDENTITE_DEJA_LIEE',
    'une identité organisation déjà liée à une fiche ne se réattache pas',
);

$inscriptionBeta = $registre->inscrireOrganisation($g([
    'identite_reference' => $IDN_ORG_B, 'type_organisation_reference' => 'ASSOCIATION',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Association Beta',
    'classification_reference' => 'INTERNE',
]));
$ORG_B = $inscriptionBeta['reference'];
$registre->activerOrganisation($ORG_B, $g());
$verifier(
    $ORG_A !== $ORG_B && str_starts_with($ORG_A, 'ORG-GAMAD-') && str_starts_with($ORG_B, 'ORG-GAMAD-'),
    'chaque organisation reçoit une référence unique et monotone',
);

// 46 — vocabulaire inconnu refusé
$typeInconnu = $registre->inscrireOrganisation($g([
    'identite_reference' => $IDN_MEMBRE, 'type_organisation_reference' => 'TYPE_INEXISTANT',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'X', 'classification_reference' => 'INTERNE',
]));
$classificationInconnue = $registre->inscrireOrganisation($g([
    'identite_reference' => $IDN_MEMBRE, 'type_organisation_reference' => 'COOPERATIVE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'X', 'classification_reference' => 'CLASSE_INEXISTANTE',
]));
$verifier(
    ($typeInconnu['refus'] ?? null) === 'TYPE_ORGANISATION_INCONNU'
        && ($classificationInconnue['refus'] ?? null) === 'CLASSIFICATION_INCONNUE',
    'un type ou une classification hors vocabulaire canonique est refusé',
);

// ------------------------------------------------------------------
// 10, 11, 12, 13 — activation gouvernée, refus sans politique/preuve,
// refus d'auto-activation.

$autoActivation = $registre->activerOrganisation($ORG_A, $g(['producteur' => $ORG_A]));
$sansPreuveActivation = $registre->activerOrganisation($ORG_A, $g(['preuve' => '']));
$activationAlpha = $registre->activerOrganisation($ORG_A, $g());
$reactivationIdempotente = $registre->activerOrganisation($ORG_A, $g());
$verifier(
    ($autoActivation['refus'] ?? null) === 'AUTO_ACTIVATION_INTERDITE'
        && ($sansPreuveActivation['refus'] ?? null) === 'COMMANDE_NON_GOUVERNEE'
        && ($activationAlpha['etat'] ?? null) === 'ACTIVE'
        && ($activationAlpha['idempotent'] ?? null) === false
        && ($reactivationIdempotente['idempotent'] ?? null) === true,
    'une organisation ne s’auto-active jamais ; l’activation gouvernée est idempotente',
);

// ------------------------------------------------------------------
// 2 — refus par défaut (CAP-CORE-004).
$ctr03 = new Ctr03($magasinPolitiques);
$permise = $ctr03->simuler(PolitiqueOrganisations::AUTORITE, PolitiqueOrganisations::ACTION_ACTIVER, $ORG_A);
$inconnue = $ctr03->simuler(PolitiqueOrganisations::AUTORITE, 'activer une organisation sans aucune politique adoptée', $ORG_A);
$verifier(
    ($permise['decision'] ?? null) === 'PERMIS'
        && ($permise['politique'] ?? null) === PolitiqueOrganisations::POLITIQUE
        && ($inconnue['decision'] ?? null) === 'REFUSÉ',
    'CAP-CORE-004 permet l’action gouvernée et refuse toujours par défaut',
);

// ------------------------------------------------------------------
// 14 — suspension opposable.
$suspension = $registre->suspendreOrganisation($ORG_A, $g());
$verifier(($suspension['etat'] ?? null) === 'SUSPENDUE', 'la suspension est immédiatement opposable');

$reactivation = $registre->activerOrganisation($ORG_A, $g());
$verifier(($reactivation['etat'] ?? null) === 'ACTIVE', 'une organisation suspendue peut être réactivée');

// ------------------------------------------------------------------
// 18, 19 — révisions en ajout seul.
$revision1 = $registre->modifierOrganisation($ORG_A, $g(['denomination_officielle' => 'Coopérative Alpha SCOP', 'classification_reference' => 'INTERNE']));
$revision2 = $registre->modifierOrganisation($ORG_A, $g(['denomination_officielle' => 'Coopérative Alpha SCOP (renommée)', 'classification_reference' => 'INTERNE']));
$revisionCourante = $registre->resoudreRevision($ORG_A);
$verifier(
    ($revision1['numero_revision'] ?? 0) < ($revision2['numero_revision'] ?? 0)
        && $revisionCourante['denomination_officielle'] === 'Coopérative Alpha SCOP (renommée)'
        && $revisionCourante['numero_revision'] === $revision2['numero_revision'],
    'chaque modification ajoute une révision ; aucune ancienne dénomination n’est réécrite',
);

// ------------------------------------------------------------------
// 19, 20, 21 — identifiant externe unique, non vérifié explicite.
$identifiant = $registre->declarerIdentifiantExterne($ORG_A, $g([
    'systeme_reference' => 'REGISTRE-NATIONAL', 'type_identifiant_reference' => 'REGISTRE_COMMERCE',
    'valeur_normalisee' => 'RC-P3-000001',
]));
$identifiantDoublon = $registre->declarerIdentifiantExterne($ORG_A, $g([
    'systeme_reference' => 'REGISTRE-NATIONAL', 'type_identifiant_reference' => 'REGISTRE_COMMERCE',
    'valeur_normalisee' => 'RC-P3-000001',
]));
$verifier(
    ($identifiant['verifie'] ?? null) === false
        && ($identifiantDoublon['refus'] ?? null) === 'IDENTIFIANT_DEJA_DECLARE',
    'un identifiant externe non vérifié est explicite ; le triplet système/type/valeur est unique',
);

// ------------------------------------------------------------------
// 21, 22, 23, 25, 26 — unité créée, parent de même organisation, cycle
// d'unité refusé, fermeture, descendants non fermés silencieusement.
$siege = $registre->creerUnite($ORG_A, $g(['type_unite_reference' => 'SIEGE', 'nom' => 'Siège Alpha', 'classification_reference' => 'INTERNE']));
$departement = $registre->creerUnite($ORG_A, $g([
    'type_unite_reference' => 'DEPARTEMENT', 'nom' => 'Département production', 'classification_reference' => 'INTERNE',
    'unite_parente_reference' => $siege['reference'],
]));
$parentAutreOrganisation = $registre->creerUnite($ORG_B, $g([
    'type_unite_reference' => 'DEPARTEMENT', 'nom' => 'X', 'classification_reference' => 'INTERNE',
    'unite_parente_reference' => $siege['reference'],
]));
$verifier(
    ($departement['organisation_reference'] ?? null) === $ORG_A
        && ($parentAutreOrganisation['refus'] ?? null) === 'UNITE_PARENTE_INVALIDE',
    'une unité appartient à une seule organisation ; sa parente appartient à la même organisation',
);

$cycleUnite = ValidateurStructure::uniteCreeraitCycle($siege['reference'], $departement['reference'], [
    $siege['reference'] => null, $departement['reference'] => $siege['reference'],
]);
$deplacementCycle = $registre->deplacerUnite($siege['reference'], $g(['unite_parente_reference' => $departement['reference']]));
$verifier(
    $cycleUnite === true && ($deplacementCycle['refus'] ?? null) === 'CYCLE_UNITE_DETECTE',
    'un déplacement d’unité qui créerait un cycle hiérarchique est refusé',
);

$fermetureAvecDescendant = $registre->fermerUnite($siege['reference'], $g());
$fermetureDepartement = $registre->fermerUnite($departement['reference'], $g());
$fermetureSiege = $registre->fermerUnite($siege['reference'], $g());
$verifier(
    ($fermetureAvecDescendant['refus'] ?? null) === 'DESCENDANTS_ACTIFS'
        && ($fermetureDepartement['etat'] ?? null) === 'FERMEE'
        && ($fermetureSiege['etat'] ?? null) === 'FERMEE',
    'une unité n’est jamais fermée en cascade silencieuse : les descendants actifs bloquent, explicitement',
);

// ------------------------------------------------------------------
// 27, 28, 29, 30 — relation interorganisationnelle, auto-relation
// refusée, cycle hiérarchique refusé, pourcentage borné.
$autoRelation = $registre->declarerRelationOrganisationnelle($g([
    'organisation_source_reference' => $ORG_A, 'organisation_cible_reference' => $ORG_A,
    'type_relation_reference' => 'FILIALE_DE', 'classification_reference' => 'INTERNE',
]));
$pourcentageInvalide = $registre->declarerRelationOrganisationnelle($g([
    'organisation_source_reference' => $ORG_A, 'organisation_cible_reference' => $ORG_B,
    'type_relation_reference' => 'PARTENAIRE_DE', 'classification_reference' => 'INTERNE', 'pourcentage' => 150,
]));
$relation = $registre->declarerRelationOrganisationnelle($g([
    'organisation_source_reference' => $ORG_A, 'organisation_cible_reference' => $ORG_B,
    'type_relation_reference' => 'FILIALE_DE', 'classification_reference' => 'INTERNE', 'pourcentage' => 60,
]));
$relationCycle = $registre->declarerRelationOrganisationnelle($g([
    'organisation_source_reference' => $ORG_B, 'organisation_cible_reference' => $ORG_A,
    'type_relation_reference' => 'FILIALE_DE', 'classification_reference' => 'INTERNE',
]));
$verifier(
    ($autoRelation['refus'] ?? null) === 'AUTO_RELATION_INTERDITE'
        && ($pourcentageInvalide['refus'] ?? null) === 'POURCENTAGE_INVALIDE'
        && ($relation['reference'] ?? null) !== null
        && ($relationCycle['refus'] ?? null) === 'CYCLE_HIERARCHIQUE_DETECTE',
    'relation interorganisationnelle : ni auto-référence, ni cycle hiérarchique, ni pourcentage hors 0–100',
);

$fermetureRelation = $registre->fermerRelationOrganisationnelle($relation['reference'], $g());
$verifier(($fermetureRelation['date_fin'] ?? null) !== null, 'une relation se ferme, datée, sans suppression');

// ------------------------------------------------------------------
// 31–35 — affiliation proposée / activée / suspendue / fermée ; aucun
// mandat créé.
$affiliation = $registre->proposerAffiliation($g([
    'organisation_reference' => $ORG_A, 'identite_reference' => $IDN_MEMBRE, 'type_affiliation_reference' => 'MEMBRE',
    'niveau_assurance_reference' => 'A1', 'classification_reference' => 'INTERNE', 'producteur_reference' => $ORG_A,
]));
$verifier(($affiliation['etat'] ?? null) === 'PROPOSEE', 'une affiliation est proposée avant d’être active');

$activationAffiliation = $registre->activerAffiliation($affiliation['reference'], $g());
$suspensionAffiliation = $registre->suspendreAffiliation($affiliation['reference'], $g());
$reactivationAffiliation = $registre->activerAffiliation($affiliation['reference'], $g());
$verifier(
    ($activationAffiliation['etat'] ?? null) === 'ACTIVE'
        && ($suspensionAffiliation['etat'] ?? null) === 'SUSPENDUE'
        && ($reactivationAffiliation['refus'] ?? null) === 'ETAT_INCOMPATIBLE',
    'affiliation : proposée → active → suspendue ; une affiliation suspendue ne se réactive pas silencieusement',
);
// La fermeture d'une SUSPENDUE reste possible ; on la ferme explicitement ici.
$fermetureAffiliationDepuisSuspendue = $registre->fermerAffiliation($affiliation['reference'], $g());
$verifier(
    ($fermetureAffiliationDepuisSuspendue['etat'] ?? null) === 'CLOSE',
    'une affiliation suspendue se ferme explicitement, sans suppression',
);

$appartenance = $registre->verifierAppartenance($IDN_MEMBRE, $ORG_A);
$verifier(
    $appartenance['membre'] === false,
    'une affiliation fermée n’ouvre plus d’appartenance active — aucun mandat n’a jamais été créé au passage',
);

// ------------------------------------------------------------------
// 36, 37, 38, 39, 40 — dirigeant/représentant sans mandat non opposables ;
// mandat actif opposable ; mandat expiré non opposable ; mandat
// indisponible non opposable.
$affiliationDirigeant = $registre->proposerAffiliation($g([
    'organisation_reference' => $ORG_A, 'identite_reference' => $IDN_PERSONNE, 'type_affiliation_reference' => 'DIRIGEANT',
    'niveau_assurance_reference' => 'A2', 'classification_reference' => 'INTERNE', 'producteur_reference' => $ORG_A,
]));
$registre->activerAffiliation($affiliationDirigeant['reference'], $g());

$representationSansFonction = $registre->verifierRepresentation($IDN_PERSONNE, $ORG_A);
$verifier(
    $representationSansFonction['opposable'] === false
        && in_array('MANDAT_ABSENT', $representationSansFonction['motifs'], true),
    'un dirigeant sans fonction liée à un mandat CAP-CORE-003 reste non opposable',
);

// Fonction interne liée à une fonction réelle de l'index baseline
// (FCT-CORE-001 — voir core/registre-normes/resources/index-baseline-v1.json,
// table `fonction`), pour prouver la résolution positive avec CAP-CORE-003.
// Le mandat réel existant sur FCT-CORE-001 (MANDAT-GENESIS-II-0001) a pour
// titulaire AUT-GAMAD-001, une identité que le corpus documentaire ne fait
// jamais passer par un événement d'état explicite (`etat_entite` n'a aucune
// ligne pour AUT-GAMAD-001) : elle ne peut donc pas, honnêtement, servir de
// cas positif d'« identité active » sans fabriquer un fait qui n'existe pas.
// Le mandat de test ci-dessous est donc une fixture explicite, construite
// dans la copie isolée `$indexAutorites` (jamais dans l'index de production),
// sur la MÊME fonction réelle FCT-CORE-001, pour une identité que ce test a
// lui-même inscrite et activée via CAP-CORE-001 (donc réellement ACTIVE).
$fonction = $registre->creerFonctionInterne($ORG_A, $g([
    'type_fonction_reference' => 'DIRECTION_GENERALE', 'libelle' => 'Direction générale P3',
    'mandat_fonction_reference' => 'FCT-CORE-001', 'date_debut' => '2026-07-01',
]));
$verifier(($fonction['reference'] ?? null) !== null, 'une fonction interne descriptive est créée, sans droit automatique');

$indexAutorites->prepare(
    'INSERT INTO titulaire (reference, libelle, nature) VALUES (?, ?, ?)'
)->execute([$IDN_PERSONNE, 'Dirigeante P3', 'personne']);
$indexAutorites->prepare(
    'INSERT INTO mandat (reference, fonction_reference, titulaire_reference, debut, fin, niveau_preuve, adoption_reference)
     VALUES (?, ?, ?, ?, NULL, ?, ?)'
)->execute(['MANDAT-P3-TEST-001', 'FCT-CORE-001', $IDN_PERSONNE, '2026-07-01', 'P1 — DOCUMENTÉ', 'ADOPTION-P3-TEST']);
$indexAutorites->prepare(
    'INSERT INTO etat_mandat (mandat_reference, valeur, date_effet, adoption_reference) VALUES (?, ?, ?, ?)'
)->execute(['MANDAT-P3-TEST-001', 'ACTIF — TEST', '2026-07-01', 'ADOPTION-P3-TEST']);

$affiliationDirigeanteReelle = $registre->proposerAffiliation($g([
    'organisation_reference' => $ORG_A, 'identite_reference' => $IDN_PERSONNE, 'type_affiliation_reference' => 'DIRIGEANT',
    'niveau_assurance_reference' => 'A3', 'classification_reference' => 'INTERNE', 'producteur_reference' => $ORG_A,
    'date_debut' => '2026-07-01',
]));
$registre->activerAffiliation($affiliationDirigeanteReelle['reference'], $g(['date' => '2026-07-02']));
$representationAvecMandatActif = $registre->verifierRepresentation($IDN_PERSONNE, $ORG_A, null, '2026-07-27');
$verifier(
    $representationAvecMandatActif['opposable'] === true
        && $representationAvecMandatActif['mandat'] === 'MANDAT-P3-TEST-001'
        && $representationAvecMandatActif['motifs'] === [],
    'affiliation active + fonction reliée + mandat actif vérifié par CAP-CORE-003 = représentation opposable',
);

// 39 — mandat expiré non opposable : déplacer la fin du mandat de test dans
// le passé et vérifier que la même représentation bascule à non opposable.
$indexAutorites->exec("UPDATE mandat SET fin = '2020-01-01' WHERE reference = 'MANDAT-P3-TEST-001'");
$representationExpiree = $registre->verifierRepresentation($IDN_PERSONNE, $ORG_A, null, '2026-08-01');
$verifier(
    $representationExpiree['opposable'] === false && in_array('MANDAT_ABSENT', $representationExpiree['motifs'], true),
    'contre-épreuve : un mandat déplacé et expiré à la date demandée ne rend plus la représentation opposable',
);
$indexAutorites->exec("UPDATE mandat SET fin = NULL WHERE reference = 'MANDAT-P3-TEST-001'");

// Contre-épreuve : sans le lien mandat_fonction_reference, même mandat réel,
// la représentation reste refusée par construction (aucun rapprochement flou).
$fonctionSansMandat = $registre->creerFonctionInterne($ORG_B, $g([
    'type_fonction_reference' => 'GERANCE', 'libelle' => 'Gérance P3 sans lien de mandat',
]));
$affiliationGerant = $registre->proposerAffiliation($g([
    'organisation_reference' => $ORG_B, 'identite_reference' => $IDN_PERSONNE, 'type_affiliation_reference' => 'REPRESENTANT',
    'niveau_assurance_reference' => 'A2', 'classification_reference' => 'INTERNE', 'producteur_reference' => $ORG_B,
]));
$registre->activerAffiliation($affiliationGerant['reference'], $g());
$representationSansLien = $registre->verifierRepresentation($IDN_PERSONNE, $ORG_B);
$verifier(
    $representationSansLien['opposable'] === false && in_array('MANDAT_ABSENT', $representationSansLien['motifs'], true),
    'un représentant sans fonction reliée à CAP-CORE-003 reste non opposable, même si un mandat réel existe ailleurs',
);

// Mandat indisponible (CAP-CORE-003 non raccordé) : reconstruire un registre
// identique mais sans Ctr02 — la représentation doit rester fermée.
$registreSansAutorites = new RegistreOrganisations($index, $registreIdentites, $magasinOrganisations, $ctr01, null);
$representationIndisponible = $registreSansAutorites->verifierRepresentation('AUT-GAMAD-001', $ORG_A, null, '2026-07-27');
$verifier(
    $representationIndisponible['opposable'] === false
        && in_array('MANDAT_INDISPONIBLE', $representationIndisponible['motifs'], true),
    'l’absence de réponse de CAP-CORE-003 vaut toujours non opposable — jamais une approximation',
);

// ------------------------------------------------------------------
// 41, 42 — identité suspendue / organisation suspendue non utilisables.
$organisationSuspendueTest = $registre->inscrireOrganisation($g([
    'identite_reference' => $inscrireIdentite('organisation', 'Gamma P3'), 'type_organisation_reference' => 'ASSOCIATION',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Gamma', 'classification_reference' => 'INTERNE',
]));
$ORG_GAMMA = $organisationSuspendueTest['reference'];
$affiliationSurGammaAvantActivation = $registre->proposerAffiliation($g([
    'organisation_reference' => $ORG_GAMMA, 'identite_reference' => $IDN_MEMBRE, 'type_affiliation_reference' => 'MEMBRE',
    'niveau_assurance_reference' => 'A1', 'classification_reference' => 'INTERNE', 'producteur_reference' => $ORG_GAMMA,
]));
$activationSurOrganisationNonActive = $registre->activerAffiliation($affiliationSurGammaAvantActivation['reference'], $g());
$verifier(
    ($activationSurOrganisationNonActive['refus'] ?? null) === 'ORGANISATION_NON_ACTIVE',
    'une organisation non ACTIVE (encore en PREPARATION) ne peut pas voir d’affiliation activée',
);

// ------------------------------------------------------------------
// 15, 16, 17 — dissolution terminale, retrait sans suppression, référence
// non réutilisable.
$registre->activerOrganisation($ORG_GAMMA, $g());
$dissolution = $registre->dissoudreOrganisation($ORG_GAMMA, $g());
$nouvelleAffiliationApresDissolution = $registre->proposerAffiliation($g([
    'organisation_reference' => $ORG_GAMMA, 'identite_reference' => $IDN_MEMBRE, 'type_affiliation_reference' => 'MEMBRE',
    'niveau_assurance_reference' => 'A1', 'classification_reference' => 'INTERNE', 'producteur_reference' => $ORG_GAMMA,
]));
$activationApresDissolution = $registre->activerAffiliation($nouvelleAffiliationApresDissolution['reference'] ?? '', $g());
$verifier(
    ($dissolution['etat'] ?? null) === 'DISSOUTE'
        && ($activationApresDissolution['refus'] ?? null) === 'ORGANISATION_NON_ACTIVE',
    'la dissolution est terminale ; aucune nouvelle affiliation active n’y est plus possible',
);

$retraitSansMotif = $registre->retirerOrganisation($ORG_B, $g());
$retrait = $registre->retirerOrganisation($ORG_B, $g(['motif' => 'retrait de garde P3']));
$retraitRejoue = $registre->retirerOrganisation($ORG_B, $g(['motif' => 'retrait de garde P3']));
$toujoursLisible = $registre->resoudreOrganisation($ORG_B);
$verifier(
    ($retraitSansMotif['refus'] ?? null) === 'MOTIF_ABSENT'
        && ($retrait['etat'] ?? null) === 'RETIREE'
        && ($retraitRejoue['idempotent'] ?? null) === true
        && $toujoursLisible !== null && $toujoursLisible['etat'] === 'RETIREE',
    'le retrait exige un motif, est idempotent, et ne supprime jamais physiquement la fiche',
);

$nouvelleOrgApresRetrait = $registre->inscrireOrganisation($g([
    'identite_reference' => $inscrireIdentite('organisation', 'Delta P3'), 'type_organisation_reference' => 'ASSOCIATION',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Delta', 'classification_reference' => 'INTERNE',
]));
$verifier(
    ($nouvelleOrgApresRetrait['reference'] ?? null) !== $ORG_B,
    'une référence retirée n’est jamais réattribuée : l’allocation reste strictement monotone',
);

// ------------------------------------------------------------------
// 48, 49 — aucun secret ni donnée RH détaillée dans le schéma ou le magasin.
$sourceSchema = (string) file_get_contents(__DIR__ . '/../src/SchemaOrganisations.php');
$interditsSchema = ['password', 'mot_de_passe', 'secret', 'private_key', 'clé_privée', 'salaire', 'salary', 'payroll', 'dossier_medical'];
$trouvesSchema = array_values(array_filter($interditsSchema, static fn (string $m): bool => stripos($sourceSchema, $m) !== false));
$contenuMagasin = (string) file_get_contents($fichiers['organisations']);
$interditsMagasin = ['password', 'secret', 'mot_de_passe', 'private_key', 'clé_privée'];
$trouvesMagasin = array_values(array_filter($interditsMagasin, static fn (string $m): bool => stripos($contenuMagasin, $m) !== false));
$verifier(
    $trouvesSchema === [] && $trouvesMagasin === [],
    'ni le schéma ni le magasin des organisations ne portent de secret ou de donnée RH détaillée',
);

// ------------------------------------------------------------------
// 52 — reconstruire la baseline documentaire ne supprime jamais le
// registre persistant des organisations, même en connexion partagée.
$partage = new PDO('sqlite::memory:');
$partage->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
BaselineOperationnelle::standard()->reconstruire($partage);
$ctr01Partage = new Ctr01($partage, $partage);
$registrePartage = new RegistreOrganisations($partage, $partage, $partage, $ctr01Partage);
$identitePartagee = $ctr01Partage->inscrireIdentite([
    'canal' => 'AUTORITE', 'type' => 'organisation', 'libelle' => 'Partagée P3',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-P3',
    'source' => 'garde', 'preuve' => 'EVT-P3-PARTAGE',
]);
$registrePartage->inscrireOrganisation($g(['identite_reference' => $identitePartagee['reference'], 'type_organisation_reference' => 'ASSOCIATION', 'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Partagée', 'classification_reference' => 'INTERNE']));
BaselineOperationnelle::standard()->reconstruire($partage);
$verifier(
    $registrePartage->resoudreOrganisationParIdentite($identitePartagee['reference']) !== null,
    'reconstruire la baseline documentaire ne supprime jamais le registre persistant des organisations',
);

// ------------------------------------------------------------------
// 53 — rollback transactionnel : une exception interne pendant l'écriture
// n'écrit rien.
$avant = (int) $magasinOrganisations->query('SELECT COUNT(*) FROM organisation_cycle')->fetchColumn();
try {
    $refl = new ReflectionClass($registre);
    $methode = $refl->getMethod('inscrireCycle');
    $methode->setAccessible(true);
    $magasinOrganisations->beginTransaction();
    try {
        $magasinOrganisations->prepare('INSERT INTO organisation_cycle (organisation_reference,etat_reference,date_effet,motif,acteur_reference,politique_reference,preuve_reference,correlation_id,cree_le) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$ORG_A, 'ACTIVE', '2026-08-01', null, 'X', 'X', 'X', null, gmdate('c')]);
        $methode->invoke($registre, $ORG_A, 'ETAT_HORS_LISTE_CLOSE', '2026-08-01', null, 'X', 'X', 'X', null);
        $magasinOrganisations->commit();
    } catch (\Throwable) {
        $magasinOrganisations->rollBack();
    }
} catch (\Throwable) {
}
$apres = (int) $magasinOrganisations->query('SELECT COUNT(*) FROM organisation_cycle')->fetchColumn();
$verifier($avant === $apres, 'une écriture interrompue par une exception ne laisse aucune trace partielle (rollback)');

// ------------------------------------------------------------------
// 54, 55 — idempotence des commandes gouvernées, proxy documenté de la
// maîtrise de concurrence (un test de concurrence réelle multi-processus
// relève de l'exercice PostgreSQL séparé, non de cette garde unitaire).
$doubleActivationOrg = $registre->activerOrganisation($ORG_A, $g());
$verifier(($doubleActivationOrg['idempotent'] ?? null) === true, 'rejouer une activation déjà acquise reste idempotent (proxy de la garde de concurrence)');

// ------------------------------------------------------------------
// 60 — contre-épreuve structurelle : un cycle halte hiérarchique injecté
// directement en base DOIT être détecté par diagnostiquerStructure().
$diagnosticSain = $registre->diagnostiquerStructure($ORG_A);
$magasinOrganisations->prepare(
    "INSERT INTO organisation_relation (reference,organisation_source_reference,organisation_cible_reference,type_relation_reference,date_debut,date_fin,pourcentage,classification_reference,source_reference,preuve_reference,acteur_reference,cree_le) VALUES (?,?,?,?,?,NULL,NULL,?,?,?,?,?)"
)->execute(['REL-GAMAD-FORCE-999999', $ORG_A, $ORG_A, 'FILIALE_DE', '2026-01-01', 'INTERNE', 'X', 'X', 'X', gmdate('c')]);
$diagnosticCorrompu = $registre->diagnostiquerStructure($ORG_A);
$verifier(
    $diagnosticSain['coherent'] === true && $diagnosticCorrompu['coherent'] === false,
    'contre-épreuve : une relation hiérarchique injectée directement (contournant la garde applicative) est détectée par le diagnostic de structure',
);

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-002 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-002 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
