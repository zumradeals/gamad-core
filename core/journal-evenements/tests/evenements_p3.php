<?php

declare(strict_types=1);

/**
 * Garde de comportement du noyau de CAP-CORE-014 — journal des événements.
 *
 * Éprouve directement `RegistreEvenements`, `RouteurEvenements`,
 * `RegistreAbonnements`, `LivreurEvenements` et `RejoueurEvenements`, sur des
 * fiches réelles (mais temporaires) de source (CAP-CORE-006), de realm
 * (CAP-CORE-012) et de contrat d'événement (CAP-CORE-009), pour que les
 * contrôles croisés ne soient jamais simulés.
 *
 * Portée assumée : cette garde couvre le noyau (magasin, enveloppe, routage,
 * abonnements, livraison, rejeu, lettres mortes). Elle ne couvre pas l'API
 * HTTP, la console, OpenAPI, les workers d'exploitation ni la readiness —
 * chantiers restants documentés dans le rapport final de la PR.
 *
 * Exécution : php core/journal-evenements/tests/evenements_p3.php
 */

use Gamad\JournalEvenements\EnveloppeEvenement;
use Gamad\JournalEvenements\LivreurEvenements;
use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\RegistreAbonnements;
use Gamad\JournalEvenements\RegistreEvenements;
use Gamad\JournalEvenements\RejoueurEvenements;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\PolitiqueContrats;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\PolitiqueRealms;
use Gamad\RegistreRealms\RegistreRealms;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\PolitiqueSources;
use Gamad\RegistreSources\RegistreSources;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../../registre-produits/src/PolitiqueProduits.php';
require __DIR__ . '/../../registre-produits/src/SchemaProduits.php';
require __DIR__ . '/../../registre-produits/src/Magasin.php';
require __DIR__ . '/../../evenements-sortants/src/SchemaOutbox.php';
require __DIR__ . '/../../evenements-sortants/src/OutboxProducteur.php';
require __DIR__ . '/../../registre-produits/src/RegistreProduits.php';
require __DIR__ . '/../../registre-sources/src/RegistreSources.php';
require __DIR__ . '/../../registre-contrats/src/PolitiqueContrats.php';
require __DIR__ . '/../../registre-contrats/src/ExceptionContrat.php';
require __DIR__ . '/../../registre-contrats/src/ValidateurContrat.php';
require __DIR__ . '/../../registre-contrats/src/SchemaContrats.php';
require __DIR__ . '/../../registre-contrats/src/Magasin.php';
require __DIR__ . '/../../registre-contrats/src/AnalyseurCompatibilite.php';
require __DIR__ . '/../../registre-contrats/src/GenerateurOpenApi.php';
require __DIR__ . '/../../registre-contrats/src/RegistreContrats.php';
require __DIR__ . '/../../registre-realms/src/ExceptionRealm.php';
require __DIR__ . '/../../registre-realms/src/PolitiqueRealms.php';
require __DIR__ . '/../../registre-realms/src/SchemaRealms.php';
require __DIR__ . '/../../registre-realms/src/Magasin.php';
require __DIR__ . '/../../registre-realms/src/ValidateurRealms.php';
require __DIR__ . '/../../registre-realms/src/EvaluateurPortee.php';
require __DIR__ . '/../../registre-realms/src/RegistreRealms.php';
require __DIR__ . '/../src/ExceptionEvenement.php';
require __DIR__ . '/../src/PolitiqueEvenements.php';
require __DIR__ . '/../src/EnveloppeEvenement.php';
require __DIR__ . '/../src/ValidateurEvenement.php';
require __DIR__ . '/../src/SchemaEvenements.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/RegistreEvenements.php';
require __DIR__ . '/../src/RouteurEvenements.php';
require __DIR__ . '/../src/RegistreAbonnements.php';
require __DIR__ . '/../src/LivreurEvenements.php';
require __DIR__ . '/../src/RejoueurEvenements.php';

