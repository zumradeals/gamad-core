<?php

declare(strict_types=1);

/**
 * Garde de comportement du noyau de CAP-CORE-016 — secrets & clés.
 *
 * Éprouve directement `RegistreSecretsCles`, les fournisseurs bornés et
 * `ResolveurSecret`, sans jamais faire circuler de valeur secrète réelle :
 * les fichiers factices créés ici contiennent des chaînes synthétiques
 * clairement identifiées comme telles, jamais un secret de production.
 *
 * Portée assumée : ce registre stocke la gouvernance (références, versions,
 * usages, rotations, compromissions), jamais le matériel. La vérification
 * croisée du propriétaire/de la source/du realm contre CAP-CORE-001/006/012
 * n'est pas câblée dans ce chantier — limite documentée dans la fiche
 * finale, comme pour d'autres registres qui acceptent des dépendances
 * facultatives non exercées faute de besoin bloquant.
 *
 * Exécution : php core/registre-secrets-cles/tests/secrets_cles_p3.php
 */

use Gamad\RegistreSecretsCles\DescripteurVersion;
use Gamad\RegistreSecretsCles\ExceptionSecret;
use Gamad\RegistreSecretsCles\FournisseurCredentialSystemd;
use Gamad\RegistreSecretsCles\FournisseurEnvironnementTransition;
use Gamad\RegistreSecretsCles\FournisseurFichier0600;
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;
use Gamad\RegistreSecretsCles\ResolveurSecret;
use Gamad\RegistreSecretsCles\SchemaSecretsCles;
use Gamad\RegistreSecretsCles\SensitiveValue;
use Gamad\RegistreSecretsCles\UsageSecret;

require __DIR__ . '/../src/ExceptionSecret.php';
require __DIR__ . '/../src/PolitiqueSecretsCles.php';
require __DIR__ . '/../src/SchemaSecretsCles.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/RegistreSecretsCles.php';
require __DIR__ . '/../src/DescripteurVersion.php';
require __DIR__ . '/../src/UsageSecret.php';
require __DIR__ . '/../src/DiagnosticFournisseur.php';
require __DIR__ . '/../src/ResultatDestruction.php';
require __DIR__ . '/../src/SensitiveValue.php';
require __DIR__ . '/../src/FournisseurSecret.php';
require __DIR__ . '/../src/FournisseurFichier0600.php';
require __DIR__ . '/../src/FournisseurCredentialSystemd.php';
require __DIR__ . '/../src/FournisseurEnvironnementTransition.php';
require __DIR__ . '/../src/ResolveurSecret.php';

echo "GARDE — CAP-CORE-016 (secrets & clés)\n\n";

