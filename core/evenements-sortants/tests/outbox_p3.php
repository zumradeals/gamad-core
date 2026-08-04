<?php

declare(strict_types=1);

/**
 * Garde de comportement de l'outbox transactionnelle (CAP-CORE-014) et de
 * son premier producteur réel raccordé : CAP-CORE-011 (registre des
 * produits).
 *
 * Éprouve `OutboxProducteur`, `RelaisOutbox` et le raccordement conditionnel
 * de `RegistreProduits::activerProduit()/suspendreProduit()/retirerProduit()`
 * — facultatif tant que l'appelant ne fournit pas `realm_reference`, pour ne
 * jamais casser un appelant existant qui ignore CAP-CORE-014.
 *
 * Exécution : php core/evenements-sortants/tests/outbox_p3.php
 */

use Gamad\EvenementsSortants\OutboxProducteur;
use Gamad\EvenementsSortants\RelaisOutbox;
use Gamad\EvenementsSortants\SchemaOutbox;
use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\RegistreEvenements;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\PolitiqueContrats;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;
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
require __DIR__ . '/../src/SchemaOutbox.php';
require __DIR__ . '/../src/OutboxProducteur.php';
require __DIR__ . '/../../registre-produits/src/PolitiqueProduits.php';
require __DIR__ . '/../../registre-produits/src/SchemaProduits.php';
require __DIR__ . '/../../registre-produits/src/Magasin.php';
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
require __DIR__ . '/../src/RelaisOutbox.php';

$prefixe = sys_get_temp_dir() . '/gamad-outbox-p3-' . getmypid();
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
        'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-OUTBOX-P3',
        'source' => 'garde CAP-CORE-014', 'preuve' => 'EVT-P3-IDN-' . strtoupper(bin2hex(random_bytes(4))),
    ]);
    if (isset($identite['refus'])) {
        throw new RuntimeException('inscription identité impossible : ' . json_encode($identite));
    }

    return (string) $identite['reference'];
};

$produitsMagasin = ProduitsMagasin::connecter($fichiers['produits']);
$produits = new RegistreProduits($index, $registreIdentites, $produitsMagasin, $ctr01);

$sourcesMagasin = SourcesMagasin::connecter($fichiers['sources']);
$sources = new RegistreSources($index, $registreIdentites, $sourcesMagasin, $produitsMagasin, $ctr01, $produits);
$gSource = static fn (): array => [
    'politique' => PolitiqueSources::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-014', 'preuve' => 'EVT-P3-SRC-' . strtoupper(bin2hex(random_bytes(4))),
];
$SRC = PolitiqueProduits::SOURCE_EVENEMENTS_REFERENCE;
$sources->inscrireSource(array_merge($gSource(), [
    'reference' => $SRC, 'nom_canonique' => 'source-p3-outbox', 'nom_affichage' => 'Source P3 Outbox',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
]));
$sources->activerSource($SRC, $gSource());
$FINALITE = PolitiqueProduits::FINALITE_EVENEMENTS_DEFAUT;
$sources->declarerFinalite($SRC, array_merge($gSource(), ['finalite_reference' => $FINALITE]));

$realmsMagasin = RealmsMagasin::connecter($fichiers['realms']);
$realms = new RegistreRealms($index, $registreIdentites, $realmsMagasin, $ctr01);
$gRealm = static fn (): array => [
    'politique' => PolitiqueRealms::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-014', 'preuve' => 'EVT-P3-RLM-' . strtoupper(bin2hex(random_bytes(4))),
];
$idnRealm = $inscrireIdentite('realm', 'Realm P3 Outbox');
$insRealm = $realms->inscrireRealm(array_merge($gRealm(), [
    'identite_reference' => $idnRealm, 'code_canonique' => 'RLM-P3-OUTBOX', 'type_realm_reference' => 'TECHNIQUE',
    'nom_affichage' => 'Realm P3 Outbox', 'classification_reference' => 'INTERNE',
]));
$RLM = (string) $insRealm['reference'];
$realms->activerRealm($RLM, $gRealm());

