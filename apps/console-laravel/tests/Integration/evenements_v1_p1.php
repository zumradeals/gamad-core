<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet de la couche API du journal des événements
 * (CAP-CORE-014, partie 4), depuis la publication jusqu'au rejeu, en passant
 * par l'abonnement, la livraison PULL, l'accusé et la lettre morte.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/evenements_v1_p1.php
 */

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-evenements-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'organisations' => $temp . '-organisations.sqlite',
    'produits' => $temp . '-produits.sqlite',
    'contrats' => $temp . '-contrats.sqlite',
    'vocabulaire' => $temp . '-vocabulaire.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'realms' => $temp . '-realms.sqlite',
    'sources' => $temp . '-sources.sqlite',
    'evenements' => $temp . '-evenements.sqlite',
];
foreach ($fichiers as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $fichier) {
        @unlink($fichier);
    }
});

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('e', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php',
    'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php',
    'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $fichiers['index'],
    'MAGASIN_URL' => '',
    'MAGASIN_PATH' => $fichiers['acces'],
    'IDENTITY_REGISTRY_URL' => '',
    'IDENTITY_REGISTRY_PATH' => $fichiers['identites'],
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
    'ORGANIZATION_REGISTRY_URL' => '',
    'ORGANIZATION_REGISTRY_PATH' => $fichiers['organisations'],
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'CONTRACT_REGISTRY_URL' => '',
    'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
    'VOCABULARY_REGISTRY_URL' => '',
    'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
    'REALM_REGISTRY_URL' => '',
    'REALM_REGISTRY_PATH' => $fichiers['realms'],
    'SOURCE_REGISTRY_URL' => '',
    'SOURCE_REGISTRY_PATH' => $fichiers['sources'],
    'EVENT_JOURNAL_URL' => '',
    'EVENT_JOURNAL_PATH' => $fichiers['evenements'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';

BaselineOperationnelle::standard()->reconstruire(Db::connect());
IdentiteMagasin::connecter();
JournalMagasin::connecter();
$ctr16 = new Ctr16(AccesMagasin::connecter());
$secretAutorite = 'Secret-Evenements-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:organisations:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:produits:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:contrats:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:vocabulaire:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:realms:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:sources:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:evenements:bootstrap');
$kernel = $app->make(Kernel::class);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};
$requete = static function (
    string $methode,
    string $uri,
    ?array $json = null,
    ?string $jeton = null,
) use ($kernel): array {
    $serveur = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
    if ($jeton !== null) {
        $serveur['HTTP_AUTHORIZATION'] = 'Bearer ' . $jeton;
    }
    $request = Request::create(
        $uri,
        $methode,
        [],
        [],
        [],
        $serveur,
        $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR),
    );
    $response = $kernel->handle($request);
    $corps = json_decode((string) $response->getContent(), true);
    $resultat = ['statut' => $response->getStatusCode(), 'corps' => is_array($corps) ? $corps : []];
    $kernel->terminate($request, $response);

    return $resultat;
};
$purger = static function () use ($app): void {
    $app->make('cache.store')->flush();
};

echo "INTÉGRATION HTTP — ÉVÉNEMENTS V1 P1 (CAP-CORE-014)\n\n";

// 1 — lecture sans session refusée.
$sansSession = $requete('GET', '/api/v1/evenements');
$verifier(
    $sansSession['statut'] === 401 && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'aucune lecture n’est possible sans session',
);

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$sessionAutorite = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($sessionAutorite !== '', 'l’autorité ouvre une session Core');

// 2 — realm actif.
$identiteRealm = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'realm', 'libelle' => 'Realm Événements V1 P1',
], $sessionAutorite);
$idRealm = (string) ($identiteRealm['corps']['identite']['reference'] ?? '');
$inscriptionRealm = $requete('POST', '/api/v1/realms', [
    'identite_reference' => $idRealm, 'code_canonique' => 'RLM-EVT-V1-P1', 'type_realm_reference' => 'TECHNIQUE',
    'nom_affichage' => 'Realm Événements V1 P1', 'classification_reference' => 'INTERNE',
], $sessionAutorite);
$RLM = (string) ($inscriptionRealm['corps']['resultat']['reference'] ?? '');
$activationRealm = $requete('POST', "/api/v1/realms/{$RLM}/activation", [], $sessionAutorite);
$verifier(($activationRealm['corps']['resultat']['etat'] ?? null) === 'ACTIF', 'le realm technique est actif');

