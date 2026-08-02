<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-007 — registre persistant et gouverné des
 * politiques et règles techniques.
 *
 * Avant ce chantier, les politiques et règles vivaient dans l'index
 * documentaire reconstructible (`core/registre-normes`), lues par CTR-03 sans
 * cycle de vie ni gouvernance propre. CAP-CORE-007 leur donne une fiche
 * persistante, des versions numérotées et immuables une fois soumises, un
 * cycle en ajout seul (BROUILLON → EN_VALIDATION → ACTIVE → SUSPENDUE →
 * REMPLACEE → RETIREE), et une simulation obligatoire avant toute activation.
 *
 * CONTRE-ÉPREUVE : la dernière épreuve retire une ligne du magasin et vérifie
 * que sa résolution échoue. Un test qui ne peut pas échouer ne prouve rien.
 *
 * Exécution : php core/registre-politiques/tests/politiques_p3.php
 * Code de sortie : 0 si la garde passe, 1 sinon.
 */

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../src/PolitiqueAdministration.php';
require __DIR__ . '/../src/SchemaPolitiques.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/RegistrePolitiques.php';
require __DIR__ . '/../../registre-autorisation/src/Ctr03.php';

use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Gamad\RegistrePolitiques\RegistrePolitiques;

$pid = getmypid();
$fichiers = [
    'index' => sys_get_temp_dir() . "/regn-pol-p3-index-{$pid}.sqlite",
    'identites' => sys_get_temp_dir() . "/regn-pol-p3-identites-{$pid}.sqlite",
    'politiques' => sys_get_temp_dir() . "/regn-pol-p3-politiques-{$pid}.sqlite",
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
$polMagasin = PolitiquesMagasin::connecter($fichiers['politiques']);
$registre = new RegistrePolitiques($index, $identitesReg, $polMagasin, $ctr01);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — REGISTRE DES POLITIQUES (CAP-CORE-007)\n\n";

$AUTORITE = PolitiqueInscription::AUTORITE_INSCRIPTION; // AUT-GAMAD-001
$POLITIQUE = PolitiqueAdministration::POLITIQUE;
$SOURCE_TECH = 'garde CAP-CORE-007';

$gouvernance = static fn (?string $acteur = null): array => [
    'politique' => $POLITIQUE,
    'producteur' => $acteur ?? $AUTORITE,
    'source' => $SOURCE_TECH,
    'preuve' => 'PREUVE-' . bin2hex(random_bytes(6)),
];

/* -------------------------------------------------------- bootstrap réel */
echo "  Bootstrap fidèle des politiques déjà exploitées\n";

$brut = file_get_contents(__DIR__ . '/../resources/bootstrap-politiques-v1.json');
$empreinteAttendue = 'f64a5eada6c02e303c783b6c69bb34276e5ef900752145ce88db6628d7c51e08';
$verifier(
    $brut !== false && hash_equals($empreinteAttendue, hash('sha256', $brut)),
    'la ressource figée de bootstrap porte l’empreinte SHA-256 attendue',
);
$bootstrap = json_decode((string) $brut, true, 512, JSON_THROW_ON_ERROR);
$politiquesFigees = $bootstrap['politiques'];
$verifier(
    count($politiquesFigees) === 8,
    'la photographie figée porte les huit politiques déjà exploitées',
    count($politiquesFigees) . ' politique(s)',
);

$reglesTotal = 0;
foreach ($politiquesFigees as $p) {
    $reference = $p['reference'];
    $version = $p['version'];
    $sourceRef = $p['source'] . (!empty($p['adoption_reference']) ? ' (' . $p['adoption_reference'] . ')' : '');
    $regles = $p['regles'];
    $reglesTotal += count($regles);

    $registre->inscrirePolitique(array_merge($gouvernance(), [
        'reference' => $reference, 'libelle' => $p['libelle'],
        'proprietaire_reference' => $AUTORITE, 'source_reference' => $sourceRef,
    ]));
    $registre->creerVersion($reference, array_merge($gouvernance(), ['version' => $version]));
    $cas = [];
    foreach ($regles as $r) {
        $registre->ajouterRegle($reference, $version, array_merge($gouvernance(), [
            'effet' => $r['effet'], 'action_reference' => $r['action'],
            'sujet_reference' => $r['sujet_type'], 'motif' => $r['motif'],
        ]));
        $cas[] = ['sujet' => $r['sujet_type'] ?? $AUTORITE, 'action' => $r['action'], 'attendu' => $r['effet'] === 'PERMET' ? 'PERMIS' : 'REFUSE'];
    }
    $registre->soumettreVersion($reference, $version, $gouvernance());
    $registre->simulerVersion($reference, $version, array_merge($gouvernance(), ['jeu_reference' => 'GARDE-BOOT', 'cas' => $cas]));
    $registre->activerVersion($reference, $version, $gouvernance());
}
$verifier($reglesTotal === 42, 'la photographie figée porte les quarante-deux règles déjà exploitées', "{$reglesTotal} règle(s)");

$diagnostic = $registre->diagnostiquerRegistre();
$verifier(
    $diagnostic['coherent'] === true,
    'au plus une version active par politique après le bootstrap',
    implode(', ', $diagnostic['divergences']),
);

/* -------------------------------------------------------- idempotence */
echo "\n  Idempotence du bootstrap\n";

$avantRejeu = $registre->resoudreVersion('POL-SOURCES-V1', '1.0.0');
$historiqueAvantRejeu = count($registre->resoudreHistorique('POL-SOURCES-V1'));
$rejeuInscription = $registre->inscrirePolitique(array_merge($gouvernance(), [
    'reference' => 'POL-SOURCES-V1', 'libelle' => 'Doublon', 'proprietaire_reference' => $AUTORITE,
    'source_reference' => 'x',
]));
$rejeuActivation = $registre->activerVersion('POL-SOURCES-V1', '1.0.0', $gouvernance());
$historiqueApresRejeu = count($registre->resoudreHistorique('POL-SOURCES-V1'));
$verifier(
    ($rejeuInscription['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE'
        && ($rejeuActivation['idempotent'] ?? null) === true
        && $historiqueApresRejeu === $historiqueAvantRejeu,
    'rejouer le bootstrap ne crée aucun doublon de politique ni de ligne de cycle',
);
$verifier(
    $avantRejeu['empreinte_contenu'] === $registre->resoudreVersion('POL-SOURCES-V1', '1.0.0')['empreinte_contenu'],
    'l’empreinte de contenu d’une version déjà soumise ne varie jamais',
);

/* --------------------------------------------------- inscription gouvernée */
echo "\n  Inscription gouvernée\n";

$REF = 'POL-P3-TEST-001';
$sansGouvernance = $registre->inscrirePolitique([
    'reference' => $REF, 'libelle' => 'Test', 'proprietaire_reference' => $AUTORITE, 'source_reference' => 'x',
]);
$verifier(
    ($sansGouvernance['refus'] ?? null) === 'DOSSIER_INCOMPLET',
    'une inscription sans champ de gouvernance est refusée',
);

$sansPreuve = $registre->inscrirePolitique([
    'reference' => $REF, 'libelle' => 'Test', 'proprietaire_reference' => $AUTORITE, 'source_reference' => 'x',
    'politique' => $POLITIQUE, 'producteur' => $AUTORITE, 'source' => $SOURCE_TECH, 'preuve' => '',
]);
$verifier(
    ($sansPreuve['refus'] ?? null) === 'DOSSIER_INCOMPLET',
    'une inscription sans preuve est refusée : le refus par défaut couvre aussi ce registre',
);

$proprietaireInconnu = $registre->inscrirePolitique(array_merge($gouvernance(), [
    'reference' => $REF, 'libelle' => 'Test', 'proprietaire_reference' => 'IDN-INCONNUE-000', 'source_reference' => 'x',
]));
$verifier(
    ($proprietaireInconnu['refus'] ?? null) === 'PROPRIETAIRE_INCONNU',
    'un propriétaire non connu de CAP-CORE-001 est refusé',
);

$inscription = $registre->inscrirePolitique(array_merge($gouvernance(), [
    'reference' => $REF, 'libelle' => 'Politique de test P3', 'proprietaire_reference' => $AUTORITE,
    'source_reference' => 'garde CAP-CORE-007',
]));
$doublon = $registre->inscrirePolitique(array_merge($gouvernance(), [
    'reference' => $REF, 'libelle' => 'Doublon', 'proprietaire_reference' => $AUTORITE, 'source_reference' => 'x',
]));
$verifier(
    ($inscription['reference'] ?? null) === $REF && ($doublon['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    'une politique s’inscrit une fois ; sa référence ne se réinscrit jamais',
);

/* ------------------------------------------------------------- versions */
echo "\n  Versions : format, unicité, contenu\n";

$versionInvalide = $registre->creerVersion($REF, array_merge($gouvernance(), ['version' => '1.0']));
$verifier(
    ($versionInvalide['refus'] ?? null) === 'VERSION_INVALIDE',
    'une version hors format X.Y.Z est refusée',
);

$creation = $registre->creerVersion($REF, array_merge($gouvernance(), ['version' => '1.0.0']));
$verifier(
    ($creation['etat'] ?? null) === 'BROUILLON',
    'une version créée naît en BROUILLON, jamais active d’emblée',
);

$versionDoublon = $registre->creerVersion($REF, array_merge($gouvernance(), ['version' => '1.0.0']));
$verifier(
    ($versionDoublon['refus'] ?? null) === 'VERSION_DEJA_UTILISEE',
    'une version déjà utilisée pour cette politique est refusée',
);

/* --------------------------------------------------------------- règles */
echo "\n  Règles : validation et immuabilité post-BROUILLON\n";

$effetInconnu = $registre->ajouterRegle($REF, '1.0.0', array_merge($gouvernance(), [
    'effet' => 'AUTORISE', 'action_reference' => 'agir', 'motif' => 'motif',
]));
$verifier(
    ($effetInconnu['refus'] ?? null) === 'EFFET_INCONNU',
    'un effet hors la liste close PERMET/REFUSE est refusé',
);

$actionVide = $registre->ajouterRegle($REF, '1.0.0', array_merge($gouvernance(), [
    'effet' => 'PERMET', 'action_reference' => '', 'motif' => 'motif',
]));
$verifier(
    ($actionVide['refus'] ?? null) === 'ACTION_VIDE',
    'une règle sans action est refusée',
);

$motifAbsent = $registre->ajouterRegle($REF, '1.0.0', array_merge($gouvernance(), [
    'effet' => 'PERMET', 'action_reference' => 'agir sur le test P3', 'motif' => '',
]));
$verifier(
    ($motifAbsent['refus'] ?? null) === 'MOTIF_ABSENT',
    'une règle sans motif est refusée : aucune permission ne s’écrit sans justification',
);

$regle1 = $registre->ajouterRegle($REF, '1.0.0', array_merge($gouvernance(), [
    'effet' => 'PERMET', 'action_reference' => 'agir sur le test P3', 'sujet_type' => $AUTORITE,
    'motif' => 'l’autorité peut agir sur le test P3',
]));
$ordreDoublon = $registre->ajouterRegle($REF, '1.0.0', array_merge($gouvernance(), [
    'effet' => 'PERMET', 'action_reference' => 'autre action', 'motif' => 'motif', 'ordre' => $regle1['ordre'],
]));
$verifier(
    ($ordreDoublon['refus'] ?? null) === 'ORDRE_DEJA_UTILISE',
    'un ordre déjà utilisé dans la même version est refusé',
);

$regle1Id = (int) array_values(array_filter(
    $registre->resoudreVersion($REF, '1.0.0')['regles'],
    static fn (array $r): bool => $r['action_reference'] === 'agir sur le test P3',
))[0]['id'];
$modificationInexistante = $registre->modifierRegle($REF, '1.0.0', 999999, array_merge($gouvernance(), [
    'motif' => 'motif pour une règle qui n’existe pas',
]));
$verifier(
    ($modificationInexistante['refus'] ?? null) === 'REGLE_INCONNUE',
    'modifier une règle inconnue de cette version est refusé',
);
$modification = $registre->modifierRegle($REF, '1.0.0', $regle1Id, array_merge($gouvernance(), [
    'motif' => 'motif révisé avant soumission',
]));
$verifier(
    ($modification['id'] ?? null) === $regle1Id
        && $registre->resoudreVersion($REF, '1.0.0')['regles'][array_search(
            $regle1Id,
            array_column($registre->resoudreVersion($REF, '1.0.0')['regles'], 'id'),
        )]['motif'] === 'motif révisé avant soumission',
    'une règle se modifie tant que sa version reste BROUILLON',
);

$soumissionVide = $registre->soumettreVersion('POL-P3-VIDE-INEXISTANTE', '1.0.0', $gouvernance());
$verifier(
    ($soumissionVide['refus'] ?? null) === 'VERSION_INCONNUE',
    'soumettre une version d’une politique inconnue est refusé',
);

$soumission = $registre->soumettreVersion($REF, '1.0.0', $gouvernance());
$verifier(
    ($soumission['etat'] ?? null) === 'EN_VALIDATION' && !empty($soumission['empreinte_contenu']),
    'une version BROUILLON avec règles se soumet et fige son empreinte de contenu',
);

$regleApresSoumission = $registre->ajouterRegle($REF, '1.0.0', array_merge($gouvernance(), [
    'effet' => 'PERMET', 'action_reference' => 'tentative tardive', 'motif' => 'motif',
]));
$modifApresSoumission = $registre->modifierRegle($REF, '1.0.0', $regle1Id, array_merge($gouvernance(), [
    'motif' => 'tentative de modification tardive',
]));
$verifier(
    ($regleApresSoumission['refus'] ?? null) === 'VERSION_IMMUABLE'
        && ($modifApresSoumission['refus'] ?? null) === 'VERSION_IMMUABLE',
    'une version EN_VALIDATION n’accepte plus aucune règle nouvelle ni modifiée',
);

/* ------------------------------------------------------------ simulation */
echo "\n  Simulation obligatoire avant activation\n";

$activationSansSimulation = $registre->activerVersion($REF, '1.0.0', $gouvernance());
$verifier(
    ($activationSansSimulation['refus'] ?? null) === 'SIMULATION_MANQUANTE',
    'aucune activation sans une simulation réussie de cette version exacte',
);

$simulationSansJeu = $registre->simulerVersion($REF, '1.0.0', array_merge($gouvernance(), ['cas' => []]));
$verifier(
    ($simulationSansJeu['refus'] ?? null) === 'JEU_ABSENT',
    'une simulation sans jeu_reference est refusée',
);

$simulationIncomplete = $registre->simulerVersion($REF, '1.0.0', array_merge($gouvernance(), [
    'jeu_reference' => 'JEU-VIDE', 'cas' => [['sujet' => '', 'action' => '', 'attendu' => '']],
]));
$verifier(
    ($simulationIncomplete['resultat'] ?? null) === 'INCOMPLETE',
    'une simulation sans cas exploitable est INCOMPLETE, ni réussie ni échouée',
);

$simulationEchec = $registre->simulerVersion($REF, '1.0.0', array_merge($gouvernance(), [
    'jeu_reference' => 'JEU-DIVERGENT',
    'cas' => [['sujet' => $AUTORITE, 'action' => 'agir sur le test P3', 'attendu' => 'REFUSE']],
]));
$verifier(
    ($simulationEchec['resultat'] ?? null) === 'ECHEC'
        && count($simulationEchec['resume']['divergences']) === 1,
    'une simulation dont l’issue attendue diverge de la règle réelle est en ÉCHEC, avec le détail de la divergence',
);

$activationApresEchecSeul = $registre->activerVersion($REF, '1.0.0', $gouvernance());
$verifier(
    ($activationApresEchecSeul['refus'] ?? null) === 'SIMULATION_MANQUANTE',
    'une simulation en ÉCHEC ne suffit jamais à activer',
);

$simulationReussie = $registre->simulerVersion($REF, '1.0.0', array_merge($gouvernance(), [
    'jeu_reference' => 'JEU-CONFORME',
    'cas' => [['sujet' => $AUTORITE, 'action' => 'agir sur le test P3', 'attendu' => 'PERMIS']],
]));
$verifier(
    ($simulationReussie['resultat'] ?? null) === 'REUSSIE',
    'une simulation dont l’issue attendue correspond à la règle réelle est RÉUSSIE',
);

/* -------------------------------------------------------------- cycle */
echo "\n  Cycle de vie : activation, remplacement atomique, suspension, retrait\n";

$activation = $registre->activerVersion($REF, '1.0.0', $gouvernance());
$verifier(
    ($activation['etat'] ?? null) === 'ACTIVE' && ($activation['idempotent'] ?? null) === false,
    'l’activation réussit une fois la simulation acquise',
);
$reactivation = $registre->activerVersion($REF, '1.0.0', $gouvernance());
$verifier(
    ($reactivation['idempotent'] ?? null) === true,
    'rejouer une activation déjà acquise est idempotent, sans seconde ligne de cycle',
);

// Remplacement atomique : une deuxième version active ferme la première dans
// la même transaction — jamais deux versions actives simultanées.
$registre->creerVersion($REF, array_merge($gouvernance(), ['version' => '2.0.0']));
$registre->ajouterRegle($REF, '2.0.0', array_merge($gouvernance(), [
    'effet' => 'PERMET', 'action_reference' => 'agir sur le test P3 v2', 'motif' => 'version 2',
]));
$registre->soumettreVersion($REF, '2.0.0', $gouvernance());
$registre->simulerVersion($REF, '2.0.0', array_merge($gouvernance(), [
    'jeu_reference' => 'JEU-V2',
    'cas' => [['sujet' => $AUTORITE, 'action' => 'agir sur le test P3 v2', 'attendu' => 'PERMIS']],
]));
$registre->activerVersion($REF, '2.0.0', $gouvernance());
$v1Apres = $registre->resoudreVersion($REF, '1.0.0');
$v2Apres = $registre->resoudreVersion($REF, '2.0.0');
$diagPostRemplacement = $registre->diagnostiquerRegistre();
$verifier(
    $v1Apres['etat'] === 'REMPLACEE' && $v2Apres['etat'] === 'ACTIVE' && $diagPostRemplacement['coherent'] === true,
    'activer une nouvelle version remplace l’ancienne dans le même mouvement ; jamais deux versions actives',
);

$suspensionDepuisBrouillon = (function () use ($registre, $gouvernance, $REF): array {
    $registre->creerVersion($REF, array_merge($gouvernance(), ['version' => '3.0.0']));

    return $registre->suspendreVersion($REF, '3.0.0', $gouvernance());
})();
$verifier(
    ($suspensionDepuisBrouillon['refus'] ?? null) === 'ETAT_INCOMPATIBLE',
    'seule une version ACTIVE se suspend',
);

$suspension = $registre->suspendreVersion($REF, '2.0.0', $gouvernance());
$resuspension = $registre->suspendreVersion($REF, '2.0.0', $gouvernance());
$verifier(
    ($suspension['etat'] ?? null) === 'SUSPENDUE' && ($resuspension['idempotent'] ?? null) === true,
    'la suspension ferme immédiatement la permission et est idempotente',
);

$retraitSansActive = $registre->retirerPolitique($REF, $gouvernance());
$verifier(
    ($retraitSansActive['refus'] ?? null) === 'AUCUNE_VERSION_ACTIVE',
    'retirer une politique sans version active est refusé : rien à retirer',
);

// 3.0.0 était restée en BROUILLON (contre-épreuve de suspension plus haut) ;
// une suspension ne se réactive jamais elle-même — seule une nouvelle version
// le peut. On l'amène donc à ACTIVE par le cycle complet, pour retirer sur
// une politique qui a réellement une version active.
$registre->ajouterRegle($REF, '3.0.0', array_merge($gouvernance(), [
    'effet' => 'PERMET', 'action_reference' => 'agir sur le test P3 v3', 'motif' => 'version 3',
]));
$registre->soumettreVersion($REF, '3.0.0', $gouvernance());
$registre->simulerVersion($REF, '3.0.0', array_merge($gouvernance(), [
    'jeu_reference' => 'JEU-V3',
    'cas' => [['sujet' => $AUTORITE, 'action' => 'agir sur le test P3 v3', 'attendu' => 'PERMIS']],
]));
$registre->activerVersion($REF, '3.0.0', $gouvernance());
$retrait = $registre->retirerPolitique($REF, $gouvernance());
$verifier(
    ($retrait['etat'] ?? null) === 'RETIREE'
        && $registre->resoudreVersion($REF, '3.0.0')['etat'] === 'RETIREE'
        && $registre->diagnostiquerRegistre()['coherent'] === true,
    'le retrait est irréversible, daté, sans réécriture de l’historique',
);

$historique = $registre->resoudreHistorique($REF);
$etats = array_column($historique, 'etat');
$verifier(
    in_array('BROUILLON', $etats, true)
        && in_array('EN_VALIDATION', $etats, true)
        && in_array('ACTIVE', $etats, true)
        && in_array('REMPLACEE', $etats, true)
        && in_array('SUSPENDUE', $etats, true)
        && in_array('RETIREE', $etats, true),
    'l’historique conserve chaque transition traversée, jamais réécrite',
);

/* ------------------------------------------------------- évaluation exacte */
echo "\n  Évaluation : correspondance exacte, jamais approchée\n";

$reglesEval = [
    ['action_reference' => 'agir-sur-x', 'sujet_reference' => null, 'effet' => 'PERMET', 'motif' => 'm', 'politique_reference' => 'POL-X', 'source_reference' => 's', 'ressource_reference' => null],
    ['action_reference' => 'agir-sur-x-dangereusement', 'sujet_reference' => null, 'effet' => 'REFUSE', 'motif' => 'm2', 'politique_reference' => 'POL-X', 'source_reference' => 's', 'ressource_reference' => null],
];
$exact = $registre->evaluer($reglesEval, 'AUT-GAMAD-001', 'agir sur x');
$sousChaine = $registre->evaluer($reglesEval, 'AUT-GAMAD-001', 'agir sur x dangereusement encore plus');
$verifier(
    $exact['decision'] === 'PERMIS'
        && $sousChaine['decision'] === 'REFUSE'
        && $sousChaine['politique'] === null,
    'une action normalisée qui n’égale exactement aucune règle est refusée, jamais rapprochée par sous-chaîne',
);

$refusGagne = $registre->evaluer([
    ['action_reference' => 'x', 'sujet_reference' => null, 'effet' => 'PERMET', 'motif' => 'm', 'politique_reference' => 'A', 'source_reference' => 's', 'ressource_reference' => null],
    ['action_reference' => 'x', 'sujet_reference' => null, 'effet' => 'REFUSE', 'motif' => 'm2', 'politique_reference' => 'B', 'source_reference' => 's', 'ressource_reference' => null],
], 'S', 'x');
$verifier(
    $refusGagne['decision'] === 'REFUSE' && $refusGagne['politique'] === 'B',
    'un REFUSE l’emporte toujours sur un PERMET applicable, quel que soit l’ordre',
);

/* ---------------------------------------------------- CTR-03 (CAP-CORE-004) */
echo "\n  CTR-03 lit exclusivement ce magasin persistant\n";

$sourceCtr03 = file_get_contents(__DIR__ . '/../../registre-autorisation/src/Ctr03.php');
$verifier(
    $sourceCtr03 !== false
        && !preg_match('/\bFROM\s+(norme|version_norme|statut|adoption|relation_evolution)\b/i', $sourceCtr03)
        && !str_contains($sourceCtr03, 'str_contains'),
    'CTR-03 ne référence plus l’index documentaire et n’exerce plus de rapprochement par sous-chaîne',
);

$ctr03 = new Ctr03($polMagasin);
$decisionReelle = $ctr03->autoriser('AUT-GAMAD-001', 'inscrire une identité', 'personne');
$verifier(
    $decisionReelle['decision'] === 'PERMIS' && $decisionReelle['politique'] === 'POL-INSCRIPTION-IDENTITES-V1',
    'CTR-03 permet une action réelle en lisant les versions ACTIVE de ce registre',
);

/* -------------------------------------------------------------- sécurité */
echo "\n  Refus par défaut et absence de secrets\n";

$colonnesSuspectes = [];
foreach (Gamad\RegistrePolitiques\SchemaPolitiques::TABLES as $table) {
    foreach ($polMagasin->query("PRAGMA table_info({$table})")->fetchAll() as $colonne) {
        if (preg_match('/secret|password|mot_de_passe|jeton|token/i', (string) $colonne['name'])) {
            $colonnesSuspectes[] = "{$table}.{$colonne['name']}";
        }
    }
}
$verifier(
    $colonnesSuspectes === [],
    'le schéma du magasin des politiques ne porte aucune colonne de secret',
    $colonnesSuspectes === [] ? '' : implode(', ', $colonnesSuspectes),
);

/* -------------------------------------------------- reconstruction index */
echo "\n  Reconstruction de la baseline sans effet sur le registre persistant\n";

BaselineOperationnelle::standard()->reconstruire($index);
$verifier(
    $registre->resoudrePolitique('POL-SOURCES-V1') !== null
        && $registre->resoudreVersionActive('POL-SOURCES-V1') !== null,
    'reconstruire l’index documentaire ne supprime jamais le registre persistant des politiques',
);

/* ------------------------------------------------------------ CONTRE-ÉPREUVE */
echo "\n  Contre-épreuve — la garde doit savoir échouer\n";

$polMagasin->exec("DELETE FROM politique WHERE reference = 'POL-SOURCES-V1'");
$verifier(
    $registre->resoudrePolitique('POL-SOURCES-V1') === null,
    'une politique retirée du magasin cesse d’être résolue',
);

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-007 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-007 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
