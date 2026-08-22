<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet de CORE-ORG-DELEGATION-001 : un produit reconnu,
 * explicitement et individuellement délégué via `Ctr03`/CAP-CORE-004 et une
 * politique dédiée CAP-CORE-007, inscrit une identité « organisation »
 * (CAP-CORE-001, canal PRODUIT_RECONNU) puis sa fiche CAP-CORE-002 — jamais
 * aucune autre action, jamais hors de l'état ACTIF (CAP-CORE-011), jamais
 * via `federation_autorisee`.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/organisations_delegation_satellite_p1.php
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
$temp = sys_get_temp_dir().'/gamad-organisations-delegation-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'acces' => $temp.'-acces.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'journal' => $temp.'-journal.sqlite',
    'organisations' => $temp.'-organisations.sqlite',
    'produits' => $temp.'-produits.sqlite',
    'politiques' => $temp.'-politiques.sqlite',
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('q', 32)),
    'APP_CONFIG_CACHE' => $temp.'-config.php',
    'APP_EVENTS_CACHE' => $temp.'-events.php',
    'APP_PACKAGES_CACHE' => $temp.'-packages.php',
    'APP_ROUTES_CACHE' => $temp.'-routes.php',
    'APP_SERVICES_CACHE' => $temp.'-services.php',
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
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';

BaselineOperationnelle::standard()->reconstruire(Db::connect());
IdentiteMagasin::connecter();
JournalMagasin::connecter();
$ctr16 = new Ctr16(AccesMagasin::connecter());
$secretAutorite = 'Secret-Delegation-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$kernel = $app->make(Kernel::class);
$console = $app->make(Illuminate\Contracts\Console\Kernel::class);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) {
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
        $serveur['HTTP_AUTHORIZATION'] = 'Bearer '.$jeton;
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

echo "INTÉGRATION HTTP — CORE-ORG-DELEGATION-001 P1 (CAP-CORE-001 / CAP-CORE-002 / CAP-CORE-011)\n\n";

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$sessionAutorite = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($sessionAutorite !== '', 'l’autorité ouvre une session Core');

// Identité « organisation » de rechange, jamais liée à aucune fiche : sert à
// isoler le contrôle défensif CAP-CORE-002 (produit suspendu) de celui de
// CAP-CORE-001, qui ferme déjà l'inscription d'identité elle-même.
$identiteRechange = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'organisation', 'libelle' => 'Organisation de rechange — délégation P1',
], $sessionAutorite);
$identiteRechangeRef = (string) ($identiteRechange['corps']['identite']['reference'] ?? '');
$verifier($identiteRechange['statut'] === 201, 'une identité organisation de rechange est résolue par CAP-CORE-001');

/**
 * @return array{reference:string,jeton:string}
 */
$creerProduitActif = static function (string $reference, string $libelle) use ($requete, $sessionAutorite, $ctr16): array {
    $identite = $requete('POST', '/api/v1/identites', [
        'canal' => 'AUTORITE', 'type' => 'produit', 'libelle' => $libelle,
    ], $sessionAutorite);
    $identiteRef = (string) ($identite['corps']['identite']['reference'] ?? '');
    $requete('POST', '/api/v1/produits', [
        'reference' => $reference, 'identite_reference' => $identiteRef,
        'nom_canonique' => $libelle, 'nom_affichage' => $libelle,
        'type_produit' => 'SATELLITE', 'proprietaire_reference' => 'AUT-GAMAD-001',
    ], $sessionAutorite);
    $requete('POST', "/api/v1/produits/{$reference}/activation", [], $sessionAutorite);

    $secret = 'Secret-'.$reference.'-1!';
    $ctr16->inscrireAuthentificateur($reference, $secret);
    $session = $requete('POST', '/api/v1/sessions', ['entite' => $reference, 'secret' => $secret]);

    return ['reference' => $reference, 'jeton' => (string) ($session['corps']['jeton'] ?? '')];
};

$A = $creerProduitActif('PRD-DELEGATION-P1-A', 'Satellite A — délégation P1');
$B = $creerProduitActif('PRD-DELEGATION-P1-B', 'Satellite B — délégation P1 (jamais délégué)');
$C = $creerProduitActif('PRD-DELEGATION-P1-C', 'Satellite C — délégation P1 (non fédérable)');
$verifier(
    $A['jeton'] !== '' && $B['jeton'] !== '' && $C['jeton'] !== '',
    'trois produits ACTIFS (CAP-CORE-011) ouvrent chacun leur propre session Core',
);

// C n'est jamais rendu fédérable : la délégation ne doit rien lui devoir.
$fiche_C = $requete('GET', "/api/v1/produits/{$C['reference']}", null, $sessionAutorite);
$verifier(
    ($fiche_C['corps']['produit']['federation_autorisee'] ?? null) === false,
    'le produit C reste explicitement non fédérable (federation_autorisee=false)',
);