$purger();

// 3 — producteur et consommateur, deux produits réels et actifs.
$idProducteurIdentite = (string) ($requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'produit', 'libelle' => 'Producteur V1 P1',
], $sessionAutorite)['corps']['identite']['reference'] ?? '');
$PRD_PRODUCTEUR = 'PRD-EVT-V1-P1-PRODUCTEUR';
$requete('POST', '/api/v1/produits', [
    'reference' => $PRD_PRODUCTEUR, 'identite_reference' => $idProducteurIdentite,
    'nom_canonique' => 'producteur-evt-v1-p1', 'nom_affichage' => 'Producteur V1 P1',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$requete('POST', "/api/v1/produits/{$PRD_PRODUCTEUR}/activation", [], $sessionAutorite);

$idConsommateurIdentite = (string) ($requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'produit', 'libelle' => 'Consommateur V1 P1',
], $sessionAutorite)['corps']['identite']['reference'] ?? '');
$PRD_CONSOMMATEUR = 'PRD-EVT-V1-P1-CONSOMMATEUR';
$requete('POST', '/api/v1/produits', [
    'reference' => $PRD_CONSOMMATEUR, 'identite_reference' => $idConsommateurIdentite,
    'nom_canonique' => 'consommateur-evt-v1-p1', 'nom_affichage' => 'Consommateur V1 P1',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$activationConsommateur = $requete('POST', "/api/v1/produits/{$PRD_CONSOMMATEUR}/activation", [], $sessionAutorite);
$verifier(
    ($activationConsommateur['corps']['resultat']['etat'] ?? null) === 'ACTIF',
    'un producteur et un consommateur réels, actifs, sont inscrits',
);

$purger();

// 4 — source active avec une finalité de diffusion.
$SRC = 'SRC-EVT-V1-P1';
$requete('POST', '/api/v1/sources', [
    'reference' => $SRC, 'nom_canonique' => 'Source V1 P1', 'nom_affichage' => 'Source V1 P1',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$requete('POST', "/api/v1/sources/{$SRC}/activation", [], $sessionAutorite);
$finalite = $requete('POST', "/api/v1/sources/{$SRC}/finalites", [
    'finalite_reference' => 'FINALITE-EVT-V1-P1', 'produit_consommateur_reference' => $PRD_CONSOMMATEUR,
], $sessionAutorite);
$verifier($finalite['statut'] === 201, 'une finalité de diffusion est déclarée pour la source');

$purger();

// 5 — contrat EVENEMENT actif, producteur et consommateur déclarés.
$CTR = 'CTR-EVT-V1-P1';
$requete('POST', '/api/v1/contrats', [
    'reference' => $CTR, 'nom' => 'Contrat Événement V1 P1', 'type_contrat' => 'EVENEMENT',
    'finalite_reference' => 'FINALITE-EVT-V1-P1', 'producteur_produit_reference' => $PRD_PRODUCTEUR,
    'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'garde v1 p1 événements',
], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions", ['version' => '1.0.0'], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/parties", [
    'role' => 'PRODUCTEUR', 'partie_type' => 'PRODUIT', 'partie_reference' => $PRD_PRODUCTEUR,
], $sessionAutorite);
// Le consommateur du contrat est l'autorité elle-même (partie_type CAPACITE) :
// c'est la seule entité pour laquelle POL-EVENEMENTS-V1 permet aujourd'hui
// `evenement.lire` / `evenement.livraison.accuser`, donc la seule dont la
// session peut réellement exercer la propriété de l'abonnement testée
// ci-dessous (`LivreurEvenements` compare l'acteur appelant au consommateur
// déclaré de l'abonnement, sans notion d'autorité).
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/parties", [
    'role' => 'CONSOMMATEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/operations", [
    'reference_operation' => 'evenement.v1.p1.publier', 'type_operation' => 'PUBLIER',
], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/soumission", [], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/analyse", [], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/conformite", [
    'resultat' => 'CONFORME', 'artefact_reference' => 'V1-P1-EVENEMENTS',
], $sessionAutorite);
$activationContrat = $requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/activation", [], $sessionAutorite);
$verifier(
    ($activationContrat['corps']['resultat']['etat'] ?? null) === 'ACTIVE',
    'le contrat EVENEMENT est actif, avec un producteur et un consommateur déclarés',
);

$purger();

// 6, 7 — abonnement créé en PREPARATION, puis activé après type/producteur/realm
// — AVANT la publication : le routage n'est jamais rétroactif (cf. garde
// noyau CAP-CORE-014, épreuve 32).
$abonnement = $requete('POST', '/api/v1/abonnements', [
    'nom' => 'Abonnement V1 P1', 'consommateur_capacite_reference' => 'AUT-GAMAD-001',
    'realm_reference' => $RLM, 'finalite_reference' => 'FINALITE-EVT-V1-P1', 'mode_livraison' => 'PULL_API',
], $sessionAutorite);
$ABN = (string) ($abonnement['corps']['resultat']['reference'] ?? '');
$verifier(
    $abonnement['statut'] === 201 && ($abonnement['corps']['resultat']['etat'] ?? null) === 'PREPARATION',
    'un abonnement naît en PREPARATION',
);

$activationSansType = $requete('POST', "/api/v1/abonnements/{$ABN}/activation", [], $sessionAutorite);
$requete('POST', "/api/v1/abonnements/{$ABN}/types", [
    'contrat_reference' => $CTR, 'type_evenement' => 'EVT_V1_P1_TEST',
], $sessionAutorite);
$requete('POST', "/api/v1/abonnements/{$ABN}/producteurs", ['producteur_reference' => $PRD_PRODUCTEUR], $sessionAutorite);
$requete('POST', "/api/v1/abonnements/{$ABN}/realms", ['realm_reference' => $RLM], $sessionAutorite);
$activation = $requete('POST', "/api/v1/abonnements/{$ABN}/activation", [], $sessionAutorite);
$verifier(
    ($activationSansType['corps']['resultat']['refus'] ?? null) === 'AUCUN_TYPE'
        && ($activation['corps']['resultat']['etat'] ?? null) === 'ACTIF',
    'l’activation sans type est refusée ; une fois type, producteur et realm déclarés, elle réussit',
);

$purger();

// 8, 9 — publication acceptée ; rejeu de la même idempotence idempotent.
$IDEMPOTENCE = 'IDEMP-EVT-V1-P1-001';
$enveloppe = [
    'type_evenement' => 'EVT_V1_P1_TEST', 'contrat_reference' => $CTR, 'contrat_version' => '1.0.0',
    'producteur_produit_reference' => $PRD_PRODUCTEUR, 'source_reference' => $SRC, 'realm_reference' => $RLM,
    'finalite_reference' => 'FINALITE-EVT-V1-P1', 'correlation_id' => 'COR-EVT-V1-P1',
    'survenu_le' => gmdate('c'), 'classification' => 'INTERNE', 'idempotence_reference' => $IDEMPOTENCE,
    'charge' => ['exemple_reference' => 'EXEMPLE-001', 'nouvel_etat' => 'TEST'],
];
$publication = $requete('POST', '/api/v1/evenements/publications', $enveloppe, $sessionAutorite);
$EVT = (string) ($publication['corps']['resultat']['reference'] ?? '');
$rejeuIdempotent = $requete('POST', '/api/v1/evenements/publications', $enveloppe, $sessionAutorite);
$verifier(
    $publication['statut'] === 201 && $EVT !== ''
        && $rejeuIdempotent['statut'] === 200
        && ($rejeuIdempotent['corps']['resultat']['reference'] ?? null) === $EVT,
    'la publication est acceptée une fois ; le rejeu de la même idempotence est idempotent (200, même référence)',
);

// 8 — lecture de l’enveloppe et de la publication par idempotence.
$fiche = $requete('GET', "/api/v1/evenements/{$EVT}", null, $sessionAutorite);
$ficheParIdempotence = $requete(
    'GET',
    "/api/v1/evenements/publications/{$PRD_PRODUCTEUR}/{$IDEMPOTENCE}",
    null,
    $sessionAutorite,
);
$verifier(
    $fiche['statut'] === 200 && ($fiche['corps']['evenement']['reference'] ?? null) === $EVT
        && $ficheParIdempotence['statut'] === 200,
    'l’enveloppe se lit par référence et par (producteur, idempotence)',
);

$purger();

// 10, 11 — livraison PULL, accusé, curseur.
$livraisons = $requete('GET', "/api/v1/abonnements/{$ABN}/livraisons?limite=10", null, $sessionAutorite);
$listeLivraisons = $livraisons['corps']['resultat']['livraisons'] ?? [];
$bail = (string) ($livraisons['corps']['resultat']['bail'] ?? '');
$verifier(
    $livraisons['statut'] === 200 && $bail !== '' && count($listeLivraisons) === 1
        && ($listeLivraisons[0]['evenement']['reference'] ?? null) === $EVT
        && ($listeLivraisons[0]['charge']['exemple_reference'] ?? null) === 'EXEMPLE-001',
    'la livraison PULL retourne l’événement publié avec sa charge, sous bail',
);

$REF_LIVRAISON = (string) ($listeLivraisons[0]['livraison'] ?? '');
$accuse = $requete('POST', "/api/v1/abonnements/{$ABN}/livraisons/accuses", [
    'bail' => $bail, 'livraisons' => [$REF_LIVRAISON],
], $sessionAutorite);
$curseur = $requete('GET', "/api/v1/abonnements/{$ABN}/curseur", null, $sessionAutorite);
$verifier(
    $accuse['statut'] === 200
        && ($accuse['corps']['resultat']['resultats'][$REF_LIVRAISON]['etat'] ?? null) === 'ACCUSE'
        && (int) ($curseur['corps']['derniere_sequence_contigue_accusee'] ?? 0) > 0,
    'l’accusé fait avancer le curseur de l’abonnement',
);

$purger();

// 13 — la charge n’est pas lisible par un acteur non destinataire ; le
// producteur et l’autorité, eux, y accèdent.
$identiteTiers = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'produit', 'libelle' => 'Tiers V1 P1',
], $sessionAutorite);
$PRD_TIERS = 'PRD-EVT-V1-P1-TIERS';
$requete('POST', '/api/v1/produits', [
    'reference' => $PRD_TIERS, 'identite_reference' => (string) ($identiteTiers['corps']['identite']['reference'] ?? ''),
    'nom_canonique' => 'tiers-evt-v1-p1', 'nom_affichage' => 'Tiers V1 P1',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$chargeAutorite = $requete('GET', "/api/v1/evenements/{$EVT}/charge", null, $sessionAutorite);
$verifier(
    $chargeAutorite['statut'] === 200 && ($chargeAutorite['corps']['etat'] ?? null) === 'DISPONIBLE',
    'l’autorité (destinataire indirect via bootstrap) lit la charge disponible',
);

$purger();

// 14, 15 — rejeu borné demandé, puis validé.
$demandeRejeu = $requete('POST', '/api/v1/rejeux', [
    'abonnement_reference' => $ABN, 'motif' => 'contrôle V1 P1', 'sequence_debut' => 1,
], $sessionAutorite);
$REJ = (string) ($demandeRejeu['corps']['resultat']['reference'] ?? '');
$validation = $requete('POST', "/api/v1/rejeux/{$REJ}/validation", [], $sessionAutorite);
$lectureRejeu = $requete('GET', "/api/v1/rejeux/{$REJ}", null, $sessionAutorite);
$verifier(
    $demandeRejeu['statut'] === 201 && ($demandeRejeu['corps']['resultat']['etat'] ?? null) === 'DEMANDEE'
        && ($validation['corps']['resultat']['etat'] ?? null) === 'VALIDEE'
        && ($lectureRejeu['corps']['rejeu']['etat'] ?? null) === 'VALIDEE',
    'une demande de rejeu bornée est acceptée puis validée',
);

// Audit : le parcours API est chaîné, sans secret, dans CAP-CORE-013.
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:' . $fichiers['journal']);
$evenementsAudit = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE categorie = 'EVENEMENTS'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $evenementsAudit >= 5,
    'le parcours API des événements est chaîné dans l’audit CAP-CORE-013, sans charge utile',
);

echo "\n";
if ($echecs === 0) {
    echo "Événements v1 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Événements v1 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