$echecs = 0;
$numero = 0;
$v = static function (bool $ok, string $libelle) use (&$echecs, &$numero): void {
    $numero++;
    printf("  %s  %2d. %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $numero, $libelle);
    if (!$ok) {
        $echecs++;
    }
};

$prefixe = sys_get_temp_dir() . '/gamad-secrets-p3-' . getmypid();
$fichier = $prefixe . '-registre.sqlite';
@unlink($fichier);
$dossierSecrets = $prefixe . '-fichiers';
@mkdir($dossierSecrets, 0700, true);
register_shutdown_function(static function () use ($fichier, $dossierSecrets): void {
    @unlink($fichier);
    foreach (glob($dossierSecrets . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dossierSecrets);
});

$magasin = SecretsMagasin::connecter($fichier);
$registre = new RegistreSecretsCles($magasin);

$g = static fn (): array => [
    'politique' => PolitiqueSecretsCles::POLITIQUE, 'producteur' => PolitiqueSecretsCles::AUTORITE,
    'preuve' => 'P3-' . strtoupper(bin2hex(random_bytes(4))),
];

// ------------------------------------------------------------------ 1-3 migration/moteur
$v(SchemaSecretsCles::presente($magasin), 'migration du magasin établit les dix tables de gouvernance');
SchemaSecretsCles::migrer($magasin);
$v(SchemaSecretsCles::presente($magasin), 'rejouer la migration ne casse rien (idempotente)');
$v(
    (string) $magasin->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite',
    'SQLite reste isolé aux tests et au développement (moteur observé ici)',
);

// ------------------------------------------------------------------ 7-9 ressource
$dossierRessource = array_merge($g(), [
    'reference' => 'SEC-GAMAD-TEST-CLE', 'nom' => 'Clé de test P3', 'type_secret' => 'CLE_CHIFFREMENT_SYMETRIQUE',
    'finalite_reference' => 'chiffrer un lot de test', 'proprietaire_reference' => 'AUT-GAMAD-001',
    'source_reference' => 'SRC-GAMAD-P3', 'environnement_reference' => 'CI', 'classification_reference' => 'INTERNE',
]);
$ressource = $registre->inscrireSecret($dossierRessource);
$v(!isset($ressource['refus']) && $ressource['reference'] === 'SEC-GAMAD-TEST-CLE', 'référence unique acceptée à l\'inscription');
$v(
    isset($registre->inscrireSecret($dossierRessource)['refus']),
    'une référence déjà utilisée est refusée, jamais réattribuée',
);
$v(
    isset($registre->inscrireSecret(array_merge($dossierRessource, ['reference' => 'SEC-GAMAD-TEST-TYPE-INCONNU', 'type_secret' => 'TYPE_INEXISTANT']))['refus']),
    'type hors liste close refusé',
);

// colonnes interdites
$v(
    isset($registre->inscrireSecret(array_merge($dossierRessource, ['reference' => 'SEC-GAMAD-TEST-VALEUR', 'password' => 'ceci-ne-doit-jamais-etre-ecrit']))['refus']),
    'un dossier portant un champ `password` est refusé avant toute écriture',
);
$refusChamp = $registre->inscrireSecret(array_merge($dossierRessource, ['reference' => 'SEC-GAMAD-TEST-VALEUR2', 'secret' => 'x']));
$v(($refusChamp['refus'] ?? null) === 'CHAMP_INTERDIT', 'le refus de champ interdit porte un code stable (CHAMP_INTERDIT)');

// colonnes de la table elle-même : preuve statique qu'aucune colonne de valeur n'existe
$colonnes = $magasin->query("PRAGMA table_info(secret_ressource)")->fetchAll(PDO::FETCH_ASSOC);
$nomsColonnes = array_map(static fn (array $c): string => strtolower((string) $c['name']), $colonnes);
$v(
    array_intersect($nomsColonnes, PolitiqueSecretsCles::CHAMPS_INTERDITS) === [],
    'aucune colonne de `secret_ressource` ne porte un nom de champ interdit',
);
$colonnesVersion = $magasin->query("PRAGMA table_info(secret_version)")->fetchAll(PDO::FETCH_ASSOC);
$nomsColonnesVersion = array_map(static fn (array $c): string => strtolower((string) $c['name']), $colonnesVersion);
$v(
    array_intersect($nomsColonnesVersion, PolitiqueSecretsCles::CHAMPS_INTERDITS) === [],
    'aucune colonne de `secret_version` ne porte un nom de champ interdit',
);

// ------------------------------------------------------------------ fournisseur fichier pilote
$cheminFichier = $dossierSecrets . '/secret-test.txt';
file_put_contents($cheminFichier, "valeur-factice-p3-jamais-un-vrai-secret\n");
chmod($cheminFichier, 0600);

$fournisseurDossier = array_merge($g(), [
    'reference' => 'FOU-GAMAD-TEST-FICHIER', 'nom' => 'Fournisseur fichier P3', 'type_fournisseur' => 'FICHIER_0600',
    'environnement_reference' => 'CI', 'proprietaire_reference' => 'AUT-GAMAD-001', 'capacites' => ['LIRE'],
]);
$fournisseur = $registre->inscrireFournisseur($fournisseurDossier);
$v(!isset($fournisseur['refus']) && $fournisseur['etat'] === 'PREPARATION', 'fournisseur inscrit en PREPARATION');

$adaptateurFichier = new FournisseurFichier0600('', 0);
$verif = $registre->verifierFournisseur('FOU-GAMAD-TEST-FICHIER', $adaptateurFichier, ['handle_sonde' => $cheminFichier]);
$v($verif['etat'] === 'ACTIF', 'fournisseur fichier 0600 valide passe ACTIF après vérification');

chmod($cheminFichier, 0644);
$diagnosticTropOuvert = $adaptateurFichier->verifierDisponibilite(new DescripteurVersion('SEC-GAMAD-TEST-CLE', '1', $cheminFichier));
$v(!$diagnosticTropOuvert->disponible, 'un fichier trop permissif (0644) est refusé');
chmod($cheminFichier, 0600);

$lien = $dossierSecrets . '/lien-secret.txt';
@symlink($cheminFichier, $lien);
$diagnosticLien = $adaptateurFichier->verifierDisponibilite(new DescripteurVersion('SEC-GAMAD-TEST-CLE', '1', $lien));
$v(!$diagnosticLien->disponible, 'un lien symbolique est refusé par défaut');
@unlink($lien);

$fichierVolumineux = $dossierSecrets . '/trop-gros.txt';
file_put_contents($fichierVolumineux, str_repeat('a', 100_000));
chmod($fichierVolumineux, 0600);
$diagnosticVolumineux = $adaptateurFichier->verifierDisponibilite(new DescripteurVersion('SEC-GAMAD-TEST-CLE', '1', $fichierVolumineux));
$v(!$diagnosticVolumineux->disponible, 'un fichier trop volumineux est refusé');
@unlink($fichierVolumineux);

// credential systemd (répertoire simulé) + fallback silencieux refusé
$dossierCredentials = $dossierSecrets . '/credentials';
@mkdir($dossierCredentials, 0700, true);
file_put_contents($dossierCredentials . '/SEC_TEST', "valeur-factice-credential\n");
$adaptateurSystemd = new FournisseurCredentialSystemd($dossierCredentials);
$diagCredential = $adaptateurSystemd->verifierDisponibilite(new DescripteurVersion('SEC-GAMAD-TEST-CLE', '1', 'SEC_TEST'));
$v($diagCredential->disponible, 'credential systemd présent et borné en taille est valide');
$adaptateurSystemdVide = new FournisseurCredentialSystemd(null);
$diagCredentialVide = $adaptateurSystemdVide->verifierDisponibilite(new DescripteurVersion('SEC-GAMAD-TEST-CLE', '1', 'SEC_TEST'));
$v(!$diagCredentialVide->disponible, 'sans CREDENTIALS_DIRECTORY, le fournisseur systemd ferme au lieu de retomber ailleurs');

putenv('GAMAD_SECRET_TRANSITION_P3=valeur-factice-transition');
$adaptateurTransition = new FournisseurEnvironnementTransition();
$diagTransition = $adaptateurTransition->verifierDisponibilite(new DescripteurVersion('SEC-GAMAD-TEST-CLE', '1', 'GAMAD_SECRET_TRANSITION_P3'));
$v($diagTransition->disponible, 'variable de transition explicitement déclarée est acceptée');
$diagTransitionAbsente = $adaptateurTransition->verifierDisponibilite(new DescripteurVersion('SEC-GAMAD-TEST-CLE', '1', 'GAMAD_VARIABLE_JAMAIS_DEFINIE_P3'));
$v(!$diagTransitionAbsente->disponible, 'une variable de transition non définie est refusée, jamais un fallback silencieux');

// ------------------------------------------------------------------ versions
$versionDossier = array_merge($g(), [
    'secret_reference' => 'SEC-GAMAD-TEST-CLE', 'version' => '1', 'fournisseur_reference' => 'FOU-GAMAD-TEST-FICHIER',
    'handle_fournisseur' => $cheminFichier,
]);
$version1 = $registre->declarerVersion($versionDossier);
$v(!isset($version1['refus']) && $version1['etat'] === 'PREPARATION', 'version déclarée en PREPARATION, sans valeur');
$v(
    isset($registre->declarerVersion($versionDossier)['refus']),
    'la même version ne peut pas être déclarée deux fois',
);
$refusClePrivee = $registre->declarerVersion(array_merge($versionDossier, [
    'version' => '1-privee', 'cle_publique' => "-----BEGIN PRIVATE KEY-----\nfactice\n-----END PRIVATE KEY-----",
]));
$v(($refusClePrivee['refus'] ?? null) === 'CLE_PRIVEE_REFUSEE', 'une clé privée soumise comme "clé publique" est refusée');

$id1 = (int) $version1['id'];
$verifVersion = $registre->verifierVersion($id1, $adaptateurFichier, $g());
$v(($verifVersion['disponible'] ?? false) === true, 'la vérification de version confirme la disponibilité sans exposer le matériel');
$refusActivationSansVerif = $registre->activerVersion($id1, array_merge($g(), []));
$v(isset($refusActivationSansVerif['refus']), 'activation sans vérification préalable refusée');

$activation1 = $registre->activerVersion($id1, array_merge($g(), ['verifiee' => true]));
$v(!isset($activation1['refus']) && $activation1['etat'] === 'ACTIVE_ECRITURE', 'activation réussie après vérification');
$v($registre->etatVersion($id1) === 'ACTIVE_ECRITURE', 'une seule version active en écriture est bien celle attendue');

$dependance = $registre->declarerDependance([
    'secret_reference' => 'SEC-GAMAD-TEST-CLE', 'secret_version_id' => $id1,
    'type_dependance' => 'SAUVEGARDE', 'ressource_reference' => 'LOT-P3-TEST',
]);
$v(!isset($dependance['refus']), 'dépendance historique déclarée sur la version 1');

// deuxième version : bascule
$version2 = $registre->declarerVersion(array_merge($versionDossier, ['version' => '2']));
$id2 = (int) $version2['id'];
$registre->verifierVersion($id2, $adaptateurFichier, $g());
$activation2 = $registre->activerVersion($id2, array_merge($g(), ['verifiee' => true]));
$v($activation2['etat'] === 'ACTIVE_ECRITURE', 'rotation : la nouvelle version devient ACTIVE_ECRITURE');
$v($registre->etatVersion($id1) === 'ACTIVE_LECTURE', 'l\'ancienne version bascule automatiquement en ACTIVE_LECTURE');
$versionsLecture = $registre->resoudreVersionsLecture('SEC-GAMAD-TEST-CLE');
$v(
    in_array($id1, array_map(static fn (array $ligne): int => (int) $ligne['id'], $versionsLecture), true),
    'la version dépréciée en lecture reste résolvable pour déchiffrer l\'historique',
);
$v(
    $registre->resoudreVersionActiveEcriture('SEC-GAMAD-TEST-CLE')['id'] === $id2,
    'resoudreVersionActiveEcriture() ne retourne jamais deux versions à la fois',
);

// suspension / révocation
$suspension = $registre->suspendreVersion($id2, array_merge($g(), ['motif' => 'exercice P3']));
$v($suspension['etat'] === 'SUSPENDUE', 'suspension immédiate, sans destruction');
$refusReactivation = $registre->activerVersion($id2, array_merge($g(), ['verifiee' => true]));
$v(isset($refusReactivation['refus']), 'une version suspendue ne redevient pas active par un simple réappel');

$revocation = $registre->revoquerVersion($id2, array_merge($g(), ['motif' => 'fin de vie planifiée']));
$v($revocation['etat'] === 'REVOQUEE', 'révocation appliquée depuis SUSPENDUE');
$v(
    isset($registre->activerVersion($id2, array_merge($g(), ['verifiee' => true]))['refus']),
    'une version révoquée ne redevient jamais active',
);

// ------------------------------------------------------------------ usages
$usageInvalideSansConsommateur = $registre->declarerUsage(array_merge($g(), [
    'secret_reference' => 'SEC-GAMAD-TEST-CLE', 'environnement_reference' => 'CI',
    'operation_reference' => 'op.test', 'finalite_reference' => 'test P3', 'mode_usage' => 'CHIFFRER',
]));
$v(isset($usageInvalideSansConsommateur['refus']), 'usage sans consommateur (capacité ou produit) refusé');

$usageValide = $registre->declarerUsage(array_merge($g(), [
    'secret_reference' => 'SEC-GAMAD-TEST-CLE', 'capacite_reference' => 'CAP-CORE-019',
    'environnement_reference' => 'CI', 'operation_reference' => 'op.chiffrer-sauvegarde',
    'finalite_reference' => 'chiffrer un lot de sauvegarde de test', 'mode_usage' => 'CHIFFRER',
]));
$v(!isset($usageValide['refus']), 'usage complet avec consommateur, finalité et opération accepté');

$usageWildcard = $registre->declarerUsage(array_merge($g(), [
    'secret_reference' => 'SEC-GAMAD-TEST-CLE', 'capacite_reference' => 'CAP-CORE-019',
    'environnement_reference' => 'CI', 'operation_reference' => '*',
    'finalite_reference' => 'test', 'mode_usage' => 'CHIFFRER',
]));
$v(isset($usageWildcard['refus']), 'un joker universel `*` est refusé dans un usage');

$usageEnvInconnu = $registre->declarerUsage(array_merge($g(), [
    'secret_reference' => 'SEC-GAMAD-TEST-CLE', 'capacite_reference' => 'CAP-CORE-019',
    'environnement_reference' => 'ENVIRONNEMENT_INEXISTANT', 'operation_reference' => 'op.test',
    'finalite_reference' => 'test', 'mode_usage' => 'CHIFFRER',
]));
$v(isset($usageEnvInconnu['refus']), 'usage sur un environnement hors liste close refusé');

// ------------------------------------------------------------------ compromission
$version3 = $registre->declarerVersion(array_merge($versionDossier, ['version' => '3']));
$id3 = (int) $version3['id'];
$registre->verifierVersion($id3, $adaptateurFichier, $g());
$registre->activerVersion($id3, array_merge($g(), ['verifiee' => true]));
$v($registre->etatVersion($id3) === 'ACTIVE_ECRITURE', 'version 3 active avant l\'exercice de compromission');

$compromission = $registre->declarerCompromission(array_merge($g(), [
    'secret_version_id' => $id3, 'niveau' => 'CONFIRMEE', 'source_reference' => 'SRC-GAMAD-P3',
    'motif' => 'exercice de compromission P3 — valeur factice',
]));
$v(!isset($compromission['refus']) && $compromission['etat'] === 'OUVERTE', 'compromission déclarée, ouverte');
$v($registre->etatVersion($id3) === 'COMPROMISE', 'la déclaration bloque immédiatement la version, sans étape intermédiaire');
$v(
    isset($registre->activerVersion($id3, array_merge($g(), ['verifiee' => true]))['refus']),
    'une version compromise ne redevient jamais active',
);
$compromissionJson = json_encode($compromission, JSON_UNESCAPED_UNICODE);
$v(
    !str_contains(strtolower((string) $compromissionJson), 'valeur-factice'),
    'la réponse de compromission ne contient aucune valeur de secret',
);

// ------------------------------------------------------------------ destruction
$refusDestructionActive = $registre->detruireVersion($id1, $adaptateurFichier, array_merge($g(), ['confirmation_renforcee' => true]));
// id1 est ACTIVE_LECTURE, doit être refusé
$v(isset($refusDestructionActive['refus']), 'une version encore active en lecture ne peut pas être détruite');

$version4 = $registre->declarerVersion(array_merge($versionDossier, ['version' => '4']));
$id4 = (int) $version4['id'];
$registre->verifierVersion($id4, $adaptateurFichier, $g());
$registre->activerVersion($id4, array_merge($g(), ['verifiee' => true]));
// id2 est REVOQUEE, sans dépendance : destructible
$refusSansConfirmation = $registre->detruireVersion($id2, $adaptateurFichier, $g());
$v(isset($refusSansConfirmation['refus']), 'destruction sans confirmation renforcée refusée');

$dependanceBloquante = $registre->declarerDependance([
    'secret_reference' => 'SEC-GAMAD-TEST-CLE', 'secret_version_id' => $id2,
    'type_dependance' => 'SAUVEGARDE', 'ressource_reference' => 'LOT-P3-BLOQUANT',
]);
$refusDependance = $registre->detruireVersion($id2, $adaptateurFichier, array_merge($g(), ['confirmation_renforcee' => true]));
$v(($refusDependance['refus'] ?? null) === 'DEPENDANCE_BLOQUANTE', 'une dépendance non expirée empêche la destruction');

$idDependance = (int) $magasin->query(
    "SELECT id FROM secret_dependance WHERE ressource_reference = 'LOT-P3-BLOQUANT'"
)->fetchColumn();
$registre->fermerDependance($idDependance, 'lot expiré, exercice P3');
$destructionAutorisee = $registre->detruireVersion($id2, $adaptateurFichier, array_merge($g(), ['confirmation_renforcee' => true]));
$v(!isset($destructionAutorisee['refus']) && $destructionAutorisee['etat'] === 'DETRUITE', 'destruction confirmée après fermeture de la dépendance');
$v(
    isset($registre->detruireVersion($id2, $adaptateurFichier, array_merge($g(), ['confirmation_renforcee' => true]))['refus']),
    'une version déjà détruite ne peut pas être détruite à nouveau',
);

// ------------------------------------------------------------------ rotation
$planDossier = array_merge($g(), [
    'secret_reference' => 'SEC-GAMAD-TEST-CLE', 'strategie' => 'DOUBLE_LECTURE_ECRITURE_NOUVELLE',
    'date_prevue' => gmdate('c'), 'ancienne_version_id' => $id3, 'nouvelle_version_id' => $id4,
    'retour_arriere_autorise' => true, 'impact' => ['consommateurs' => ['CAP-CORE-019']],
    'etapes' => ['preparer', 'basculer', 'nettoyer'],
]);
$refusPlanSansImpact = $registre->planifierRotation(array_merge($planDossier, ['impact' => []]));
$v(isset($refusPlanSansImpact['refus']), 'plan de rotation sans consommateurs impactés refusé');

$plan = $registre->planifierRotation($planDossier);
$v(!isset($plan['refus']) && $plan['etat'] === 'BROUILLON', 'plan de rotation créé en BROUILLON');
$refusExecutionAvantValidation = $registre->executerEtapeRotation($plan['reference'], 'preparer', array_merge($g(), ['reussie' => true]));
$v(isset($refusExecutionAvantValidation['refus']), 'exécution refusée avant validation du plan');

$validation = $registre->validerRotation($plan['reference'], $g());
$v($validation['etat'] === 'VALIDE', 'validation explicite avant exécution');

$etape1 = $registre->executerEtapeRotation($plan['reference'], 'preparer', array_merge($g(), ['reussie' => true]));
$v($etape1['etat'] === 'REUSSIE', 'première étape exécutée avec succès');
$etape1Rejouee = $registre->executerEtapeRotation($plan['reference'], 'preparer', array_merge($g(), ['reussie' => true]));
$v(($etape1Rejouee['idempotent'] ?? false) === true, 'une étape déjà réussie n\'est pas rejouée (idempotence)');

$etapeEchouee = $registre->executerEtapeRotation($plan['reference'], 'basculer', array_merge($g(), ['reussie' => false]));
$v($etapeEchouee['etat'] === 'ECHOUEE', 'un échec d\'étape est enregistré sans faire disparaître le plan');
$v($registre->resoudreRotation($plan['reference'])['etat'] === 'EN_COURS', 'un échec d\'étape ne clôture pas le plan tout seul, laissant place à une reprise');

$etapeReussie = $registre->executerEtapeRotation($plan['reference'], 'basculer', array_merge($g(), ['reussie' => true]));
$v($etapeReussie['etat'] === 'REUSSIE', 'reprise réussie après échec, ancienne version conservée entre-temps');
$cloture = $registre->cloturerRotationReussie($plan['reference']);
$v($cloture['etat'] === 'REUSSI', 'clôture explicite du plan en succès');

// ------------------------------------------------------------------ diagnostic
$diagnostic = $registre->diagnostiquerRegistre();
$v($diagnostic['coherent'] === true, 'diagnostiquerRegistre() rapporte un état cohérent après l\'exercice complet');
$v($diagnostic['versions_compromises_actives'] === 1, 'le diagnostic rapporte exactement la version 3, compromise pendant l\'exercice — visible, pas masquée');
$v($diagnostic['doublons_ecriture'] === 0, 'aucun doublon de version active en écriture par secret (la compromise n\'entre pas en concurrence avec la 4)');
$diagnosticJson = json_encode($diagnostic, JSON_UNESCAPED_UNICODE);
$v(!str_contains(strtolower((string) $diagnosticJson), 'valeur-factice'), 'le diagnostic ne contient aucune valeur');

// ------------------------------------------------------------------ résolveur borné
$resolveur = new ResolveurSecret($registre, ['FOU-GAMAD-TEST-FICHIER' => $adaptateurFichier]);
$usageContexte = new UsageSecret(
    modeUsage: 'DECHIFFRER', consommateurReference: 'CAP-CORE-019', realmReference: '',
    environnementReference: 'CI', finaliteReference: 'lecture de test', operationReference: 'op.test',
    correlationId: 'P3-RESOLVEUR',
);
$longueurLue = $resolveur->avecSecret('SEC-GAMAD-TEST-CLE', $usageContexte, static function (SensitiveValue $valeur): int {
    return $valeur->longueur();
});
$v($longueurLue > 0, 'la résolution bornée exécute l\'opération sans jamais retourner la valeur à l\'appelant');

$exceptionInconnu = null;
try {
    $resolveur->avecSecret('SEC-GAMAD-INCONNU', $usageContexte, static fn (SensitiveValue $v) => $v->longueur());
} catch (ExceptionSecret $e) {
    $exceptionInconnu = $e;
}
$v(
    $exceptionInconnu !== null && str_starts_with($exceptionInconnu->getMessage(), 'SECRET_INCONNU'),
    'la résolution d\'un secret inconnu est refusée fermé, avec un code stable',
);

$usageEnvDifferent = new UsageSecret(
    modeUsage: 'DECHIFFRER', consommateurReference: 'CAP-CORE-019', realmReference: '',
    environnementReference: 'PRODUCTION', finaliteReference: 'lecture de test', operationReference: 'op.test',
    correlationId: 'P3-RESOLVEUR-ENV',
);
$exceptionEnv = null;
try {
    $resolveur->avecSecret('SEC-GAMAD-TEST-CLE', $usageEnvDifferent, static fn (SensitiveValue $v) => $v->longueur());
} catch (ExceptionSecret $e) {
    $exceptionEnv = $e;
}
$v(
    $exceptionEnv !== null && str_starts_with($exceptionEnv->getMessage(), 'ENVIRONNEMENT_REFUSE'),
    'la résolution refuse un contexte dont l\'environnement diffère de celui du secret',
);

// ------------------------------------------------------------------ contre-épreuve : la garde sait échouer
$fauxPositif = $registre->inscrireSecret(array_merge($dossierRessource, ['reference' => 'SEC-GAMAD-TEST-CLE']));
$v(isset($fauxPositif['refus']), 'contre-épreuve : réinscrire une référence déjà prise échoue bien (la garde n\'est pas toujours verte)');

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-016 (noyau) : ÉTABLIE.\n";
    exit(0);
}

echo "Garde CAP-CORE-016 (noyau) : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