// Le limiteur de débit HTTP (`throttle:N,1`) clé son compteur uniquement sur
// domaine+IP, pas sur la route (voir organisations_v1_p1.php) : ce parcours,
// riche en requêtes POST sur trois produits et deux registres, purge
// explicitement le magasin de cache entre ses phases.
$app->make('cache.store')->flush();

// 1 — avant toute délégation, un produit ACTIF authentifié reste refusé.
$avantDelegation = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU', 'type' => 'organisation', 'libelle' => 'Organisation A — avant délégation',
], $A['jeton']);
$verifier(
    $avantDelegation['statut'] === 403,
    'un produit ACTIF authentifié, mais non délégué, reste refusé (authentification ≠ autorisation)',
);

// 2 — la procédure d'admission (documentée, générique, rejouable).
$sortieOctroiA = $console->call('core:organisations:accorder-delegation-satellite', ['produit' => $A['reference']]);
$verifier($sortieOctroiA === 0, 'la procédure d’admission accorde la délégation à A (code de sortie 0)');

// 3 — A délégué crée une identité « organisation » par le canal PRODUIT_RECONNU.
$identiteA = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU', 'type' => 'organisation', 'libelle' => 'Organisation A — déléguée',
], $A['jeton']);
$identiteARef = (string) ($identiteA['corps']['identite']['reference'] ?? '');
$verifier(
    $identiteA['statut'] === 201
        && ($identiteA['corps']['identite']['etat'] ?? null) === 'ACTIVE'
        && ($identiteA['corps']['identite']['assurance'] ?? null) === 'A1',
    'A délégué inscrit une identité organisation, assurance A1, jamais plus',
);

// 4 — A délégué inscrit ensuite la fiche CAP-CORE-002 correspondante (CREATE).
$orgA = $requete('POST', '/api/v1/organisations', [
    'identite_reference' => $identiteARef, 'type_organisation_reference' => 'INDETERMINE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Organisation A — déléguée',
    'classification_reference' => 'INTERNE',
], $A['jeton']);
$ORG_A = (string) ($orgA['corps']['resultat']['reference'] ?? '');
$verifier(
    $orgA['statut'] === 201 && ($orgA['corps']['resultat']['etat'] ?? null) === 'PREPARATION',
    'A délégué inscrit la fiche organisationnelle CAP-CORE-002 (CREATE) — le Core déduplique, crée, journalise',
);

$app->make('cache.store')->flush();

// 5 — B, ACTIF mais jamais délégué, reste refusé : la délégation est
// nommément explicite par produit, jamais accordée par classe SATELLITE.
$tentativeB = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU', 'type' => 'organisation', 'libelle' => 'Organisation B — jamais déléguée',
], $B['jeton']);
$verifier(
    $tentativeB['statut'] === 403,
    'B, ACTIF mais non délégué, reste refusé : la délégation n’est jamais accordée par classe SATELLITE',
);

// 6 — rejouer la procédure pour A ne crée aucun doublon de règle (idempotence
// de la procédure elle-même) et A continue de fonctionner ensuite.
$sortieOctroiARejoue = $console->call('core:organisations:accorder-delegation-satellite', ['produit' => $A['reference']]);
$identiteARejeu = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU', 'type' => 'organisation', 'libelle' => 'Organisation A bis — après rejeu de la procédure',
], $A['jeton']);
$verifier(
    $sortieOctroiARejoue === 0 && $identiteARejeu['statut'] === 201,
    'rejouer la procédure d’admission pour A déjà délégué est un no-op sûr ; A continue de fonctionner',
);

$app->make('cache.store')->flush();

// 7 — la même procédure, générique, admet un second produit (C, jamais
// fédérable) sans jamais toucher `federation_autorisee` ; A reste ensuite
// intact (admettre C ne révoque jamais A — chaque version conserve les
// règles déjà actives).
$sortieOctroiC = $console->call('core:organisations:accorder-delegation-satellite', ['produit' => $C['reference']]);
$identiteC = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU', 'type' => 'organisation', 'libelle' => 'Organisation C — déléguée, non fédérable',
], $C['jeton']);
$identiteCRef = (string) ($identiteC['corps']['identite']['reference'] ?? '');
$orgC = $requete('POST', '/api/v1/organisations', [
    'identite_reference' => $identiteCRef, 'type_organisation_reference' => 'INDETERMINE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Organisation C — déléguée',
    'classification_reference' => 'INTERNE',
], $C['jeton']);
$identiteAApresAdmissionC = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU', 'type' => 'organisation', 'libelle' => 'Organisation A ter — après admission de C',
], $A['jeton']);
$verifier(
    $sortieOctroiC === 0
        && $identiteC['statut'] === 201 && $orgC['statut'] === 201
        && $identiteAApresAdmissionC['statut'] === 201,
    'la même procédure délègue C (non fédérable) sans toucher federation_autorisee ; A reste délégué après C',
);

$app->make('cache.store')->flush();

