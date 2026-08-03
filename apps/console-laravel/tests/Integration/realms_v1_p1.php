<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet du registre des realms (CAP-CORE-012), depuis
 * l'inscription jusqu'au retrait, en passant par le périmètre, les
 * rattachements d'organisation/produit/contrat, l'activation, le contrôle de
 * portée et le franchissement inter-realm (fiche §58).
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/realms_v1_p1.php
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
$temp = sys_get_temp_dir() . '/gamad-realms-v1-' . getmypid();
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('r', 32)),
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
$secretAutorite = 'Secret-Realms-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:organisations:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:produits:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:contrats:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:vocabulaire:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:realms:bootstrap');
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

echo "INTÉGRATION HTTP — REALMS V1 P1 (CAP-CORE-012)\n\n";

// 1 — lecture sans session refusée.
$sansSession = $requete('GET', '/api/v1/realms');
$verifier(
    $sansSession['statut'] === 401 && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'aucune lecture n’est possible sans session',
);

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$sessionAutorite = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($sessionAutorite !== '', 'l’autorité ouvre une session Core');

// 2 — acteur non autorisé refusé.
$identiteSeconde = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'realm', 'libelle' => 'Non autorisée',
], $sessionAutorite);
$acteurNonAutorise = $requete(
    'POST',
    '/api/v1/sessions',
    ['entite' => (string) ($identiteSeconde['corps']['identite']['reference'] ?? ''), 'secret' => 'inconnu'],
);
$verifier($acteurNonAutorise['statut'] !== 200, 'un acteur non authentifiable est refusé');

// 3 — identités realm créées.
$identiteRlmA = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'realm', 'libelle' => 'Realm A V1 P1',
], $sessionAutorite);
$IDN_RLM_A = (string) ($identiteRlmA['corps']['identite']['reference'] ?? '');
$identiteRlmB = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'realm', 'libelle' => 'Realm B V1 P1',
], $sessionAutorite);
$IDN_RLM_B = (string) ($identiteRlmB['corps']['identite']['reference'] ?? '');
$identiteOrg = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'organisation', 'libelle' => 'Organisation Realms V1 P1',
], $sessionAutorite);
$IDN_ORG = (string) ($identiteOrg['corps']['identite']['reference'] ?? '');
$identiteProduit = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'produit', 'libelle' => 'Produit Realms V1 P1',
], $sessionAutorite);
$IDN_PRD = (string) ($identiteProduit['corps']['identite']['reference'] ?? '');
$verifier(
    $IDN_RLM_A !== '' && $IDN_RLM_B !== '' && $IDN_ORG !== '' && $IDN_PRD !== '',
    'les identités canoniques du realm, de l’organisation et du produit sont résolues par CAP-CORE-001',
);

// 4 — realm inscrit ; 5 — visible en préparation seulement à l’autorité.
$inscription = $requete('POST', '/api/v1/realms', [
    'identite_reference' => $IDN_RLM_A, 'code_canonique' => 'RLM-V1-P1-A', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Realm A V1 P1', 'classification_reference' => 'INTERNE',
], $sessionAutorite);
$RLM_A = (string) ($inscription['corps']['resultat']['reference'] ?? '');
$ficheEnPreparation = $requete('GET', "/api/v1/realms/{$RLM_A}", null, $sessionAutorite);
$verifier(
    $inscription['statut'] === 201
        && ($inscription['corps']['resultat']['etat'] ?? null) === 'PREPARATION'
        && $ficheEnPreparation['statut'] === 200,
    'l’autorité inscrit un realm, visible en PREPARATION',
);

$inscriptionB = $requete('POST', '/api/v1/realms', [
    'identite_reference' => $IDN_RLM_B, 'code_canonique' => 'RLM-V1-P1-B', 'type_realm_reference' => 'TERRITORIAL',
    'nom_affichage' => 'Realm B V1 P1', 'classification_reference' => 'INTERNE',
], $sessionAutorite);
$RLM_B = (string) ($inscriptionB['corps']['resultat']['reference'] ?? '');

// 6 — périmètre déclaré.
$perimetre = $requete('POST', "/api/v1/realms/{$RLM_A}/perimetres", [
    'dimension_reference' => 'PAYS', 'valeur_reference' => 'V1P1',
], $sessionAutorite);
$verifier($perimetre['statut'] === 201, 'un périmètre canonique est déclaré');

$purger();

// 7 — organisation responsable rattachée avec mandat (l’autorité elle-même).
$inscriptionOrg = $requete('POST', '/api/v1/organisations', [
    'identite_reference' => $IDN_ORG, 'type_organisation_reference' => 'INSTITUTION',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Organisation Realms V1 P1',
    'classification_reference' => 'INTERNE',
], $sessionAutorite);
$ORG = (string) ($inscriptionOrg['corps']['resultat']['reference'] ?? '');
$requete('POST', "/api/v1/organisations/{$ORG}/activation", [], $sessionAutorite);
$rattachementOrg = $requete('POST', "/api/v1/realms/{$RLM_A}/organisations", [
    'organisation_reference' => $ORG, 'role_reference' => 'RESPONSABLE', 'classification_reference' => 'INTERNE',
], $sessionAutorite);
$verifier(
    $rattachementOrg['statut'] === 201,
    'une organisation active est rattachée en rôle RESPONSABLE, l’autorité disposant d’un mandat implicite reconnu',
);

