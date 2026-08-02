<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-006 — registre persistant des sources.
 *
 * CAP-CORE-006 n'est plus une simple lecture de l'index documentaire : elle
 * possède un magasin persistant gouverné, un cycle de vie en ajout seul, des
 * révisions, des vérifications expirables, des finalités bornées par
 * consommateur et par période, et une lignée traçable et acyclique. `CTR-15`
 * ne dépend plus des tables `norme`, `version_norme`, `statut`, `adoption` ni
 * `relation_evolution` du registre des normes (fiche CAP-CORE-006, §7).
 *
 * CONTRE-ÉPREUVE : la dernière épreuve retire une source du magasin et
 * vérifie que sa résolution échoue. Un test qui ne peut pas échouer ne
 * prouve rien.
 *
 * Exécution : php core/registre-sources/tests/sources_p3.php
 * Code de sortie : 0 si la garde passe, 1 sinon.
 */

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../../registre-produits/src/PolitiqueProduits.php';
require __DIR__ . '/../../registre-produits/src/SchemaProduits.php';
require __DIR__ . '/../../registre-produits/src/Magasin.php';
require __DIR__ . '/../../registre-produits/src/RegistreProduits.php';
require __DIR__ . '/../src/RegistreSources.php';

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;
use Gamad\RegistreSources\Ctr15;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\PolitiqueSources;
use Gamad\RegistreSources\RegistreSources;

