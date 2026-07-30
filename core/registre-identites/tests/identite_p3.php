<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-001 — corpus historique + registre persistant.
 *
 * Exécution : php core/registre-identites/tests/identite_p3.php
 * Code de sortie : 0 si toutes les preuves et contre-épreuves passent.
 */

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreIdentites\SchemaInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../src/PolitiqueInscription.php';
require __DIR__ . '/../src/SchemaInscription.php';
require __DIR__ . '/../src/Ctr01.php';

$indexFichier = sys_get_temp_dir() . '/regn-identite-index-' . getmypid() . '.sqlite';
$registreFichier = sys_get_temp_dir() . '/regn-identite-persistant-' . getmypid() . '.sqlite';
$partageFichier = sys_get_temp_dir() . '/regn-identite-partage-' . getmypid() . '.sqlite';
foreach ([$indexFichier, $registreFichier, $partageFichier] as $fichier) {
    @unlink($fichier);
}

putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $indexFichier);
$index = Db::connect();
(new Ingestion($index, REGN_CORPUS))->executer();

$registre = new PDO('sqlite:' . $registreFichier);
$registre->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$registre->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$ctr01 = new Ctr01($index, $registre);

$echecs = 0;
echo "PREUVE P3 — IDENTITY REGISTRY PERSISTANT (CAP-CORE-001)\n\n";

$verifier = static function (bool $ok, string $message) use (&$echecs): void {
    echo $ok ? "  [OK]    {$message}\n" : "  [ÉCHEC] {$message}\n";
    if (!$ok) {
        $echecs++;
    }
};

// 1 et 13 — appels historiques et reconstruction temporelle inchangés.
$cas = [
    ['2026-07-26', 'HISTORIQUE À QUALIFIER'],
    ['2026-07-27', 'DISSOUS — IDENTITÉ RENDUE AU CORE'],
    ['2026-08-01', 'DISSOUS — IDENTITÉ RENDUE AU CORE'],
];
foreach ($cas as [$date, $attendu]) {
    $obtenu = $ctr01->resoudreIdentite('PRD-GAMAD-001', $date)['etat'] ?? null;
    $verifier($obtenu === $attendu, "appel historique resoudreIdentite au {$date}");
}
$inventaireInitial = $ctr01->resoudreInventaire();
$verifier(count(array_filter(
    $inventaireInitial,
    static fn (array $i): bool => $i['regime'] === Ctr01::DERIVE,
)) >= 7, 'les sept identités historiques du corpus restent résolues');
$verifier(
    array_filter($ctr01->resoudreDenominations(), static fn (array $d): bool => $d['divergente']) !== [],
    'resoudreDenominations signale toujours la divergence historique',
);

// 2 — une personne peut être inscrite par un produit reconnu.
$personne = $ctr01->inscrireIdentite([
    'canal' => 'PRODUIT_RECONNU',
    'type' => 'personne',
    'libelle' => 'Personne de test P3',
    'producteur' => 'PRD-GAMAD-002',
    'politique' => 'POL-INSCRIPTION-P3',
    'source' => 'garde CAP-CORE-001',
    'preuve' => 'EVT-P3-PERSONNE-001',
    'date' => '2026-07-30',
]);
$personneRef = (string) ($personne['reference'] ?? '');
$verifier(
    str_starts_with($personneRef, 'IDN-PER-')
        && ($ctr01->resoudreIdentite($personneRef)['regime'] ?? null) === Ctr01::INSCRIT,
    'une personne reçoit une référence canonique persistante',
);

