<?php

declare(strict_types=1);

/**
 * Garde de comportement de `AcquisitionSignaux` (CAP-CORE-021) : parcours
 * réel de bout en bout à travers le vrai CAP-CORE-014 — un contrat
 * `EVENEMENT`, un abonnement `PULL_API` réellement activé, un événement
 * réellement accepté dans le journal central, tiré, matérialisé en
 * `matching_signal`, accusé.
 *
 * Réserve documentée dans `AcquisitionSignaux` : aucun producteur réel
 * n'existe encore dans le Core. Le producteur ici (`CAP-P5-PRODUCTEUR`) et
 * la forme de charge sont synthétiques, construits pour éprouver la
 * tuyauterie elle-même, pas pour simuler un pilote réel.
 *
 * Exécution : php core/moteur-matching/tests/matching_p5.php
 */

use Gamad\JournalEvenements\LivreurEvenements;
use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\RegistreAbonnements;
use Gamad\JournalEvenements\RegistreEvenements;
use Gamad\MoteurMatching\AcquisitionSignaux;
use Gamad\MoteurMatching\Magasin as MatchingMagasin;
use Gamad\MoteurMatching\RegistreMatching;
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
require __DIR__ . '/../../journal-evenements/src/ExceptionEvenement.php';
require __DIR__ . '/../../journal-evenements/src/PolitiqueEvenements.php';
require __DIR__ . '/../../journal-evenements/src/EnveloppeEvenement.php';
require __DIR__ . '/../../journal-evenements/src/ValidateurEvenement.php';
require __DIR__ . '/../../journal-evenements/src/SchemaEvenements.php';
require __DIR__ . '/../../journal-evenements/src/Magasin.php';
require __DIR__ . '/../../journal-evenements/src/RegistreEvenements.php';
require __DIR__ . '/../../journal-evenements/src/RouteurEvenements.php';
require __DIR__ . '/../../journal-evenements/src/RegistreAbonnements.php';
require __DIR__ . '/../../journal-evenements/src/LivreurEvenements.php';
require __DIR__ . '/../../registre-preuves/src/Canonicaliseur.php';
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
require __DIR__ . '/../src/AcquisitionSignaux.php';

$prefixe = sys_get_temp_dir() . '/gamad-matching-p5-' . getmypid();
$fichiers = [
    'index' => $prefixe . '-index.sqlite', 'identites' => $prefixe . '-identites.sqlite',
    'produits' => $prefixe . '-produits.sqlite', 'sources' => $prefixe . '-sources.sqlite',
    'contrats' => $prefixe . '-contrats.sqlite', 'realms' => $prefixe . '-realms.sqlite',
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
        'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-MATCHING-P5',
        'source' => 'garde CAP-CORE-021', 'preuve' => 'EVT-P5-IDN-' . strtoupper(bin2hex(random_bytes(4))),
    ]);

    return (string) $identite['reference'];
};

$produitsMagasin = ProduitsMagasin::connecter($fichiers['produits']);
$sourcesMagasin = SourcesMagasin::connecter($fichiers['sources']);
$sources = new RegistreSources($index, $registreIdentites, $sourcesMagasin, $produitsMagasin, $ctr01);
$gSource = static fn (): array => [
    'politique' => PolitiqueSources::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-021', 'preuve' => 'EVT-P5-SRC-' . strtoupper(bin2hex(random_bytes(4))),
];
$SRC = 'SRC-GAMAD-P5-TEST';
$sources->inscrireSource(array_merge($gSource(), [
    'reference' => $SRC, 'nom_canonique' => 'source-p5-test', 'nom_affichage' => 'Source P5 Test',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
]));
$sources->activerSource($SRC, $gSource());
$sources->declarerFinalite($SRC, array_merge($gSource(), ['finalite_reference' => 'FINALITE-SIGNAUX-MATCHING-P5']));

$realmsMagasin = RealmsMagasin::connecter($fichiers['realms']);
$realms = new RegistreRealms($index, $registreIdentites, $realmsMagasin, $ctr01);
$gRealm = static fn (): array => [
    'politique' => PolitiqueRealms::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-021', 'preuve' => 'EVT-P5-RLM-' . strtoupper(bin2hex(random_bytes(4))),
];
$idnRealm = $inscrireIdentite('realm', 'Realm P5 Matching');
$insRealm = $realms->inscrireRealm(array_merge($gRealm(), [
    'identite_reference' => $idnRealm, 'code_canonique' => 'RLM-P5-MATCHING', 'type_realm_reference' => 'TECHNIQUE',
    'nom_affichage' => 'RLM-P5-MATCHING', 'classification_reference' => 'INTERNE',
]));
$RLM = (string) $insRealm['reference'];
$realms->activerRealm($RLM, $gRealm());