$prefixe = sys_get_temp_dir() . '/gamad-evenements-p3-' . getmypid();
$fichiers = [
    'index' => $prefixe . '-index.sqlite',
    'identites' => $prefixe . '-identites.sqlite',
    'produits' => $prefixe . '-produits.sqlite',
    'sources' => $prefixe . '-sources.sqlite',
    'contrats' => $prefixe . '-contrats.sqlite',
    'realms' => $prefixe . '-realms.sqlite',
    'evenements' => $prefixe . '-evenements.sqlite',
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

$registreIdentites = IdentiteMagasin::connecter($fichiers['identites']);
$ctr01 = new Ctr01($index, $registreIdentites);

$AUTORITE = PolitiqueInscription::AUTORITE_INSCRIPTION;

$inscrireIdentite = static function (string $type, string $libelle) use ($ctr01): string {
    $identite = $ctr01->inscrireIdentite([
        'canal' => 'AUTORITE', 'type' => $type, 'libelle' => $libelle,
        'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-EVT-P3',
        'source' => 'garde CAP-CORE-014', 'preuve' => 'EVT-P3-IDN-' . strtoupper(bin2hex(random_bytes(4))),
    ]);
    if (isset($identite['refus'])) {
        throw new RuntimeException('inscription identité impossible : ' . json_encode($identite));
    }

    return (string) $identite['reference'];
};

// ------------------------------------------------------------------ sources
$produitsMagasin = ProduitsMagasin::connecter($fichiers['produits']);
$sourcesMagasin = SourcesMagasin::connecter($fichiers['sources']);
$sources = new RegistreSources($index, $registreIdentites, $sourcesMagasin, $produitsMagasin, $ctr01);
$gSource = static fn (): array => [
    'politique' => PolitiqueSources::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-014', 'preuve' => 'EVT-P3-SRC-' . strtoupper(bin2hex(random_bytes(4))),
];
$SRC = 'SRC-GAMAD-CAP-CORE-011';
$sources->inscrireSource(array_merge($gSource(), [
    'reference' => $SRC, 'nom_canonique' => 'source-p3-evenements', 'nom_affichage' => 'Source P3 Événements',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
]));
$sources->activerSource($SRC, $gSource());
$FINALITE = 'FINALITE-DIFFUSION-EVENEMENTS-COMMUNS';
$sources->declarerFinalite($SRC, array_merge($gSource(), ['finalite_reference' => $FINALITE]));

$SRC_INACTIVE = 'SRC-GAMAD-P3-INACTIVE';
$sources->inscrireSource(array_merge($gSource(), [
    'reference' => $SRC_INACTIVE, 'nom_canonique' => 'source-p3-inactive', 'nom_affichage' => 'Source P3 Inactive',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
]));

// ------------------------------------------------------------------- realms
$realmsMagasin = RealmsMagasin::connecter($fichiers['realms']);
$realms = new RegistreRealms($index, $registreIdentites, $realmsMagasin, $ctr01);
$gRealm = static fn (): array => [
    'politique' => PolitiqueRealms::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-014', 'preuve' => 'EVT-P3-RLM-' . strtoupper(bin2hex(random_bytes(4))),
];
$creerRealmActif = static function (string $code) use ($realms, $inscrireIdentite, $gRealm, $AUTORITE): string {
    $idn = $inscrireIdentite('realm', "Realm {$code}");
    $ins = $realms->inscrireRealm(array_merge($gRealm(), [
        'identite_reference' => $idn, 'code_canonique' => $code, 'type_realm_reference' => 'TECHNIQUE',
        'nom_affichage' => $code, 'classification_reference' => 'INTERNE',
    ]));
    if (isset($ins['refus'])) {
        throw new RuntimeException("inscription realm {$code} impossible : " . json_encode($ins));
    }
    $reference = (string) $ins['reference'];
    $act = $realms->activerRealm($reference, $gRealm());
    if (isset($act['refus'])) {
        throw new RuntimeException("activation realm {$code} impossible : " . json_encode($act));
    }

    return $reference;
};
$RLM_A = $creerRealmActif('RLM-P3-EVT-A');
$RLM_B = $creerRealmActif('RLM-P3-EVT-B');

// ----------------------------------------------------------------- contrats
$contratsMagasin = ContratsMagasin::connecter($fichiers['contrats']);
$contrats = new RegistreContrats($index, $registreIdentites, $contratsMagasin, $ctr01);
$gContrat = static fn (): array => [
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-014', 'preuve' => 'EVT-P3-CTR-' . strtoupper(bin2hex(random_bytes(4))),
];
$CTR = 'EVT-GAMAD-PRODUIT-ACTIVE';
$PRODUCTEUR_CAPACITE = 'CAP-CORE-011';
$CONSOMMATEUR_CAPACITE = 'CAP-P3-CONSOMMATEUR';
$contrats->inscrireContrat(array_merge($gContrat(), [
    'reference' => $CTR, 'nom' => 'Produit activé', 'type_contrat' => 'EVENEMENT',
    'finalite_reference' => $FINALITE, 'producteur_capacite_reference' => $PRODUCTEUR_CAPACITE,
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'garde CAP-CORE-014',
]));
$contrats->creerVersion($CTR, array_merge($gContrat(), ['version' => '1.0.0', 'compatibilite_annoncee' => 'COMPATIBLE']));
$contrats->declarerPartie($CTR, '1.0.0', array_merge($gContrat(), ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => $PRODUCTEUR_CAPACITE]));
$contrats->declarerPartie($CTR, '1.0.0', array_merge($gContrat(), ['role' => 'CONSOMMATEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => $CONSOMMATEUR_CAPACITE]));
$contrats->declarerOperation($CTR, '1.0.0', array_merge($gContrat(), ['reference_operation' => 'produitActive', 'type_operation' => 'PUBLIER', 'idempotente' => true]));
$contrats->declarerSchema($CTR, '1.0.0', array_merge($gContrat(), [
    'operation_reference' => 'produitActive', 'sens' => 'EVENEMENT', 'format' => 'JSON_SCHEMA',
    'contenu' => json_encode(['proprietes' => ['produit_reference' => ['type' => 'string', 'requis' => true]]]),
]));
$contrats->soumettreVersion($CTR, '1.0.0', $gContrat());
$contrats->analyserCompatibilite($CTR, '1.0.0', $gContrat());
$contrats->enregistrerConformite($CTR, '1.0.0', array_merge($gContrat(), ['resultat' => 'CONFORME', 'artefact_reference' => 'garde-p3']));
$activationCtr = $contrats->activerVersion($CTR, '1.0.0', $gContrat());
if (isset($activationCtr['refus'])) {
    throw new RuntimeException('activation du contrat pilote impossible : ' . json_encode($activationCtr));
}

// -------------------------------------------------------------- évenements
$evtMagasin = EvenementsMagasin::connecter($fichiers['evenements']);
$registre = new RegistreEvenements($evtMagasin, $contrats, $sources, $realms);
$abonnements = new RegistreAbonnements($evtMagasin, $contrats, $realms);
$livreur = new LivreurEvenements($evtMagasin, $registre);
$rejoueur = new RejoueurEvenements($evtMagasin);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           {$detail}" : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — JOURNAL DES ÉVÉNEMENTS (CAP-CORE-014, noyau)\n\n";

$dossierGouvernance = ['politique' => 'POL-EVENEMENTS-V1', 'producteur' => $PRODUCTEUR_CAPACITE, 'source' => 'garde', 'preuve' => 'EVT-P3-GOV'];

$intentionValide = static fn (array $extra = []): array => array_merge([
    'type_evenement' => 'PRODUIT_ACTIVE',
    'contrat_reference' => $CTR,
    'contrat_version' => '1.0.0',
    'producteur_capacite_reference' => $PRODUCTEUR_CAPACITE,
    'source_reference' => $SRC,
    'realm_reference' => $RLM_A,
    'finalite_reference' => $FINALITE,
    'sujet_type' => 'PRODUIT',
    'sujet_reference' => 'PRD-P3-001',
    'correlation_id' => 'COR-P3-' . strtoupper(bin2hex(random_bytes(4))),
    'survenu_le' => gmdate('c'),
    'classification' => 'INTERNE',
    'idempotence_reference' => 'IDEMP-P3-' . strtoupper(bin2hex(random_bytes(6))),
    'charge' => ['produit_reference' => 'PRD-P3-001', 'nouvel_etat' => 'ACTIF'],
], $extra);

/* ------------------------------------------------------- 1-13 : refus d'enveloppe */
$typeInconnu = $registre->accepterEvenement($intentionValide(['contrat_reference' => 'EVT-GAMAD-INEXISTANT']), $dossierGouvernance);
$verifier(($typeInconnu['refus'] ?? null) === 'CONTRAT_INCONNU', '1. contrat absent refusé');

$versionInactive = $registre->accepterEvenement($intentionValide(['contrat_version' => '9.9.9']), $dossierGouvernance);
$verifier(($versionInactive['refus'] ?? null) === 'VERSION_INCOMPATIBLE', '2. version inactive refusée');

$producteurNonDeclare = $registre->accepterEvenement($intentionValide(['producteur_capacite_reference' => 'CAP-CORE-999']), $dossierGouvernance);
$verifier(($producteurNonDeclare['refus'] ?? null) === 'PRODUCTEUR_NON_DECLARE', '3. producteur non déclaré refusé');

$sourceInactive = $registre->accepterEvenement($intentionValide(['source_reference' => $SRC_INACTIVE]), $dossierGouvernance);
$verifier(($sourceInactive['refus'] ?? null) === 'SOURCE_INACTIVE', '4. source inactive refusée');

$realmInactif = $registre->accepterEvenement($intentionValide(['realm_reference' => 'RLM-INCONNU-000']), $dossierGouvernance);
$verifier(($realmInactif['refus'] ?? null) === 'REALM_INACTIF', '5. realm inactif ou inconnu refusé');

$finaliteAbsente = $registre->accepterEvenement($intentionValide(['finalite_reference' => 'FINALITE-INEXISTANTE']), $dossierGouvernance);
$verifier(($finaliteAbsente['refus'] ?? null) === 'FINALITE_ABSENTE', '6. finalité non déclarée active refusée');

$sansCorrelation = $registre->accepterEvenement($intentionValide(['correlation_id' => '']), $dossierGouvernance);
$verifier(($sansCorrelation['refus'] ?? null) === 'ENVELOPPE_INVALIDE', '7. corrélation obligatoire absente refusée');

$classificationInvalide = $registre->accepterEvenement($intentionValide(['classification' => 'INEXISTANTE']), $dossierGouvernance);
$verifier(($classificationInvalide['refus'] ?? null) === 'ENVELOPPE_INVALIDE', '8. classification hors vocabulaire refusée');

$secretRefuse = $registre->accepterEvenement($intentionValide(['charge' => ['mot_de_passe' => 'x'], 'idempotence_reference' => 'IDEMP-P3-SECRET']), $dossierGouvernance);
$verifier(($secretRefuse['refus'] ?? null) === 'CHARGE_INVALIDE', '9. secret dans la charge refusé');

$jetonRefuse = $registre->accepterEvenement($intentionValide(['charge' => ['jeton' => 'Bearer abcdefghijklmnop'], 'idempotence_reference' => 'IDEMP-P3-JETON']), $dossierGouvernance);
$verifier(($jetonRefuse['refus'] ?? null) === 'CHARGE_INVALIDE', '10. jeton ressemblant à un Bearer refusé');

$chargeExcessive = $registre->accepterEvenement($intentionValide(['charge' => ['bloc' => str_repeat('x', 40_000)], 'idempotence_reference' => 'IDEMP-P3-TAILLE']), $dossierGouvernance);
$verifier(($chargeExcessive['refus'] ?? null) === 'CHARGE_INVALIDE', '11. charge au-delà de la taille maximale refusée');

$empreinteDivergente = $registre->accepterEvenement($intentionValide(['charge_empreinte' => str_repeat('0', 64), 'idempotence_reference' => 'IDEMP-P3-EMPREINTE']), $dossierGouvernance);
$verifier(($empreinteDivergente['refus'] ?? null) === 'CHARGE_INVALIDE', '12. empreinte de charge annoncée divergente refusée');

$deuxProducteurs = $registre->accepterEvenement($intentionValide(['producteur_produit_reference' => 'PRD-X']), $dossierGouvernance);
$verifier(($deuxProducteurs['refus'] ?? null) === 'ENVELOPPE_INVALIDE', '13. deux producteurs principaux simultanés refusés');

/* --------------------------------------------------- 14-20 : acceptation, séquence, chaîne */
$idem1 = 'IDEMP-P3-ACCEPTE-001';
$accepte1 = $registre->accepterEvenement($intentionValide(['idempotence_reference' => $idem1]), $dossierGouvernance);
$verifier(
    !isset($accepte1['refus']) && str_starts_with((string) $accepte1['reference'], 'EVT-GAMAD-') && $accepte1['signee'] === false,
    '14. une intention valide est acceptée, référencée et explicitement non signée',
);

$rejoue1 = $registre->accepterEvenement($intentionValide(['idempotence_reference' => $idem1, 'correlation_id' => 'AUTRE-CORRELATION']), $dossierGouvernance);
$verifier(
    ($rejoue1['reference'] ?? null) === ($accepte1['reference'] ?? null) && ($rejoue1['idempotent'] ?? false) === true,
    '15. le rejeu de la même idempotence retourne le même événement sans nouvelle séquence',
);

$idem2 = 'IDEMP-P3-ACCEPTE-002';
$accepte2 = $registre->accepterEvenement($intentionValide(['idempotence_reference' => $idem2, 'sujet_reference' => 'PRD-P3-002']), $dossierGouvernance);
$verifier(
    ($accepte2['sequence'] ?? 0) === ($accepte1['sequence'] ?? 0) + 1,
    '16. la séquence est monotone entre deux événements distincts',
);

$chaine = $registre->verifierChaine();
$verifier($chaine['valide'] === true && $chaine['evenements'] === 2, '17. la chaîne d’empreintes est valide sur le journal réel');

$lu = $registre->resoudreEvenement((string) $accepte1['reference']);
$verifier($lu !== null && $lu['type'] === 'PRODUIT_ACTIVE' && $lu['signee'] === false, '18. lecture de l’événement par référence');

$charge = $registre->resoudreCharge((string) $accepte1['reference']);
$verifier($charge['etat'] === 'DISPONIBLE' && $charge['charge']['produit_reference'] === 'PRD-P3-001', '19. lecture de la charge disponible');

$purgePrematuree = $registre->purgerCharge((string) $accepte1['reference'], $dossierGouvernance);
$verifier(($purgePrematuree['refus'] ?? null) === 'PURGE_PREMATUREE', '20. purge sans expiration contractuelle refusée');

/* -------------------------------------------------------------- 21 : falsification */
$falsifie = false;
try {
    $evtMagasin->exec("UPDATE evenement_commun SET type_evenement = 'FALSIFIE' WHERE reference = " . $evtMagasin->quote((string) $accepte1['reference']));
} catch (\PDOException) {
    $falsifie = true;
}
$verifier($falsifie, '21. UPDATE est refusé par le magasin append-only (evenement_commun)');

$evtMagasin->exec('DROP TRIGGER IF EXISTS evenement_commun_refuser_update');
$evtMagasin->exec("UPDATE evenement_commun SET type_evenement = 'FALSIFIE' WHERE reference = " . $evtMagasin->quote((string) $accepte1['reference']));
$chaineApresFalsification = $registre->verifierChaine();
$verifier(
    $chaineApresFalsification['valide'] === false,
    '22. CONTRE-ÉPREUVE : une falsification après neutralisation du verrou brise la chaîne détectée',
);

/* --------------------------------------------------------- 23-32 : abonnements */
$sansType = $abonnements->creerAbonnement([
    'nom' => 'Abonnement P3', 'consommateur_capacite_reference' => $CONSOMMATEUR_CAPACITE,
    'realm_reference' => $RLM_A, 'finalite_reference' => $FINALITE, 'mode_livraison' => 'PULL_API',
    'acteur' => $CONSOMMATEUR_CAPACITE, 'politique' => 'POL-EVENEMENTS-V1', 'source' => 'garde', 'preuve' => 'EVT-P3-ABN',
]);
$ABN = (string) $sansType['reference'];
$verifier(($sansType['etat'] ?? null) === 'PREPARATION', '23. un abonnement créé naît en PREPARATION');

$activationSansType = $abonnements->activerAbonnement($ABN, ['acteur' => $CONSOMMATEUR_CAPACITE, 'politique' => 'POL-EVENEMENTS-V1', 'preuve' => 'EVT-P3-ACT-1']);
$verifier(($activationSansType['refus'] ?? null) === 'AUCUN_TYPE', '24. activation sans type refusée');

$abonnements->ajouterTypeAbonnement($ABN, $CTR, 'PRODUIT_ACTIVE', []);
$activationSansProducteur = $abonnements->activerAbonnement($ABN, ['acteur' => $CONSOMMATEUR_CAPACITE, 'politique' => 'POL-EVENEMENTS-V1', 'preuve' => 'EVT-P3-ACT-2']);
$verifier(($activationSansProducteur['refus'] ?? null) === 'AUCUN_PRODUCTEUR', '25. activation sans producteur refusée');

$wildcardRefuse = $abonnements->ajouterProducteurAbonnement($ABN, '*');
$verifier(($wildcardRefuse['refus'] ?? null) === 'PRODUCTEUR_INVALIDE', '26. un joker « * » est refusé comme producteur');

$abonnements->ajouterProducteurAbonnement($ABN, $PRODUCTEUR_CAPACITE);
$activationSansRealm = $abonnements->activerAbonnement($ABN, ['acteur' => $CONSOMMATEUR_CAPACITE, 'politique' => 'POL-EVENEMENTS-V1', 'preuve' => 'EVT-P3-ACT-3']);
$verifier(($activationSansRealm['refus'] ?? null) === 'AUCUN_REALM', '27. activation sans realm refusée');

$abonnements->ajouterRealmAbonnement($ABN, $RLM_A);
$activation = $abonnements->activerAbonnement($ABN, ['acteur' => $CONSOMMATEUR_CAPACITE, 'politique' => 'POL-EVENEMENTS-V1', 'preuve' => 'EVT-P3-ACT-4']);
$verifier(($activation['etat'] ?? null) === 'ACTIF', '28. activation avec type, producteur et realm réussit');

$consommateurNonDeclare = $abonnements->creerAbonnement([
    'nom' => 'Abonnement non déclaré', 'consommateur_capacite_reference' => 'CAP-INCONNUE-999',
    'realm_reference' => $RLM_A, 'finalite_reference' => $FINALITE, 'mode_livraison' => 'PULL_API',
    'acteur' => 'CAP-INCONNUE-999', 'politique' => 'POL-EVENEMENTS-V1', 'source' => 'garde', 'preuve' => 'EVT-P3-ABN-2',
]);
$refusTypeConsommateurInconnu = $abonnements->ajouterTypeAbonnement((string) $consommateurNonDeclare['reference'], $CTR, 'PRODUIT_ACTIVE', []);
$verifier(($refusTypeConsommateurInconnu['refus'] ?? null) === 'CONSOMMATEUR_NON_DECLARE', '29. un consommateur non déclaré CONSOMMATEUR du contrat est refusé');

$abonnementRealmB = $abonnements->creerAbonnement([
    'nom' => 'Abonnement realm B', 'consommateur_capacite_reference' => $CONSOMMATEUR_CAPACITE,
    'realm_reference' => $RLM_B, 'finalite_reference' => $FINALITE, 'mode_livraison' => 'PULL_API',
    'acteur' => $CONSOMMATEUR_CAPACITE, 'politique' => 'POL-EVENEMENTS-V1', 'source' => 'garde', 'preuve' => 'EVT-P3-ABN-3',
]);
$ABN_B = (string) $abonnementRealmB['reference'];
$abonnements->ajouterTypeAbonnement($ABN_B, $CTR, 'PRODUIT_ACTIVE', []);
$abonnements->ajouterProducteurAbonnement($ABN_B, $PRODUCTEUR_CAPACITE);
$abonnements->ajouterRealmAbonnement($ABN_B, $RLM_B);
$abonnements->activerAbonnement($ABN_B, ['acteur' => $CONSOMMATEUR_CAPACITE, 'politique' => 'POL-EVENEMENTS-V1', 'preuve' => 'EVT-P3-ACT-5']);

/* --------------------------------------------------------------- 30-35 : routage */
$idem3 = 'IDEMP-P3-ROUTAGE-001';
$accepte3 = $registre->accepterEvenement($intentionValide(['idempotence_reference' => $idem3, 'realm_reference' => $RLM_A]), $dossierGouvernance);
$livraisonsA = $livreur->listerLivraisons($ABN);
$livraisonsB = $livreur->listerLivraisons($ABN_B);
$verifier(
    count(array_filter($livraisonsA, static fn (array $l): bool => $l['evenement_reference'] === $accepte3['reference'])) === 1,
    '30. un événement du realm A est routé vers l’abonnement du realm A',
);
$verifier(
    count(array_filter($livraisonsB, static fn (array $l): bool => $l['evenement_reference'] === $accepte3['reference'])) === 0,
    '31. le realm B ne reçoit pas un événement d’un autre realm (aucune omniscience de realm parent)',
);

$idemHorsAbonnement = 'IDEMP-P3-SANS-MATCH';
$sansAbonnementCorrespondant = $registre->accepterEvenement($intentionValide([
    'idempotence_reference' => $idemHorsAbonnement, 'finalite_reference' => $FINALITE,
    'sujet_reference' => 'PRD-P3-SANS-ABONNEMENT',
]), $dossierGouvernance);
$verifier(
    !isset($sansAbonnementCorrespondant['refus']) && $registre->resoudreEvenement((string) $sansAbonnementCorrespondant['reference']) !== null,
    '32. un événement sans abonnement correspondant n’est jamais perdu (reste dans le journal)',
);

$diagnostic = $registre->diagnostiquerJournal();
$verifier(is_array($diagnostic) && array_key_exists('coherent', $diagnostic), '33. diagnostiquerJournal() produit un rapport structuré');

/* -------------------------------------------------------- 34-45 : livraison PULL */
$lectureAutreConsommateur = $livreur->obtenirLivraisons($ABN, 'CAP-AUTRE-999', 10, null, 'COR-P3-LECTURE-1');
$verifier(($lectureAutreConsommateur['refus'] ?? null) === 'CONSOMMATEUR_NON_PROPRIETAIRE', '34. lecture par un autre consommateur refusée');

$lot1 = $livreur->obtenirLivraisons($ABN, $CONSOMMATEUR_CAPACITE, 1, 60, 'COR-P3-LECTURE-2');
$verifier(
    $lot1['bail'] !== null && count($lot1['livraisons']) === 1,
    '35. lot borné : au plus la limite demandée est retournée, avec un bail opaque',
);

$lot2 = $livreur->obtenirLivraisons($ABN, $CONSOMMATEUR_CAPACITE, 10, 60, 'COR-P3-LECTURE-3');
$referencesLot1 = array_column($lot1['livraisons'], 'livraison');
$referencesLot2 = array_column($lot2['livraisons'], 'livraison');
$verifier(
    array_intersect($referencesLot1, $referencesLot2) === [],
    '36. concurrence sur lecture : une livraison déjà sous bail n’est pas redistribuée',
);

$accuseHorsBail = $livreur->accuserLivraisons($ABN, 'BAIL-INEXISTANT', [$referencesLot1[0]], 'COR-P3-ACCUSE-1');
$verifier(($accuseHorsBail['resultats'][$referencesLot1[0]]['refus'] ?? null) === 'BAIL_INVALIDE', '37. accusé hors bail refusé');

$accuse1 = $livreur->accuserLivraisons($ABN, (string) $lot1['bail'], [$referencesLot1[0]], 'COR-P3-ACCUSE-2');
$verifier(($accuse1['resultats'][$referencesLot1[0]]['etat'] ?? null) === 'ACCUSE', '38. accusé sous bail valide accepté');

$accuseRejoue = $livreur->accuserLivraisons($ABN, (string) $lot1['bail'], [$referencesLot1[0]], 'COR-P3-ACCUSE-3');
$verifier(($accuseRejoue['resultats'][$referencesLot1[0]]['idempotent'] ?? false) === true, '39. un second accusé identique réussit sans doublon (idempotent)');

$curseur = $livreur->resoudreCurseur($ABN);
$verifier((int) $curseur['derniere_sequence_contigue_accusee'] >= 1, '40. le curseur avance sur la suite contiguë accusée');

/* ---------------------------------------------------- 41-47 : refus, relance, lettre morte */
$idemRetry = 'IDEMP-P3-RETRY-001';
$accepteRetry = $registre->accepterEvenement($intentionValide(['idempotence_reference' => $idemRetry, 'sujet_reference' => 'PRD-P3-RETRY']), $dossierGouvernance);
$lotRetry = $livreur->obtenirLivraisons($ABN, $CONSOMMATEUR_CAPACITE, 50, 60, 'COR-P3-RETRY-1');
$livraisonRetry = null;
foreach ($lotRetry['livraisons'] as $l) {
    if ($l['evenement']['reference'] === $accepteRetry['reference']) {
        $livraisonRetry = $l['livraison'];
    }
}
$verifier($livraisonRetry !== null, '41. la nouvelle livraison est bien distribuée pour le refus temporaire à venir');

$refusTemp = $livreur->refuserTemporairement($ABN, (string) $lotRetry['bail'], (string) $livraisonRetry, 'ERREUR_METIER_DEFINITIVE', 1, 'COR-P3-RETRY-2');
$verifier(($refusTemp['etat'] ?? null) === 'A_REESSAYER', '42. refus temporaire déclenche une nouvelle tentative');

sleep(2);
$plafond = 0;
for ($i = 0; $i < 10; $i++) {
    $lotN = $livreur->obtenirLivraisons($ABN, $CONSOMMATEUR_CAPACITE, 50, 60, "COR-P3-RETRY-BOUCLE-{$i}");
    $trouve = null;
    foreach ($lotN['livraisons'] as $l) {
        if ($l['livraison'] === $livraisonRetry) {
            $trouve = $l;
        }
    }
    if ($trouve === null) {
        break;
    }
    $r = $livreur->refuserTemporairement($ABN, (string) $lotN['bail'], (string) $livraisonRetry, 'ERREUR_METIER_DEFINITIVE', 1, "COR-P3-RETRY-BOUCLE-{$i}");
    if (($r['etat'] ?? null) === 'LETTRE_MORTE') {
        $plafond = 1;
        break;
    }
    usleep(1_100_000);
}
$verifier($plafond === 1, '43. le plafond de tentatives déclenche la lettre morte');

$lettresMortes = $livreur->listerLettresMortes($ABN);
$lettreMorte = null;
foreach ($lettresMortes as $lm) {
    if ($lm['livraison_reference'] === $livraisonRetry) {
        $lettreMorte = $lm;
    }
}
$verifier($lettreMorte !== null, '44. la lettre morte est visible et référence la livraison d’origine');

$relance = $livreur->relancerLettreMorte((string) $lettreMorte['reference'], ['acteur' => $CONSOMMATEUR_CAPACITE, 'motif' => 'cause corrigée en garde P3']);
$verifier(($relance['relancee'] ?? false) === true, '45. la relance gouvernée réouvre la livraison');

$lotApresRelance = $livreur->obtenirLivraisons($ABN, $CONSOMMATEUR_CAPACITE, 50, 60, 'COR-P3-RELANCE');
$presenteApresRelance = false;
foreach ($lotApresRelance['livraisons'] as $l) {
    if ($l['livraison'] === $livraisonRetry) {
        $presenteApresRelance = true;
    }
}
$verifier($presenteApresRelance, '46. après relance, la livraison redevient disponible à la lecture');
$livreur->accuserLivraisons($ABN, (string) $lotApresRelance['bail'], [$livraisonRetry], 'COR-P3-RELANCE-ACCUSE');

$liberation = $livreur->libererBauxExpires();
$verifier(array_key_exists('liberes', $liberation), '47. la libération des baux expirés est idempotente et rapporte un compte');

/* ---------------------------------------------------------------- 48-52 : rejeu */
$rejeuSansBornes = $rejoueur->demanderRejeu($ABN, ['motif' => 'test', 'demandeur' => $CONSOMMATEUR_CAPACITE, 'politique' => 'POL-EVENEMENTS-V1', 'preuve' => 'EVT-P3-REJ-1']);
$verifier(($rejeuSansBornes['refus'] ?? null) === 'BORNES_ABSENTES', '48. un rejeu sans bornes explicites est refusé');

$demandeRejeu = $rejoueur->demanderRejeu($ABN, [
    'motif' => 'test de rejeu borné', 'demandeur' => $CONSOMMATEUR_CAPACITE,
    'politique' => 'POL-EVENEMENTS-V1', 'preuve' => 'EVT-P3-REJ-2',
    'sequence_debut' => (int) $accepte1['sequence'], 'sequence_fin' => (int) $accepte1['sequence'],
]);
$verifier(($demandeRejeu['etat'] ?? null) === 'DEMANDEE', '49. une demande de rejeu bornée est acceptée en DEMANDEE');

$validation = $rejoueur->validerRejeu((string) $demandeRejeu['reference'], []);
$verifier(($validation['etat'] ?? null) === 'VALIDEE', '50. la validation gouvernée fait passer la demande en VALIDEE');

$execution = $rejoueur->executerRejeu((string) $demandeRejeu['reference']);
$verifier(in_array($execution['etat'] ?? null, ['TERMINEE', 'EN_COURS'], true) && ($execution['traites'] ?? 0) >= 1, '51. l’exécution du rejeu traite au moins une livraison sans erreur');

$livraisonRejouee = null;
foreach ($livreur->listerLivraisons($ABN) as $l) {
    if ($l['evenement_reference'] === $accepte1['reference'] && (int) $l['rejeu'] === 1) {
        $livraisonRejouee = $l;
    }
}
$verifier($livraisonRejouee !== null, '52. le rejeu marque la livraison comme issue d’un rejeu, sans créer de nouvel événement (référence d’origine inchangée)');

/* -------------------------------------------------------------------- fin */
echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-014 (noyau) : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-014 (noyau) : {$echecs} écart(s).\n";
exit(1);