$contratsMagasin = ContratsMagasin::connecter($fichiers['contrats']);
$contrats = new RegistreContrats($index, $registreIdentites, $contratsMagasin, $ctr01);
$gContrat = static fn (): array => [
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'garde CAP-CORE-014', 'preuve' => 'EVT-P3-CTR-' . strtoupper(bin2hex(random_bytes(4))),
];
$activerContratEvenement = static function (string $ref) use ($contrats, $gContrat, $FINALITE): void {
    $contrats->inscrireContrat(array_merge($gContrat(), [
        'reference' => $ref, 'nom' => $ref, 'type_contrat' => 'EVENEMENT',
        'finalite_reference' => $FINALITE, 'producteur_capacite_reference' => 'CAP-CORE-011',
        'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'garde CAP-CORE-014',
    ]));
    $contrats->creerVersion($ref, array_merge($gContrat(), ['version' => '1.0.0', 'compatibilite_annoncee' => 'COMPATIBLE']));
    $contrats->declarerPartie($ref, '1.0.0', array_merge($gContrat(), ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-011']));
    $contrats->declarerOperation($ref, '1.0.0', array_merge($gContrat(), ['reference_operation' => 'op', 'type_operation' => 'PUBLIER', 'idempotente' => true]));
    $contrats->declarerSchema($ref, '1.0.0', array_merge($gContrat(), [
        'operation_reference' => 'op', 'sens' => 'EVENEMENT', 'format' => 'JSON_SCHEMA',
        'contenu' => json_encode(['proprietes' => ['produit_reference' => ['type' => 'string', 'requis' => true]]]),
    ]));
    $contrats->soumettreVersion($ref, '1.0.0', $gContrat());
    $contrats->analyserCompatibilite($ref, '1.0.0', $gContrat());
    $contrats->enregistrerConformite($ref, '1.0.0', array_merge($gContrat(), ['resultat' => 'CONFORME', 'artefact_reference' => 'garde-p3']));
    $activation = $contrats->activerVersion($ref, '1.0.0', $gContrat());
    if (isset($activation['refus'])) {
        throw new RuntimeException("activation du contrat {$ref} impossible : " . json_encode($activation));
    }
};
$activerContratEvenement('EVT-GAMAD-PRODUIT-ACTIVE');
$activerContratEvenement('EVT-GAMAD-PRODUIT-SUSPENDU');
$activerContratEvenement('EVT-GAMAD-PRODUIT-RETIRE');

$evtMagasin = EvenementsMagasin::connecter($fichiers['evenements']);
$registreCentral = new RegistreEvenements($evtMagasin, $contrats, $sources, $realms);
$dossierRelais = ['politique' => 'POL-EVENEMENTS-V1', 'producteur' => 'CAP-CORE-011', 'source' => 'garde', 'preuve' => 'EVT-P3-RELAIS'];

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — OUTBOX PRODUCTEUR ET PILOTE CAP-CORE-011 (CAP-CORE-014)\n\n";

/* --------------------------------------------------------- 1-4 : OutboxProducteur transactionnel */
$magasinTest = new \PDO('sqlite::memory:');
$magasinTest->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
SchemaOutbox::migrer($magasinTest);

$avant = (int) $magasinTest->query('SELECT COUNT(*) FROM evenement_sortant')->fetchColumn();
$magasinTest->beginTransaction();
OutboxProducteur::preparerEvenement($magasinTest, [
    'type_evenement' => 'TEST_EVENEMENT', 'contrat_reference' => 'EVT-TEST', 'contrat_version' => '1.0.0',
    'producteur_capacite_reference' => 'CAP-CORE-999', 'source_reference' => 'SRC-TEST', 'realm_reference' => 'RLM-TEST',
    'finalite_reference' => 'FIN-TEST', 'correlation_id' => 'COR-TEST', 'survenu_le' => gmdate('c'),
    'classification' => 'INTERNE', 'idempotence_reference' => 'IDEMP-ROLLBACK-TEST', 'charge' => [],
]);
$magasinTest->rollBack();
$apresRollback = (int) $magasinTest->query('SELECT COUNT(*) FROM evenement_sortant')->fetchColumn();
$verifier($apresRollback === $avant, '1. un rollback métier ne laisse aucune ligne d’outbox');

$magasinTest->beginTransaction();
OutboxProducteur::preparerEvenement($magasinTest, [
    'type_evenement' => 'TEST_EVENEMENT', 'contrat_reference' => 'EVT-TEST', 'contrat_version' => '1.0.0',
    'producteur_capacite_reference' => 'CAP-CORE-999', 'source_reference' => 'SRC-TEST', 'realm_reference' => 'RLM-TEST',
    'finalite_reference' => 'FIN-TEST', 'correlation_id' => 'COR-TEST', 'survenu_le' => gmdate('c'),
    'classification' => 'INTERNE', 'idempotence_reference' => 'IDEMP-COMMIT-TEST', 'charge' => [],
]);
$magasinTest->commit();
$apresCommit = (int) $magasinTest->query('SELECT COUNT(*) FROM evenement_sortant')->fetchColumn();
$verifier($apresCommit === $avant + 1, '2. un commit métier laisse une ligne d’outbox EN_ATTENTE');

$etatLigne = $magasinTest->query('SELECT etat FROM evenement_sortant WHERE idempotence_reference = ' . $magasinTest->quote('IDEMP-COMMIT-TEST'))->fetchColumn();
$verifier($etatLigne === 'EN_ATTENTE', '3. la ligne créée démarre en EN_ATTENTE, jamais publiée avant le relais');

$rejeuPreparation = OutboxProducteur::preparerEvenement($magasinTest, [
    'type_evenement' => 'TEST_EVENEMENT', 'contrat_reference' => 'EVT-TEST', 'contrat_version' => '1.0.0',
    'producteur_capacite_reference' => 'CAP-CORE-999', 'source_reference' => 'SRC-TEST', 'realm_reference' => 'RLM-TEST',
    'finalite_reference' => 'FIN-TEST', 'correlation_id' => 'COR-TEST', 'survenu_le' => gmdate('c'),
    'classification' => 'INTERNE', 'idempotence_reference' => 'IDEMP-COMMIT-TEST', 'charge' => [],
]);
$verifier(($rejeuPreparation['idempotent'] ?? false) === true, '4. préparer deux fois la même idempotence ne crée pas de doublon d’outbox');

/* -------------------------------------------------- 5-9 : pilote réel CAP-CORE-011 */
$idnProduit = $inscrireIdentite('produit', 'Produit Pilote P3');
$refProduit = 'PRD-P3-OUTBOX-PILOTE';
$produits->inscrireProduit([
    'reference' => $refProduit, 'identite_reference' => $idnProduit,
    'nom_canonique' => 'produit-p3-outbox-pilote', 'nom_affichage' => 'Produit Pilote P3',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $AUTORITE,
    'source' => 'garde CAP-CORE-014', 'producteur' => $AUTORITE,
    'politique' => PolitiqueProduits::POLITIQUE, 'preuve' => 'EVT-P3-PRD-INSCRIPTION',
]);

$activationSansRealm = $produits->activerProduit($refProduit, [
    'producteur' => $AUTORITE, 'preuve' => 'EVT-P3-PRD-ACTIVATION-SANS-REALM',
    'politique' => PolitiqueProduits::POLITIQUE, 'source' => PolitiqueProduits::SOURCE,
]);
$compteOutboxSansRealm = (int) $produitsMagasin->query(
    "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='evenement_sortant'"
)->fetchColumn();
$verifier(
    ($activationSansRealm['etat'] ?? null) === 'ACTIF' && $compteOutboxSansRealm === 0,
    '5. sans realm_reference explicite, l’activation réussit sans jamais créer la table d’outbox (facultatif, aucun effet de bord)',
);

$refProduit2 = 'PRD-P3-OUTBOX-PILOTE-2';
$idnProduit2 = $inscrireIdentite('produit', 'Produit Pilote P3 bis');
$produits->inscrireProduit([
    'reference' => $refProduit2, 'identite_reference' => $idnProduit2,
    'nom_canonique' => 'produit-p3-outbox-pilote-2', 'nom_affichage' => 'Produit Pilote P3 bis',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $AUTORITE,
    'source' => 'garde CAP-CORE-014', 'producteur' => $AUTORITE,
    'politique' => PolitiqueProduits::POLITIQUE, 'preuve' => 'EVT-P3-PRD-INSCRIPTION-2',
]);
$activationAvecRealm = $produits->activerProduit($refProduit2, [
    'producteur' => $AUTORITE, 'preuve' => 'EVT-P3-PRD-ACTIVATION-AVEC-REALM',
    'politique' => PolitiqueProduits::POLITIQUE, 'source' => PolitiqueProduits::SOURCE,
    'realm_reference' => $RLM,
]);
$verifier(($activationAvecRealm['etat'] ?? null) === 'ACTIF', '6. avec realm_reference fourni, l’activation réussit toujours');

$ligneOutbox = $produitsMagasin->query(
    "SELECT * FROM evenement_sortant WHERE type_evenement = 'PRODUIT_ACTIVE' ORDER BY id DESC LIMIT 1"
)->fetch();
$verifier(
    $ligneOutbox !== false && $ligneOutbox['etat'] === 'EN_ATTENTE' && $ligneOutbox['contrat_reference'] === 'EVT-GAMAD-PRODUIT-ACTIVE',
    '7. l’activation crée réellement une ligne d’outbox EN_ATTENTE, dans la même transaction métier',
);

$relais = new RelaisOutbox($produitsMagasin, $registreCentral);
$publication = $relais->publierOutbox($dossierRelais);
$verifier(($publication['publies'] ?? 0) === 1, '8. le relais publie la ligne d’outbox vers le journal central');

$ligneOutboxApres = $produitsMagasin->query(
    "SELECT * FROM evenement_sortant WHERE type_evenement = 'PRODUIT_ACTIVE' ORDER BY id DESC LIMIT 1"
)->fetch();
$verifier(
    $ligneOutboxApres['etat'] === 'PUBLIE' && is_string($ligneOutboxApres['evenement_reference']) && str_starts_with((string) $ligneOutboxApres['evenement_reference'], 'EVT-GAMAD-'),
    '9. la ligne publiée porte la référence de l’événement central accepté',
);

$evenementCentral = $registreCentral->resoudreEvenement((string) $ligneOutboxApres['evenement_reference']);
$verifier(
    $evenementCentral !== null && $evenementCentral['sujet_reference'] === $refProduit2,
    '10. l’événement central référence bien le produit sujet de l’activation',
);

/* ------------------------------------------------ 11-13 : idempotence et rejeu du relais */
$publicationRejouee = $relais->publierOutbox($dossierRelais);
$verifier(($publicationRejouee['lot'] ?? -1) === 0, '11. une ligne déjà PUBLIE n’est plus sélectionnée par un relais rejoué');

$suspension = $produits->suspendreProduit($refProduit2, [
    'producteur' => $AUTORITE, 'preuve' => 'EVT-P3-PRD-SUSPENSION',
    'politique' => PolitiqueProduits::POLITIQUE, 'source' => PolitiqueProduits::SOURCE,
    'realm_reference' => $RLM,
]);
$verifier(($suspension['etat'] ?? null) === 'SUSPENDU', '12. la suspension réussit et prépare à son tour un événement');

$publicationSuspension = $relais->publierOutbox($dossierRelais);
$verifier(($publicationSuspension['publies'] ?? 0) === 1, '13. le relais publie également l’événement de suspension');

/* -------------------------------------------------------- 14 : échec définitif classifié */
$magasinTest->exec("INSERT INTO evenement_sortant
    (idempotence_reference,type_evenement,contrat_reference,contrat_version,source_reference,realm_reference,
     finalite_reference,correlation_id,survenu_le,classification,charge_json,charge_empreinte,etat,tentatives,cree_le)
    VALUES('IDEMP-P3-DEFINITIF','TYPE_X','EVT-CONTRAT-INCONNU','1.0.0','SRC-X','RLM-X','FIN-X','COR-X','" . gmdate('c') . "','INTERNE','{}','" . hash('sha256', '{}') . "',
    'EN_ATTENTE',0,'" . gmdate('c') . "')");
$relaisTest = new RelaisOutbox($magasinTest, $registreCentral);
$relaisTest->publierOutbox($dossierRelais);
$etatDefinitif = $magasinTest->query("SELECT etat FROM evenement_sortant WHERE idempotence_reference = 'IDEMP-P3-DEFINITIF'")->fetchColumn();
$verifier($etatDefinitif === 'ECHEC_DEFINITIF', '14. un contrat inconnu classe l’échec en ECHEC_DEFINITIF, jamais retenté indéfiniment');

echo "\n";
if ($echecs === 0) {
    echo "Garde outbox + pilote CAP-CORE-011 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde outbox + pilote CAP-CORE-011 : {$echecs} écart(s).\n";
exit(1);