// 3 — une organisation peut être inscrite sans créer de dossier CAP-CORE-002.
$organisation = $ctr01->inscrireIdentite([
    'canal' => 'PRODUIT_RECONNU',
    'type' => 'organisation',
    'libelle' => 'Organisation de test P3',
    'producteur' => 'PRD-GAMAD-002',
    'politique' => 'POL-INSCRIPTION-P3',
    'source' => 'garde CAP-CORE-001',
    'preuve' => 'EVT-P3-ORGANISATION-001',
    'date' => '2026-07-30',
]);
$organisationRef = (string) ($organisation['reference'] ?? '');
$verifier(
    str_starts_with($organisationRef, 'IDN-ORG-')
        && ($ctr01->resoudreIdentite($organisationRef)['type'] ?? null) === 'organisation',
    'une organisation reçoit une identité canonique minimale',
);
$identiteInscrite = $ctr01->resoudreIdentite($personneRef);
$verifier(
    ($identiteInscrite['producteur'] ?? null) === 'PRD-GAMAD-002'
        && ($identiteInscrite['preuve_reference'] ?? null) === 'EVT-P3-PERSONNE-001'
        && ($identiteInscrite['canal'] ?? null) === 'PRODUIT_RECONNU'
        && ($identiteInscrite['etat'] ?? null) === 'ACTIVE'
        && ($ctr01->resoudreAssurance($personneRef)['niveau'] ?? null) === 'A1',
    'l’inscription conserve canal, producteur, preuve, état et assurance',
);
$refusSansPolitique = $ctr01->inscrireIdentite([
    'canal' => 'PRODUIT_RECONNU',
    'type' => 'personne',
    'libelle' => 'Dossier non gouverné',
    'producteur' => 'PRD-GAMAD-002',
    'source' => 'garde CAP-CORE-001',
    'preuve' => 'EVT-P3-REFUS-001',
]);
$refusProduitNonReconnu = $ctr01->inscrireIdentite([
    'canal' => 'PRODUIT_RECONNU',
    'type' => 'personne',
    'libelle' => 'Dossier produit non reconnu',
    'producteur' => 'PRD-GAMAD-003',
    'politique' => 'POL-INSCRIPTION-P3',
    'source' => 'garde CAP-CORE-001',
    'preuve' => 'EVT-P3-REFUS-002',
]);
$verifier(
    isset($refusSansPolitique['refus'])
        && ($refusProduitNonReconnu['refus'] ?? null) === 'PRODUCTEUR_INCOMPETENT',
    'une écriture sans politique ou sans producteur compétent est refusée',
);

$gouvernanceWasplex = [
    'politique' => 'POL-RATTACHEMENT-P3',
    'producteur' => 'PRD-GAMAD-003',
    'source' => 'garde CAP-CORE-001',
    'preuve' => 'EVT-P3-WASPLEX-001',
    'sujet_local_opaque' => 'wasplex-sujet-opaque-41',
    'date_debut' => '2026-07-30',
];
$gouvernanceDrive = [
    'politique' => 'POL-RATTACHEMENT-P3',
    'producteur' => 'PRD-GAMAD-002',
    'source' => 'garde CAP-CORE-001',
    'preuve' => 'EVT-P3-DRIVE-001',
    'sujet_local_opaque' => 'drive-sujet-opaque-92',
    'date_debut' => '2026-07-30',
];

// 4 et 5 — Wasplex puis GamaDrive, même personne et même référence.
$wasplex = $ctr01->rattacherProduit(
    $personneRef, 'PRD-GAMAD-003', 'UTILISATEUR', $gouvernanceWasplex,
);
$drive = $ctr01->rattacherProduit(
    $personneRef, 'PRD-GAMAD-002', 'CLIENT', $gouvernanceDrive,
);
$driveResolution = $ctr01->resoudreIdentiteDepuisSujetProduit(
    'PRD-GAMAD-002',
    'drive-sujet-opaque-92',
    '2026-07-30',
);
$verifier(
    ($wasplex['identite_reference'] ?? null) === $personneRef,
    'la personne est reliée à Wasplex',
);
$verifier(
    ($drive['identite_reference'] ?? null) === $personneRef
        && ($driveResolution['reference'] ?? null) === $personneRef,
    'GamaDrive réutilise la même identité canonique',
);

// 11 — un produit ne reçoit jamais la vue de l'autre produit.
$liensWasplex = $ctr01->resoudreLiensProduits(
    $personneRef, 'PRD-GAMAD-003', null, '2026-07-30',
);
$liensDrive = $ctr01->resoudreLiensProduits(
    $personneRef, 'PRD-GAMAD-002', null, '2026-07-30',
);
$verifier(
    count($liensWasplex) === 1
        && ($liensWasplex[0]['produit_reference'] ?? null) === 'PRD-GAMAD-003'
        && count($liensDrive) === 1
        && ($liensDrive[0]['produit_reference'] ?? null) === 'PRD-GAMAD-002'
        && $ctr01->resoudreIdentiteDepuisSujetProduit(
            'PRD-GAMAD-003',
            'drive-sujet-opaque-92',
            '2026-07-30',
        ) === null,
    'chaque produit ne lit que sa relation et ses sujets opaques',
);