$purger();

// 8 — produit actif rattaché.
$inscriptionPrd = $requete('POST', '/api/v1/produits', [
    'reference' => 'PRD-V1-P1-REALMS', 'identite_reference' => $IDN_PRD, 'nom_canonique' => 'produit-v1-p1-realms',
    'nom_affichage' => 'Produit Realms V1 P1', 'type_produit' => 'SATELLITE', 'proprietaire_reference' => 'AUT-GAMAD-001',
], $sessionAutorite);
$PRD = 'PRD-V1-P1-REALMS';
$requete('POST', "/api/v1/produits/{$PRD}/activation", [], $sessionAutorite);
$rattachementPrd = $requete('POST', "/api/v1/realms/{$RLM_A}/produits", [
    'produit_reference' => $PRD, 'role_reference' => 'OPERE_DANS',
], $sessionAutorite);
$verifier($rattachementPrd['statut'] === 201, 'un produit actif est rattaché au realm');

$purger();

// 9 — contrat rattaché.
$inscriptionCtr = $requete('POST', '/api/v1/contrats', [
    'reference' => 'CTR-V1-P1-REALMS', 'nom' => 'Contrat Realms V1 P1', 'type_contrat' => 'INTERCAPACITE',
    'finalite_reference' => 'FINALITE-V1-P1', 'producteur_produit_reference' => $IDN_PRD,
    'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'garde v1 p1',
], $sessionAutorite);
$CTR = 'CTR-V1-P1-REALMS';
$requete('POST', "/api/v1/contrats/{$CTR}/versions", ['version' => '1.0.0'], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/parties", [
    'role' => 'PRODUCTEUR', 'partie_type' => 'PRODUIT', 'partie_reference' => $IDN_PRD,
], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/operations", [
    'reference_operation' => 'operation.v1.p1', 'type_operation' => 'INTERROGER',
], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/soumission", [], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/analyse", [], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/conformite", [
    'resultat' => 'CONFORME', 'artefact_reference' => 'V1-P1-REALMS',
], $sessionAutorite);
$requete('POST', "/api/v1/contrats/{$CTR}/versions/1.0.0/activation", [], $sessionAutorite);
$rattachementCtr = $requete('POST', "/api/v1/realms/{$RLM_A}/contrats", [
    'contrat_reference' => $CTR, 'role_reference' => 'GOUVERNE',
], $sessionAutorite);
$verifier($rattachementCtr['statut'] === 201, 'un contrat actif est rattaché au realm');

$purger();

// 10 — activation.
$activation = $requete('POST', "/api/v1/realms/{$RLM_A}/activation", [], $sessionAutorite);
$requete('POST', "/api/v1/realms/{$RLM_B}/activation", [], $sessionAutorite);
$verifier(($activation['corps']['resultat']['etat'] ?? null) === 'ACTIF', 'le realm actif reçoit ses rattachements avant activation');

// 11 — contrôle de portée positif.
$porteePositive = $requete('POST', "/api/v1/realms/{$RLM_A}/portee", [
    'organisation' => $ORG, 'produit' => $PRD, 'contrat' => $CTR,
], $sessionAutorite);
$verifier(
    ($porteePositive['corps']['dans_portee'] ?? null) === true,
    'le contrôle de portée est positif une fois le realm actif et les rattachements réunis',
);

// 12 — autre produit refusé.
$porteeAutreProduit = $requete('POST', "/api/v1/realms/{$RLM_A}/portee", [
    'produit' => 'PRD-INCONNU-V1-P1',
], $sessionAutorite);
$verifier(
    ($porteeAutreProduit['corps']['dans_portee'] ?? null) === false
        && in_array('PRODUIT_NON_RATTACHE', $porteeAutreProduit['corps']['motifs'] ?? [], true),
    'un produit non rattaché est explicitement refusé par le contrôle de portée',
);

// 13 — autre realm refusé sans franchissement.
$porteeCroisee = $requete('POST', "/api/v1/realms/{$RLM_A}/portee", [
    'realm_cible' => $RLM_B, 'finalite' => 'FINALITE-V1-P1',
], $sessionAutorite);
$verifier(
    ($porteeCroisee['corps']['dans_portee'] ?? null) === false
        && in_array('FRANCHISSEMENT_NON_DECLARE', $porteeCroisee['corps']['motifs'] ?? [], true),
    'sans franchissement déclaré, un realm cible est refusé par défaut',
);

$purger();