$pid = getmypid();
$fichiers = [
    'index' => sys_get_temp_dir() . "/regs-sources-p3-index-{$pid}.sqlite",
    'identites' => sys_get_temp_dir() . "/regs-sources-p3-identites-{$pid}.sqlite",
    'produits' => sys_get_temp_dir() . "/regs-sources-p3-produits-{$pid}.sqlite",
    'sources' => sys_get_temp_dir() . "/regs-sources-p3-sources-{$pid}.sqlite",
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

$identitesReg = IdentiteMagasin::connecter($fichiers['identites']);
$ctr01 = new Ctr01($index, $identitesReg);
$produitsMagasin = ProduitsMagasin::connecter($fichiers['produits']);
$produits = new RegistreProduits($index, $identitesReg, $produitsMagasin, $ctr01);
$sourcesMagasin = SourcesMagasin::connecter($fichiers['sources']);
$registre = new RegistreSources($index, $identitesReg, $sourcesMagasin, $produitsMagasin, $ctr01, $produits);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — REGISTRE DES SOURCES (CAP-CORE-006)\n\n";

$AUTORITE = PolitiqueInscription::AUTORITE_INSCRIPTION; // AUT-GAMAD-001
$POLITIQUE = PolitiqueSources::POLITIQUE;
$SOURCE_TECH = 'test';

$gouvernance = static fn (string $acteur = null): array => [
    'politique' => $POLITIQUE,
    'producteur' => $acteur ?? PolitiqueInscription::AUTORITE_INSCRIPTION,
    'source' => $SOURCE_TECH,
    'preuve' => 'PREUVE-' . bin2hex(random_bytes(6)),
];

/* -------------------------------------------------------- bootstrap réel */
echo "  Bootstrap des sources historiques\n";

$legacy = $index->query('SELECT reference, titre, categorie, authenticite, reserve FROM source ORDER BY reference')
    ->fetchAll();
$verifier($legacy !== [], "l'index porte des sources historiques réelles", count($legacy) . ' source(s)');

$erreursBootstrap = 0;
foreach ($legacy as $ligne) {
    $ins = $registre->inscrireSource(array_merge($gouvernance(), [
        'reference' => $ligne['reference'],
        'nom_canonique' => $ligne['titre'],
        'nom_affichage' => $ligne['titre'],
        'type_source' => 'IMPORT_GOUVERNE',
        'proprietaire_reference' => $AUTORITE,
        'categorie' => $ligne['categorie'],
        'authenticite_legacy' => $ligne['authenticite'],
        'reserve' => $ligne['reserve'],
    ]));
    if (isset($ins['refus'])) {
        $erreursBootstrap++;

        continue;
    }
    $act = $registre->activerSource($ligne['reference'], $gouvernance());
    if (isset($act['refus'])) {
        $erreursBootstrap++;
    }
}
$verifier($erreursBootstrap === 0, 'chaque source historique s’inscrit et s’active sans invention de données');

$rejeu = 0;
foreach ($legacy as $ligne) {
    $existant = $registre->resoudreSource($ligne['reference']);
    if ($existant === null || $existant['etat'] !== 'ACTIVE') {
        $rejeu++;
    }
}
$verifier($rejeu === 0, 'rejouer le bootstrap ne recrée aucun doublon (source déjà active)', count($legacy) . ' source(s) vérifiée(s)');

/* --------------------------------------------------- identité canonique */
echo "\n  Identité canonique\n";

$verifier(
    $registre->resoudreSource('SOURCES-0001')['reference'] === 'SOURCES-0001',
    'une source connue se résout par sa référence canonique',
);
$verifier(
    $registre->resoudreSource('SRC-9999-INCONNUE') === null,
    'une référence inconnue rend null, sans rapprochement approché',
);

/* -------------------------------------------------- authenticité legacy */
echo "\n  Authenticité historique préservée, jamais réinterprétée\n";

$silsila = $registre->resoudreSource('SRC-0007');
$verifier(
    $silsila !== null && str_starts_with((string) $silsila['authenticite_legacy'], 'AUTH-1'),
    "SRC-0007 conserve sa valeur historique d'authenticité AUTH-1",
    'valeur : ' . ($silsila['authenticite_legacy'] ?? 'null'),
);
$verifier(
    $silsila !== null && $silsila['reserve'] !== null && $silsila['reserve'] !== '',
    'la réserve inscrite sur SRC-0007 est restituée, non masquée',
);

/* --------------------------------------------------- inscription gouvernée */
echo "\n  Inscription gouvernée\n";

$REF = 'SRC-P3-TEST-001';
$sansPolitique = $registre->inscrireSource([
    'reference' => $REF, 'nom_canonique' => 'Test', 'nom_affichage' => 'Test',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
    'producteur' => $AUTORITE, 'source' => $SOURCE_TECH, 'preuve' => 'P',
]);
$verifier(
    ($sansPolitique['refus'] ?? null) === 'DOSSIER_INCOMPLET',
    'une inscription sans politique est refusée',
);

$sansProprietaireConnu = $registre->inscrireSource(array_merge($gouvernance(), [
    'reference' => $REF, 'nom_canonique' => 'Test', 'nom_affichage' => 'Test',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => 'IDN-INCONNUE-000',
]));
$verifier(
    ($sansProprietaireConnu['refus'] ?? null) === 'PROPRIETAIRE_INCONNU',
    'un propriétaire non connu de CAP-CORE-001 est refusé',
);

$sansProduitActif = $registre->inscrireSource(array_merge($gouvernance(), [
    'reference' => $REF, 'nom_canonique' => 'Test', 'nom_affichage' => 'Test',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
    'produit_producteur_reference' => 'PRD-INCONNU-000',
]));
$verifier(
    ($sansProduitActif['refus'] ?? null) === 'PRODUIT_PRODUCTEUR_INCONNU',
    'un produit producteur inconnu de CAP-CORE-011 est refusé',
);

$inscription = $registre->inscrireSource(array_merge($gouvernance(), [
    'reference' => $REF, 'nom_canonique' => 'Test P3', 'nom_affichage' => 'Test P3',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
    'categorie' => 'Catégorie de test', 'description' => 'Description de test',
]));
$verifier(
    ($inscription['etat'] ?? null) === 'PREPARATION',
    'une source inscrite naît en PREPARATION, jamais activée automatiquement',
);

$doublon = $registre->inscrireSource(array_merge($gouvernance(), [
    'reference' => $REF, 'nom_canonique' => 'Doublon', 'nom_affichage' => 'Doublon',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
]));
$verifier(
    ($doublon['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    'une référence déjà inscrite est refusée',
);

/* ------------------------------------------------------------ activation */
echo "\n  Cycle de vie\n";

$activation = $registre->activerSource($REF, $gouvernance());
$verifier(
    ($activation['etat'] ?? null) === 'ACTIVE' && ($activation['idempotent'] ?? null) === false,
    'l’activation gouvernée réussit',
);
$reactivation = $registre->activerSource($REF, $gouvernance());
$verifier(
    ($reactivation['idempotent'] ?? null) === true,
    'rejouer une activation déjà acquise est idempotent, sans seconde ligne de cycle',
);

$suspension = $registre->suspendreSource($REF, $gouvernance());
$verifier(
    ($suspension['etat'] ?? null) === 'SUSPENDUE',
    'la suspension retire immédiatement la source de l’usage',
);
$suspensionDepuisPreparation = $registre->suspendreSource(
    (function () use ($registre, $gouvernance, $AUTORITE): string {
        $ref = 'SRC-P3-PREP-' . bin2hex(random_bytes(3));
        $registre->inscrireSource(array_merge($gouvernance(), [
            'reference' => $ref, 'nom_canonique' => 'Prep', 'nom_affichage' => 'Prep',
            'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
        ]));

        return $ref;
    })(),
    $gouvernance(),
);
$verifier(
    ($suspensionDepuisPreparation['refus'] ?? null) === 'ETAT_INCOMPATIBLE',
    'seule une source ACTIVE se suspend',
);

$retrait = $registre->retirerSource($REF, $gouvernance());
$reinscriptionInterdite = $registre->inscrireSource(array_merge($gouvernance(), [
    'reference' => $REF, 'nom_canonique' => 'Reinscription', 'nom_affichage' => 'Reinscription',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
]));
$verifier(
    ($retrait['etat'] ?? null) === 'RETIREE'
        && ($reinscriptionInterdite['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    'le retrait est irréversible, ne supprime rien, et la référence n’est jamais réutilisée',
);

$historique = $registre->resoudreHistorique($REF);
$verifier(
    count($historique) === 4
        && $historique[0]['etat'] === 'PREPARATION'
        && $historique[1]['etat'] === 'ACTIVE'
        && $historique[2]['etat'] === 'SUSPENDUE'
        && $historique[3]['etat'] === 'RETIREE',
    'l’historique conserve chaque transition, datée, sans réécrire le passé',
);

/* -------------------------------------------------------------- révision */
echo "\n  Révisions en ajout seul\n";

$REF2 = 'SRC-P3-REVISION-001';
$registre->inscrireSource(array_merge($gouvernance(), [
    'reference' => $REF2, 'nom_canonique' => 'Nom canonique stable', 'nom_affichage' => 'Nom v1',
    'type_source' => 'ORGANISATION', 'proprietaire_reference' => $AUTORITE,
]));
$registre->activerSource($REF2, $gouvernance());
$avantModif = $registre->resoudreSource($REF2);
$champImmuable = $registre->modifierSource($REF2, array_merge($gouvernance(), ['nom_canonique' => 'Tentative']));
$modif = $registre->modifierSource($REF2, array_merge($gouvernance(), ['nom_affichage' => 'Nom v2']));
$revisions = $registre->resoudreRevisions($REF2);
$verifier(
    ($champImmuable['refus'] ?? null) === 'CHAMP_IMMUABLE'
        && $avantModif['nom_canonique'] === $registre->resoudreSource($REF2)['nom_canonique']
        && ($modif['nom_affichage'] ?? null) === 'Nom v2'
        && count($revisions) === 2
        && $revisions[0]['nom_affichage'] === 'Nom v1',
    'la référence et le nom canonique ne se modifient jamais ; les autres métadonnées, oui, en ajout seul',
);

/* -------------------------------------------------------------- finalité */
echo "\n  Finalités : bornées, jamais implicites\n";

// Un produit consommateur réel et ACTIF, requis par CAP-CORE-006 pour toute
// finalité bornée à un consommateur.
$identiteConsommateur = $ctr01->inscrireIdentite([
    'canal' => 'CREATION_TECHNIQUE', 'type' => 'produit', 'libelle' => 'Consommateur P3',
    'producteur' => $AUTORITE, 'politique' => 'POL-PRODUITS-P3',
    'source' => 'garde CAP-CORE-006', 'preuve' => 'EVT-P3-IDN-' . strtoupper(bin2hex(random_bytes(4))),
]);
$REF_CONSOMMATEUR = 'PRD-P3-CONSOMMATEUR-' . strtoupper(bin2hex(random_bytes(4)));
$dossierProduit = static fn (array $extra = []): array => $extra + [
    'politique' => PolitiqueProduits::POLITIQUE, 'source' => PolitiqueProduits::SOURCE,
    'producteur' => PolitiqueProduits::AUTORITE, 'preuve' => 'EVT-P3-PRD-' . strtoupper(bin2hex(random_bytes(4))),
];
$produits->inscrireProduit($dossierProduit([
    'reference' => $REF_CONSOMMATEUR, 'identite_reference' => (string) $identiteConsommateur['reference'],
    'nom_canonique' => 'Consommateur P3', 'nom_affichage' => 'Consommateur P3',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $AUTORITE,
]));
$produits->activerProduit($REF_CONSOMMATEUR, $dossierProduit());

$verifier(
    $registre->resoudreFinalites($REF2) === [],
    'une source active sans finalité déclarée reste sans finalité implicite',
);

$verifUtilisableSansFinalite = $registre->verifierUtilisable($REF2, $REF_CONSOMMATEUR, 'FIN-TEST');
$verifier(
    $verifUtilisableSansFinalite['utilisable'] === false
        && in_array('FINALITE_NON_DECLAREE', $verifUtilisableSansFinalite['motifs'], true),
    'sans finalité déclarée, l’utilisabilité est refusée avec un motif explicite',
);

$finalite = $registre->declarerFinalite($REF2, array_merge($gouvernance(), [
    'finalite_reference' => 'FIN-TEST', 'produit_consommateur_reference' => $REF_CONSOMMATEUR,
    'date_debut' => '2026-01-01',
]));
$verifUtilisableAvecFinalite = $registre->verifierUtilisable($REF2, $REF_CONSOMMATEUR, 'FIN-TEST');
$verifUtilisableAutreConsommateur = $registre->verifierUtilisable($REF2, 'PRD-AUTRE', 'FIN-TEST');
$verifier(
    !isset($finalite['refus'])
        && $verifUtilisableAvecFinalite['utilisable'] === true
        && $verifUtilisableAutreConsommateur['utilisable'] === false,
    'une finalité déclarée pour un consommateur précis ne s’étend pas à un autre',
);

$finaliteExpiree = $registre->declarerFinalite($REF2, array_merge($gouvernance(), [
    'finalite_reference' => 'FIN-EXPIREE', 'produit_consommateur_reference' => $REF_CONSOMMATEUR,
    'date_debut' => '2020-01-01', 'date_fin' => '2020-06-01',
]));
$verifUtilisableExpiree = $registre->verifierUtilisable($REF2, $REF_CONSOMMATEUR, 'FIN-EXPIREE', '2026-01-01');
$verifier(
    !isset($finaliteExpiree['refus'])
        && $verifUtilisableExpiree['utilisable'] === false
        && in_array('FINALITE_EXPIREE', $verifUtilisableExpiree['motifs'], true),
    'une finalité dont la date de fin est dépassée est refusée',
);

$fermeture = $registre->fermerFinalite($REF2, (int) $finalite['id'], $gouvernance());
$fermetureIdempotente = $registre->fermerFinalite($REF2, (int) $finalite['id'], $gouvernance());
$verifier(
    $fermeture['actif'] === false && $fermetureIdempotente['idempotent'] === true,
    'fermer une finalité est datée, ne supprime rien, et idempotente',
);

$registre->suspendreSource($REF2, $gouvernance());
$verifUtilisableSuspendue = $registre->verifierUtilisable($REF2, $REF_CONSOMMATEUR, 'FIN-EXPIREE');
$verifier(
    in_array('SOURCE_SUSPENDUE', $verifUtilisableSuspendue['motifs'], true),
    'une source suspendue est refusée pour tout usage, même avec une finalité par ailleurs déclarée',
);
$registre->activerSource($REF2, $gouvernance());

/* ---------------------------------------------------------- vérification */
echo "\n  Vérifications historisées, expirables, non auto-attestées\n";

$verifNiveauInitial = $registre->resoudreVerificationCourante($REF2);
$verifier(
    $verifNiveauInitial === null,
    'sans vérification enregistrée, aucune n’est inventée (niveau NON_VERIFIEE par convention de lecture)',
);

$attestationAuto = $registre->enregistrerVerification($REF2, array_merge($gouvernance(), [
    'niveau' => 'ATTESTEE', 'resultat' => 'VALIDE', 'verifie_par_reference' => $AUTORITE,
]));
$verifier(
    ($attestationAuto['refus'] ?? null) === 'AUTO_ATTESTATION_INTERDITE',
    'une source ne peut pas s’auto-attester : vérificateur et producteur doivent différer pour ATTESTEE',
);

$verification = $registre->enregistrerVerification($REF2, array_merge($gouvernance(), [
    'niveau' => 'CONTROLEE', 'resultat' => 'VALIDE', 'verifie_par_reference' => 'AUT-TIERS-VERIF',
    'verifie_le' => '2026-01-01', 'expire_le' => '2026-02-01',
]));
$verifCourante = $registre->resoudreVerificationCourante($REF2, '2026-06-01');
$verifier(
    !isset($verification['refus'])
        && $verifCourante['expiree'] === true
        && $verifCourante['resultat'] === 'EXPIREE',
    'une vérification passée sa date d’expiration est signalée comme expirée',
);

/* -------------------------------------------------------------- lignée */
echo "\n  Lignée acyclique\n";

$REF3 = 'SRC-P3-LIGNEE-PARENTE';
$registre->inscrireSource(array_merge($gouvernance(), [
    'reference' => $REF3, 'nom_canonique' => 'Parente', 'nom_affichage' => 'Parente',
    'type_source' => 'ORGANISATION', 'proprietaire_reference' => $AUTORITE,
]));
$registre->activerSource($REF3, $gouvernance());

$autoParente = $registre->declarerLignee($REF2, array_merge($gouvernance(), [
    'source_parente_reference' => $REF2, 'type_relation' => 'DERIVEE_DE',
]));
$verifier(
    ($autoParente['refus'] ?? null) === 'SOURCE_PROPRE_PARENTE_INTERDITE',
    'une source ne peut pas être sa propre parente',
);

$lignee1 = $registre->declarerLignee($REF2, array_merge($gouvernance(), [
    'source_parente_reference' => $REF3, 'type_relation' => 'DERIVEE_DE',
]));
$cycle = $registre->declarerLignee($REF3, array_merge($gouvernance(), [
    'source_parente_reference' => $REF2, 'type_relation' => 'DERIVEE_DE',
]));
$verifier(
    !isset($lignee1['refus']) && ($cycle['refus'] ?? null) === 'CYCLE_LIGNEE_INTERDIT',
    'toute relation qui fermerait un cycle de lignée est refusée avant écriture',
);

$ligneeAmont = $registre->resoudreLignee($REF2);
$ligneeAval = $registre->resoudreLignee($REF3);
$verifier(
    count($ligneeAmont['amont']) === 1 && $ligneeAmont['amont'][0]['reference'] === $REF3
        && count($ligneeAval['aval']) === 1 && $ligneeAval['aval'][0]['reference'] === $REF2,
    'la lignée amont d’une source correspond à la lignée aval de sa parente',
);
$verifier(
    $registre->resoudreLignee('SRC-INCONNUE-9999') === null,
    'une source inconnue rend une lignée null, distincte d’une lignée vide',
);

/* -------------------------------------------------------------- sécurité */
echo "\n  Refus par défaut et absence de secrets\n";

$sansPreuveActivation = $registre->activerSource($REF3, [
    'politique' => $POLITIQUE, 'producteur' => $AUTORITE, 'source' => $SOURCE_TECH, 'preuve' => '',
]);
$verifier(
    ($sansPreuveActivation['refus'] ?? null) === 'COMMANDE_NON_GOUVERNEE',
    'refus par défaut : une commande sans preuve n’est jamais exécutée',
);

// Un scan brut du fichier échouerait sur du contenu légitime : la baseline
// documentaire porte un intitulé réel « Gouvernance des accès, secrets,
// incidents, continuité » (SECURITY-GOVERNANCE-0001), sans qu'aucun secret
// n'y soit stocké. La preuve structurelle est plus sûre : aucune colonne du
// schéma ne peut porter un identifiant, un mot de passe ou un jeton.
$colonnesSuspectes = [];
foreach (Gamad\RegistreSources\SchemaSources::TABLES as $table) {
    foreach ($sourcesMagasin->query("PRAGMA table_info({$table})")->fetchAll() as $colonne) {
        if (preg_match('/secret|password|mot_de_passe|jeton|token|empreinte_acces/i', (string) $colonne['name'])) {
            $colonnesSuspectes[] = "{$table}.{$colonne['name']}";
        }
    }
}
$verifier(
    $colonnesSuspectes === [],
    'le schéma du magasin des sources ne porte aucune colonne de secret',
    $colonnesSuspectes === [] ? '' : implode(', ', $colonnesSuspectes),
);

/* --------------------------------------------- CTR-15 découplé des normes */
echo "\n  CTR-15 découplé du registre des normes\n";

$sourceCtr15 = file_get_contents(__DIR__ . '/../src/Ctr15.php');
$verifier(
    $sourceCtr15 !== false
        && !preg_match('/\bFROM\s+(norme|version_norme|statut|adoption|relation_evolution)\b/i', $sourceCtr15),
    'CTR-15 ne contient aucune requête vers les tables du registre des normes',
);

$ctr15 = new Ctr15($sourcesMagasin);
$verifier(
    $ctr15->resoudreSource('SOURCES-0001')['reference'] === 'SOURCES-0001'
        && $ctr15->resoudreLignee($REF3)['reference'] === $REF3,
    'CTR-15 résout les sources et leur lignée uniquement depuis le magasin persistant',
);

/* -------------------------------------------------- reconstruction index */
echo "\n  Reconstruction de la baseline sans perte du registre\n";

BaselineOperationnelle::standard()->reconstruire($index);
$verifier(
    $registre->resoudreSource('SOURCES-0001') !== null
        && $registre->resoudreSource($REF3) !== null,
    'reconstruire l’index documentaire ne supprime jamais le registre persistant des sources',
);

/* ------------------------------------------------------- CONTRE-ÉPREUVE */
echo "\n  Contre-épreuve — la garde doit savoir échouer\n";

$sourcesMagasin->exec("DELETE FROM source WHERE reference = 'SOURCES-0001'");
$verifier(
    $registre->resoudreSource('SOURCES-0001') === null,
    'une source retirée du magasin cesse d’être résolue',
);

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-006 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-006 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