// 6 — clôturer le compte Wasplex ajoute un événement sans toucher l'identité.
$cloture = $ctr01->cloreRelationProduit(
    (string) $wasplex['reference'],
    '2026-07-31',
    [
        'politique' => 'POL-RATTACHEMENT-P3',
        'producteur' => 'PRD-GAMAD-003',
        'source' => 'garde CAP-CORE-001',
        'preuve' => 'EVT-P3-WASPLEX-CLOTURE-001',
    ],
);
$verifier(
    ($cloture['relation_etat'] ?? null) === 'CLOSE'
        && ($ctr01->resoudreIdentite($personneRef)['etat'] ?? null) === 'ACTIVE'
        && $ctr01->resoudreIdentiteDepuisSujetProduit(
            'PRD-GAMAD-003',
            'wasplex-sujet-opaque-41',
            '2026-07-31',
        ) === null,
    'la clôture du compte produit ne supprime ni ne clôt l’identité',
);

// 8 — A0 reste A0, et une identité provisoire ne peut être élevée à A3.
$provisoire = $ctr01->inscrireIdentite([
    'canal' => 'AUTO_INSCRIPTION',
    'type' => 'personne',
    'libelle' => 'Identité provisoire P3',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'politique' => 'POL-AUTO-INSCRIPTION-P3',
    'source' => 'garde CAP-CORE-001',
    'preuve' => 'EVT-P3-PROVISOIRE-001',
    'provisoire' => true,
    'date' => '2026-07-30',
]);
$provisoireRef = (string) ($provisoire['reference'] ?? '');
$elevation = $ctr01->inscrireEvenementAssurance(
    $provisoireRef,
    'A3',
    'EVT-P3-ASSURANCE-FAUSSE',
    'garde CAP-CORE-001',
);
$verifier(
    ($ctr01->resoudreAssurance($provisoireRef)['niveau'] ?? null) === 'A0'
        && ($elevation['refus'] ?? null) === 'ASSURANCE_PROVISOIRE_DEPASSEE',
    'une identité A0 ou provisoire ne devient jamais automatiquement A3',
);

// 9 — une relation organisationnelle ne crée pas de mandat.
$lienOrganisation = $ctr01->rattacherOrganisation(
    $personneRef,
    $organisationRef,
    'REPRESENTANT',
    [
        'politique' => 'POL-RELATION-ORGANISATION-P3',
        'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
        'source' => 'garde CAP-CORE-001',
        'preuve' => 'EVT-P3-RELATION-ORG-001',
        'date_debut' => '2026-07-30',
    ],
);
$verifier(
    ($lienOrganisation['mandat_reference'] ?? null) === null
        && ($lienOrganisation['mandat_verifie'] ?? true) === false
        && ($lienOrganisation['opposable'] ?? true) === false,
    'une relation avec une organisation ne crée pas de mandat',
);

// 10 — une correspondance probable reste en validation.
$rapprochement = $ctr01->proposerRapprochement(
    $personneRef,
    $provisoireRef,
    ['EVT-P3-SIGNAL-001', 'EVT-P3-SIGNAL-002'],
    [
        'politique' => 'POL-RAPPROCHEMENT-P3',
        'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
        'source' => 'garde CAP-CORE-001',
        'preuve' => 'EVT-P3-RAPPROCHEMENT-001',
        'qualification' => 'CORRESPONDANCE_PROBABLE',
        'date' => '2026-07-30',
    ],
);
$verifier(
    ($rapprochement['qualification'] ?? null) === 'CORRESPONDANCE_PROBABLE'
        && ($rapprochement['etat'] ?? null) === 'VALIDATION_REQUISE'
        && array_key_exists('decideur', $rapprochement)
        && $rapprochement['decideur'] === null,
    'une correspondance probable reste en attente de validation',
);

// 7 — réindexation avec bases séparées.
(new Ingestion($index, REGN_CORPUS))->executer();
$ctr01 = new Ctr01($index, $registre);
$verifier(
    ($ctr01->resoudreIdentite($personneRef)['reference'] ?? null) === $personneRef
        && SchemaInscription::presente($registre),
    'une réindexation ne touche pas le registre persistant séparé',
);