// 14 — franchissement déclaré ; 15 — contrôle positif inter-realm.
$franchissement = $requete('POST', "/api/v1/realms/{$RLM_A}/franchissements", [
    'realm_cible_reference' => $RLM_B, 'objet_reference' => 'objet.v1.p1', 'type_objet_reference' => 'DONNEE',
    'effet_reference' => 'PERMET', 'finalite_reference' => 'FINALITE-V1-P1',
], $sessionAutorite);
$porteeApresFranchissement = $requete('POST', "/api/v1/realms/{$RLM_A}/portee", [
    'realm_cible' => $RLM_B, 'finalite' => 'FINALITE-V1-P1',
], $sessionAutorite);
$verifier(
    $franchissement['statut'] === 201 && ($porteeApresFranchissement['corps']['dans_portee'] ?? null) === true,
    'un franchissement PERMET explicite rend le contrôle de portée positif entre deux realms',
);

// 16, 17 — refus explicite ajouté ; refus prioritaire.
$refusExplicite = $requete('POST', "/api/v1/realms/{$RLM_A}/franchissements", [
    'realm_cible_reference' => $RLM_B, 'objet_reference' => 'objet.v1.p1', 'type_objet_reference' => 'DONNEE',
    'effet_reference' => 'REFUSE', 'finalite_reference' => 'FINALITE-V1-P1',
], $sessionAutorite);
$porteeApresRefus = $requete('POST', "/api/v1/realms/{$RLM_A}/portee", [
    'realm_cible' => $RLM_B, 'finalite' => 'FINALITE-V1-P1',
], $sessionAutorite);
$verifier(
    $refusExplicite['statut'] === 201
        && ($porteeApresRefus['corps']['dans_portee'] ?? null) === false
        && in_array('FRANCHISSEMENT_REFUSE', $porteeApresRefus['corps']['motifs'] ?? [], true),
    'un REFUSE ajouté après un PERMET l’emporte toujours (refus prioritaire)',
);

$purger();

// 18, 19 — suspension ; nouveaux usages refusés.
$suspension = $requete('POST', "/api/v1/realms/{$RLM_A}/suspension", [], $sessionAutorite);
$porteeApresSuspension = $requete('POST', "/api/v1/realms/{$RLM_A}/portee", [], $sessionAutorite);
$verifier(
    ($suspension['corps']['resultat']['etat'] ?? null) === 'SUSPENDU'
        && ($porteeApresSuspension['corps']['dans_portee'] ?? null) === false
        && in_array('REALM_SUSPENDU', $porteeApresSuspension['corps']['motifs'] ?? [], true),
    'un realm suspendu ferme immédiatement le contrôle de portée',
);

// 20 — historique lisible.
$historique = $requete('GET', "/api/v1/realms/{$RLM_A}/historique", null, $sessionAutorite);
$verifier(
    $historique['statut'] === 200 && count($historique['corps']['historique'] ?? []) >= 2,
    'l’historique du cycle reste lisible après suspension',
);

$purger();

// 21 — fermeture.
$fermeture = $requete('POST', "/api/v1/realms/{$RLM_A}/fermeture", [], $sessionAutorite);
$verifier(($fermeture['corps']['resultat']['etat'] ?? null) === 'FERME', 'le realm est fermé');

// 22 — successeur déclaré.
$identiteSuccesseur = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'realm', 'libelle' => 'Realm Successeur V1 P1',
], $sessionAutorite);
$inscriptionSuccesseur = $requete('POST', '/api/v1/realms', [
    'identite_reference' => (string) ($identiteSuccesseur['corps']['identite']['reference'] ?? ''),
    'code_canonique' => 'RLM-V1-P1-SUCC', 'type_realm_reference' => 'PROGRAMME',
    'nom_affichage' => 'Realm Successeur V1 P1', 'classification_reference' => 'INTERNE',
], $sessionAutorite);
$RLM_SUCC = (string) ($inscriptionSuccesseur['corps']['resultat']['reference'] ?? '');
$succession = $requete('POST', "/api/v1/realms/{$RLM_SUCC}/relations", [
    'realm_cible_reference' => $RLM_A, 'type_relation_reference' => 'SUCCEDE_A',
], $sessionAutorite);
$verifier($succession['statut'] === 201, 'une relation SUCCEDE_A est déclarée depuis le successeur vers le realm fermé');

$purger();

// 23, 24 — retrait ; référence non réutilisable.
$retrait = $requete('POST', "/api/v1/realms/{$RLM_A}/retrait", ['motif_reference' => 'FIN_DE_PROGRAMME'], $sessionAutorite);
$reactivationApresRetrait = $requete('POST', "/api/v1/realms/{$RLM_A}/activation", [], $sessionAutorite);
$verifier(
    ($retrait['corps']['resultat']['etat'] ?? null) === 'RETIRE'
        && $reactivationApresRetrait['statut'] === 409
        && ($reactivationApresRetrait['corps']['resultat']['refus'] ?? null) === 'ETAT_INCOMPATIBLE',
    'le retrait est irréversible ; la référence retirée ne redevient jamais active',
);

// Audit : le parcours est chaîné, sans secret.
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:' . $fichiers['journal']);
$evenements = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE categorie = 'REALMS'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $evenements >= 8,
    'le parcours des realms est chaîné dans l’audit CAP-CORE-013',
);

echo "\n";
if ($echecs === 0) {
    echo "Realms v1 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Realms v1 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