// 8 — rejeu strict : la même identité déjà liée refuse un doublon (409),
// sans créer de seconde fiche organisationnelle pour la même identité.
$rejeuOrgA = $requete('POST', '/api/v1/organisations', [
    'identite_reference' => $identiteARef, 'type_organisation_reference' => 'INDETERMINE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Organisation A — doublon',
    'classification_reference' => 'INTERNE',
], $A['jeton']);
$verifier(
    $rejeuOrgA['statut'] === 409
        && ($rejeuOrgA['corps']['resultat']['refus'] ?? null) === 'IDENTITE_DEJA_LIEE',
    'rejouer la même demande de création n’a jamais pu créer une seconde Organisation pour la même identité',
);

// 9 — ATTACH V1 : résolution en lecture seule d'une organisation déjà
// canonisée, par son identité — jamais de création, jamais de mutation.
// Résolue par l'autorité : ORG_A reste en PREPARATION (jamais activée dans
// ce parcours), donc non publique — sa visibilité hors ACTIVE reste réservée
// à l'autorité ou au propriétaire déclaré (`AccesOrganisations::visible()`,
// inchangé par ce chantier). L'ATTACH lui-même n'ajoute ni ne retire aucune
// règle de visibilité.
$resolutionA = $requete('GET', "/api/v1/organisations/resolution/{$identiteARef}", null, $sessionAutorite);
$resolutionInconnue = $requete('GET', '/api/v1/organisations/resolution/IDN-INCONNUE-DELEGATION-P1', null, $sessionAutorite);
$verifier(
    $resolutionA['statut'] === 200
        && ($resolutionA['corps']['organisation']['reference'] ?? null) === $ORG_A
        && $resolutionInconnue['statut'] === 404,
    'la résolution ATTACH retrouve la fiche déjà canonisée par son identité ; une identité inconnue rend 404',
);

// 10 — aucune obtention implicite de rôle ou de propriété : avoir résolu ou
// créé une organisation ne rend jamais A capable de l'administrer.
$activationParA = $requete('POST', "/api/v1/organisations/{$ORG_A}/activation", [], $A['jeton']);
$verifier(
    $activationParA['statut'] === 403,
    'A n’a reçu aucune autre action que la création : activer sa propre organisation lui reste interdit',
);

$app->make('cache.store')->flush();

// 11 — suspension du produit : fermeture immédiate de la délégation, sans
// toucher la politique ni les Organisations déjà créées.
$suspensionA = $requete('POST', "/api/v1/produits/{$A['reference']}/suspension", [], $sessionAutorite);
$verifier(
    ($suspensionA['corps']['resultat']['etat'] ?? null) === 'SUSPENDU',
    'le produit A est suspendu via CAP-CORE-011',
);

$identiteApresSuspension = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU', 'type' => 'organisation', 'libelle' => 'Organisation A — après suspension',
], $A['jeton']);
$verifier(
    $identiteApresSuspension['statut'] === 422
        && ($identiteApresSuspension['corps']['resultat']['refus'] ?? null) === 'PRODUCTEUR_INCOMPETENT',
    'A suspendu ne peut plus inscrire d’identité : sa règle CTR-03 reste PERMIS, mais Ctr01::produitReconnu() '
        .'consulte désormais l’état réel CAP-CORE-011 et refuse (CAP-CORE-001, Phase A §5.1)',
);

$orgApresSuspension = $requete('POST', '/api/v1/organisations', [
    'identite_reference' => $identiteRechangeRef, 'type_organisation_reference' => 'INDETERMINE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Organisation — refusée après suspension',
    'classification_reference' => 'INTERNE',
], $A['jeton']);
$verifier(
    $orgApresSuspension['statut'] === 403,
    'A suspendu est refusé immédiatement sur CAP-CORE-002, même si sa règle CTR-03 reste active (contrôle défensif Phase A §5.2)',
);

$app->make('cache.store')->flush();

// 12 — les Organisations déjà créées par A restent intactes après sa
// suspension.
$orgAApresSuspension = $requete('GET', "/api/v1/organisations/{$ORG_A}", null, $sessionAutorite);
$verifier(
    $orgAApresSuspension['statut'] === 200
        && ($orgAApresSuspension['corps']['organisation']['reference'] ?? null) === $ORG_A,
    'l’Organisation déjà créée par A reste intacte et lisible après sa suspension',
);

// 13 — journalisation : la décision et l’opération de chaque étape déléguée
// sont tracées, y compris les refus, chaînées sans rupture (CAP-CORE-013).
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:'.$fichiers['journal']);
$decisionsA = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE acteur = 'PRD-DELEGATION-P1-A'")
    ->fetchColumn();
$refusApresSuspension = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE acteur = 'PRD-DELEGATION-P1-A' AND decision = 'REFUSE'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $decisionsA >= 4 && $refusApresSuspension >= 1,
    'le parcours délégué de A est chaîné dans l’audit CAP-CORE-013, refus compris',
);

echo "\n";
if ($echecs === 0) {
    echo "CORE-ORG-DELEGATION-001 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "CORE-ORG-DELEGATION-001 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