// 7, défense en profondeur — même en mono-connexion les tables ne sont pas
// dans la liste destructrice de Schema::create().
putenv('SQLITE_PATH=' . $partageFichier);
$partage = Db::connect();
(new Ingestion($partage, REGN_CORPUS))->executer();
$ctrPartage = new Ctr01($partage);
$idPartagee = $ctrPartage->inscrireIdentite([
    'canal' => 'PRODUIT_RECONNU',
    'type' => 'personne',
    'libelle' => 'Identité mono-connexion P3',
    'producteur' => 'PRD-GAMAD-002',
    'politique' => 'POL-INSCRIPTION-P3',
    'source' => 'garde CAP-CORE-001',
    'preuve' => 'EVT-P3-PARTAGE-001',
    'date' => '2026-07-30',
]);
(new Ingestion($partage, REGN_CORPUS))->executer();
$ctrPartage = new Ctr01($partage);
$verifier(
    ($ctrPartage->resoudreIdentite((string) $idPartagee['reference'])['reference'] ?? null)
        === ($idPartagee['reference'] ?? null),
    'la liste de réindexation exclut structurellement les tables persistantes',
);

// 12 — aucune colonne de profil universel, réputation ou jugement.
$interdites = ['profil', 'reputation', 'réputation', 'jugement', 'moral', 'spirituel'];
$colonnesInterdites = [];
foreach (SchemaInscription::TABLES as $table) {
    foreach ($registre->query("PRAGMA table_info({$table})")->fetchAll() as $colonne) {
        $nom = mb_strtolower((string) $colonne['name'], 'UTF-8');
        foreach ($interdites as $interdite) {
            if (str_contains($nom, $interdite)) {
                $colonnesInterdites[] = "{$table}.{$nom}";
            }
        }
    }
}
$verifier($colonnesInterdites === [], 'le schéma ne contient aucune colonne de profil ou de jugement');
$routes = (string) file_get_contents(REGN_CORPUS . '/apps/console-laravel/routes/web.php');
$routesApi = (string) file_get_contents(REGN_CORPUS . '/apps/console-laravel/routes/api.php');
$controleurConsole = (string) file_get_contents(
    REGN_CORPUS . '/apps/console-laravel/app/Http/Controllers/IdentiteConsoleController.php'
);
$casUsage = (string) file_get_contents(
    REGN_CORPUS . '/apps/console-laravel/app/Application/Identites/InscrireIdentite.php'
);
$verifier(
    str_contains($routes, "Route::middleware('gamad.session')")
        && str_contains($routes, "Route::post('/identites'")
        && str_contains($controleurConsole, 'InscrireIdentite $inscrire')
        && str_contains($casUsage, "->autoriser("),
    'la console inscrit une identité sous session via le cas d’usage gouverné commun',
);
$verifier(
    str_contains($routesApi, "Route::middleware('gamad.api')")
        && str_contains($routesApi, "Route::post('/identites'")
        && str_contains(
            (string) file_get_contents(
                REGN_CORPUS . '/apps/console-laravel/app/Http/Controllers/Api/V1/IdentiteController.php'
            ),
            'InscrireIdentite $inscrire',
        )
        && str_contains($casUsage, "->autoriser("),
    'l’API v1 d’inscription exige une session et une décision CAP-CORE-004',
);

// 14 — contre-épreuves ciblées sur copie temporaire. La garde doit détecter
// les falsifications, pas les corriger.
$verifier(
    $ctr01->verifierIntegritePersistante()['valide'] === true,
    'le registre sain passe le diagnostic d’intégrité',
);
$registre->prepare(
    "UPDATE rapprochement_identite SET etat = 'VALIDEE' WHERE reference = ?"
)->execute([$rapprochement['reference']]);
$registre->prepare(
    'INSERT INTO evenement_assurance_identite
     (identite_reference,niveau,preuve_reference,source,producteur,date_effet)
     VALUES(?,?,?,?,?,?)'
)->execute([
    $provisoireRef, 'A3', 'FALSIFICATION-A3', 'contre-épreuve',
    'CAP-CORE-005', '2026-07-31',
]);
$registre->prepare(
    'INSERT INTO evenement_relation_identite
     (relation_reference,categorie,evenement_type,etat,source,
      preuve_reference,producteur,date_effet)
     VALUES(?,?,?,?,?,?,?,?)'
)->execute([
    $drive['reference'], 'PRODUIT', 'CLOTURE', 'CLOSE', 'contre-épreuve',
    'FALSIFICATION-DATE', 'PRD-GAMAD-002', '2026-01-01',
]);
$diagnosticFalsifie = $ctr01->verifierIntegritePersistante();
$verifier(
    $diagnosticFalsifie['valide'] === false
        && count($diagnosticFalsifie['ecarts']) >= 3,
    'les falsifications ciblées font échouer la garde',
);

foreach ([$indexFichier, $registreFichier, $partageFichier] as $fichier) {
    @unlink($fichier);
}

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-001 couvre le registre persistant révisé.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