$contratsMagasin = ContratsMagasin::connecter($fichiers['contrats']);
$contrats = new RegistreContrats($index, $registreIdentites, $contratsMagasin, $ctr01);
$gContrat = static fn (): array => [
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-021', 'preuve' => 'EVT-P5-CTR-' . strtoupper(bin2hex(random_bytes(4))),
];
$FINALITE = 'FINALITE-SIGNAUX-MATCHING-P5';
$CTR = 'EVT-GAMAD-SIGNAL-MATCHING-P5';
$PRODUCTEUR = 'CAP-P5-PRODUCTEUR';
$CONSOMMATEUR = 'CAP-CORE-021';
$contrats->inscrireContrat(array_merge($gContrat(), [
    'reference' => $CTR, 'nom' => 'Signal normalisé de test — matching_p5', 'type_contrat' => 'EVENEMENT',
    'finalite_reference' => $FINALITE, 'producteur_capacite_reference' => $PRODUCTEUR,
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'garde CAP-CORE-021',
]));
$contrats->creerVersion($CTR, array_merge($gContrat(), ['version' => '1.0.0', 'compatibilite_annoncee' => 'COMPATIBLE']));
$contrats->declarerPartie($CTR, '1.0.0', array_merge($gContrat(), ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => $PRODUCTEUR]));
$contrats->declarerPartie($CTR, '1.0.0', array_merge($gContrat(), ['role' => 'CONSOMMATEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => $CONSOMMATEUR]));
$contrats->declarerOperation($CTR, '1.0.0', array_merge($gContrat(), ['reference_operation' => 'signalNormalise', 'type_operation' => 'PUBLIER', 'idempotente' => true]));
$contrats->declarerSchema($CTR, '1.0.0', array_merge($gContrat(), [
    'operation_reference' => 'signalNormalise', 'sens' => 'EVENEMENT', 'format' => 'JSON_SCHEMA',
    'contenu' => json_encode(['proprietes' => ['signal_code' => ['type' => 'string', 'requis' => true]]]),
]));
$contrats->soumettreVersion($CTR, '1.0.0', $gContrat());
$contrats->analyserCompatibilite($CTR, '1.0.0', $gContrat());
$contrats->enregistrerConformite($CTR, '1.0.0', array_merge($gContrat(), ['resultat' => 'CONFORME', 'artefact_reference' => 'garde-p5']));
$activationCtr = $contrats->activerVersion($CTR, '1.0.0', $gContrat());
if (isset($activationCtr['refus'])) {
    throw new RuntimeException('activation du contrat pilote impossible : ' . json_encode($activationCtr));
}

$evtMagasin = EvenementsMagasin::connecter($fichiers['evenements']);
$evenements = new RegistreEvenements($evtMagasin, $contrats, $sources, $realms);
$abonnements = new RegistreAbonnements($evtMagasin, $contrats, $realms);
$livreur = new LivreurEvenements($evtMagasin, $evenements);

$gEvt = static fn (): array => [
    'politique' => 'POL-EVENEMENTS-V1', 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-021', 'preuve' => 'EVT-P5-ABN-' . strtoupper(bin2hex(random_bytes(4))),
];
$abn = $abonnements->creerAbonnement(array_merge($gEvt(), [
    'nom' => 'Abonnement signaux matching P5', 'consommateur_capacite_reference' => $CONSOMMATEUR,
    'realm_reference' => $RLM, 'finalite_reference' => $FINALITE, 'mode_livraison' => 'PULL_API',
    'acteur' => $CONSOMMATEUR,
]));
$ABN = (string) $abn['reference'];
$abonnements->ajouterTypeAbonnement($ABN, $CTR, AcquisitionSignaux::TYPE_EVENEMENT, []);
$abonnements->ajouterProducteurAbonnement($ABN, $PRODUCTEUR);
$abonnements->ajouterRealmAbonnement($ABN, $RLM);
$activationAbn = $abonnements->activerAbonnement($ABN, ['acteur' => $CONSOMMATEUR, 'politique' => 'POL-EVENEMENTS-V1', 'preuve' => 'EVT-P5-ABN-ACTIVATION']);

$matching = new RegistreMatching(MatchingMagasin::connecter(':memory:'));
$acquisition = new AcquisitionSignaux($livreur, $matching);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — ACQUISITION DE SIGNAUX DEPUIS CAP-CORE-014 (CAP-CORE-021)\n\n";

$verifier(($activationAbn['etat'] ?? null) === 'ACTIF', 'abonnement réellement activé (type, producteur et realm déclarés)');

$publierSignal = static function (string $idempotence, string $sujetReference, ?array $charge) use ($evenements, $CTR, $PRODUCTEUR, $RLM, $FINALITE): array {
    return $evenements->accepterEvenement([
        'idempotence_reference' => $idempotence, 'type_evenement' => AcquisitionSignaux::TYPE_EVENEMENT,
        'contrat_reference' => $CTR, 'contrat_version' => '1.0.0', 'producteur_capacite_reference' => $PRODUCTEUR,
        'source_reference' => 'SRC-GAMAD-P5-TEST', 'realm_reference' => $RLM, 'finalite_reference' => $FINALITE,
        'sujet_type' => 'PERSONNE', 'sujet_reference' => $sujetReference, 'correlation_id' => 'COR-P5-' . $idempotence,
        'survenu_le' => '2026-08-06T00:00:00Z', 'classification' => 'INTERNE',
        'charge' => $charge ?? ['signal_code' => 'CRT-REGION', 'valeur_type' => 'TEXTE', 'valeur_normalisee' => 'ABJ', 'valide_jusqua' => '2027-08-06T00:00:00Z'],
    ], ['politique' => 'POL-EVENEMENTS-V1', 'producteur' => $PRODUCTEUR, 'source' => 'garde CAP-CORE-021', 'preuve' => 'EVT-P5-PUB-' . $idempotence]);
};

$publie1 = $publierSignal('IDEMP-P5-001', 'CAND-P5-A', null);
$verifier(!isset($publie1['refus']), 'un événement SIGNAL_NORMALISE_DISPONIBLE est réellement accepté dans le journal central CAP-CORE-014');

// Même type d'événement (donc bien routé vers la livraison de cet abonnement),
// mais charge incomplète : la matérialisation doit refuser explicitement,
// jamais silencieusement ignorer.
$publieChargeIncomplete = $publierSignal('IDEMP-P5-002', 'CAND-P5-B', ['signal_code' => 'CRT-REGION', 'valeur_type' => 'TEXTE', 'valeur_normalisee' => 'DKR']);
$verifier(!isset($publieChargeIncomplete['refus']), 'un second événement du même type, à charge incomplète (sans valide_jusqua), est aussi accepté');

$acquisition1 = $acquisition->acquerir($ABN, $CONSOMMATEUR, 10, 'COR-P5-ACQ-1');
$verifier(!isset($acquisition1['refus']) && $acquisition1['livraisons_recues'] === 2, 'deux livraisons tirées de l’abonnement');
$verifier(count($acquisition1['signaux_materialises']) === 1, 'un seul signal matérialisé : celui à charge complète');
$verifier(count($acquisition1['livraisons_refusees']) === 1 && $acquisition1['livraisons_refusees'][0]['motif'] === 'CHARGE_INCOMPLETE', 'la livraison à charge incomplète est refusée avec un motif explicite, pas silencieusement ignorée');
$verifier(count($acquisition1['accuses']) === 1, 'seule la livraison matérialisée est accusée');

$signalMaterialiseReference = $acquisition1['signaux_materialises'][0]['signal'] ?? null;
$verifier($signalMaterialiseReference !== null && str_starts_with((string) $signalMaterialiseReference, 'SIG-GAMAD-'), 'le signal matérialisé porte une vraie référence du magasin du Matching');

// La livraison matérialisée est ACCUSE (jamais re-livrée) ; celle à charge
// incomplète est explicitement passée en A_REESSAYER avec un délai — ni
// accusée à tort, ni relivrée avant son délai, ni perdue.
$acquisition2 = $acquisition->acquerir($ABN, $CONSOMMATEUR, 10, 'COR-P5-ACQ-2');
$verifier(
    ($acquisition2['livraisons_recues'] ?? -1) === 0,
    'immédiatement après refus, aucune livraison n’est re-proposée : ni l’accusée, ni celle en attente de délai de nouvelle tentative',
);

$refusConsommateurEtranger = $acquisition->acquerir($ABN, 'CAP-AUTRE-999', 10, 'COR-P5-ETRANGER');
$verifier(isset($refusConsommateurEtranger['refus']), 'un tiers non propriétaire de l’abonnement ne peut pas tirer ses livraisons');

echo "\n";
if ($echecs === 0) {
    echo "Toutes les épreuves sont vertes.\n";
    exit(0);
}
printf("%d épreuve(s) en échec.\n", $echecs);
exit(1);
