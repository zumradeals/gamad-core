<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet du registre des organisations (CAP-CORE-002), depuis
 * l'inscription jusqu'à la dissolution, en passant par la structure, les
 * relations, les affiliations et la vérification de représentation via
 * CAP-CORE-003.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/organisations_v1_p1.php
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
$temp = sys_get_temp_dir() . '/gamad-organisations-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'organisations' => $temp . '-organisations.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('q', 32)),
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
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
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
$secretAutorite = 'Secret-Organisations-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:organisations:bootstrap');
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

echo "INTÉGRATION HTTP — ORGANISATIONS V1 P1 (CAP-CORE-002)\n\n";

// 1 — requête sans session refusée.
$sansSession = $requete('POST', '/api/v1/organisations', ['identite_reference' => 'X']);
$verifier(
    $sansSession['statut'] === 401 && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'aucune inscription n’est possible sans session',
);

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$sessionAutorite = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($sessionAutorite !== '', 'l’autorité ouvre une session Core');

$identiteOrg = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'organisation', 'libelle' => 'Organisation V1 P1',
], $sessionAutorite);
$identiteOrgRef = (string) ($identiteOrg['corps']['identite']['reference'] ?? '');
$identiteMembre = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'personne', 'libelle' => 'Dirigeante V1 P1',
], $sessionAutorite);
$identiteMembreRef = (string) ($identiteMembre['corps']['identite']['reference'] ?? '');
$verifier(
    $identiteOrg['statut'] === 201 && $identiteMembre['statut'] === 201,
    'les identités canoniques de l’organisation et de sa dirigeante sont résolues par CAP-CORE-001',
);

// 2, 3 — acteur non autorisé refusé ; fiche organisation inscrite et résolue.
$identiteSeconde = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'organisation', 'libelle' => 'Non autorisée',
], $sessionAutorite);
$acteurNonAutorise = $requete('POST', '/api/v1/sessions', ['entite' => (string) ($identiteSeconde['corps']['identite']['reference'] ?? ''), 'secret' => 'inconnu']);
$inscription = $requete('POST', '/api/v1/organisations', [
    'identite_reference' => $identiteOrgRef, 'type_organisation_reference' => 'COOPERATIVE',
    'proprietaire_reference' => 'AUT-GAMAD-001', 'denomination_officielle' => 'Coopérative V1 P1',
    'classification_reference' => 'INTERNE',
], $sessionAutorite);
$ORG = (string) ($inscription['corps']['resultat']['reference'] ?? '');
$fiche = $requete('GET', "/api/v1/organisations/{$ORG}", null, $sessionAutorite);
$verifier(
    $acteurNonAutorise['statut'] !== 200
        && $inscription['statut'] === 201
        && ($inscription['corps']['resultat']['etat'] ?? null) === 'PREPARATION'
        && $fiche['statut'] === 200,
    'un acteur non authentifiable est refusé ; l’autorité inscrit une organisation, visible en PREPARATION',
);

// 4, 5 — activation ; unité créée.
$activation = $requete('POST', "/api/v1/organisations/{$ORG}/activation", [], $sessionAutorite);
$unite = $requete('POST', "/api/v1/organisations/{$ORG}/unites", [
    'type_unite_reference' => 'SIEGE', 'nom' => 'Siège V1 P1', 'classification_reference' => 'INTERNE',
], $sessionAutorite);
$verifier(
    ($activation['corps']['resultat']['etat'] ?? null) === 'ACTIVE'
        && $unite['statut'] === 201,
    'l’organisation s’active ; une unité est créée sous l’organisation active',
);

// 7, 8 — affiliation proposée puis activée.
$affiliation = $requete('POST', "/api/v1/organisations/{$ORG}/affiliations", [
    'identite_reference' => $identiteMembreRef, 'type_affiliation_reference' => 'DIRIGEANT',
    'niveau_assurance_reference' => 'A2', 'classification_reference' => 'INTERNE',
    'producteur_reference' => $ORG,
], $sessionAutorite);
$AFL = (string) ($affiliation['corps']['resultat']['reference'] ?? '');
$activationAffiliation = $requete('POST', "/api/v1/organisations/{$ORG}/affiliations/{$AFL}/activation", [], $sessionAutorite);
$verifier(
    ($affiliation['corps']['resultat']['etat'] ?? null) === 'PROPOSEE'
        && ($activationAffiliation['corps']['resultat']['etat'] ?? null) === 'ACTIVE',
    'une affiliation est proposée puis activée, gouvernée par CAP-CORE-004',
);

// 9 — appartenance positive.
$appartenance = $requete('POST', "/api/v1/organisations/{$ORG}/appartenance/verification", [
    'identite_reference' => $identiteMembreRef,
], $sessionAutorite);
$verifier(
    $appartenance['statut'] === 200 && ($appartenance['corps']['membre'] ?? null) === true,
    'l’appartenance de la dirigeante à l’organisation est positive',
);

// 10 — représentation négative sans mandat.
$representationSansMandat = $requete('POST', "/api/v1/organisations/{$ORG}/representation/verification", [
    'identite_reference' => $identiteMembreRef,
], $sessionAutorite);
$verifier(
    $representationSansMandat['statut'] === 200 && ($representationSansMandat['corps']['opposable'] ?? null) === false,
    'sans mandat vérifié par CAP-CORE-003, la représentation reste refusée malgré l’affiliation DIRIGEANT active',
);

// 11, 12 — mandat valide (fixture CAP-CORE-003 réelle sur FCT-CORE-001) ;
// représentation positive.
$fonction = $requete('POST', "/api/v1/organisations/{$ORG}/fonctions", [
    'type_fonction_reference' => 'DIRECTION_GENERALE', 'libelle' => 'Direction générale V1 P1',
    'mandat_fonction_reference' => 'FCT-CORE-001',
], $sessionAutorite);
$verifier($fonction['statut'] === 201, 'une fonction interne descriptive, reliée à FCT-CORE-001, est créée');

$indexPdo = new PDO('sqlite:' . $fichiers['index']);
$indexPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$indexPdo->prepare('INSERT INTO titulaire (reference, libelle, nature) VALUES (?, ?, ?)')
    ->execute([$identiteMembreRef, 'Dirigeante V1 P1', 'personne']);
$indexPdo->prepare(
    'INSERT INTO mandat (reference, fonction_reference, titulaire_reference, debut, fin, niveau_preuve, adoption_reference)
     VALUES (?, ?, ?, ?, NULL, ?, ?)'
)->execute(['MANDAT-V1-P1-TEST', 'FCT-CORE-001', $identiteMembreRef, '2020-01-01', 'P1', 'ADOPTION-V1-P1-TEST']);
$indexPdo->prepare('INSERT INTO etat_mandat (mandat_reference, valeur, date_effet, adoption_reference) VALUES (?, ?, ?, ?)')
    ->execute(['MANDAT-V1-P1-TEST', 'ACTIF — TEST', '2020-01-01', 'ADOPTION-V1-P1-TEST']);

$representationAvecMandat = $requete('POST', "/api/v1/organisations/{$ORG}/representation/verification", [
    'identite_reference' => $identiteMembreRef,
], $sessionAutorite);
$verifier(
    $representationAvecMandat['statut'] === 200
        && ($representationAvecMandat['corps']['opposable'] ?? null) === true
        && ($representationAvecMandat['corps']['mandat'] ?? null) === 'MANDAT-V1-P1-TEST',
    'un mandat actif vérifié par CAP-CORE-003 rend la représentation opposable',
);

// 13, 14 — suspension de l’affiliation ; représentation refusée à nouveau.
$suspensionAffiliation = $requete('POST', "/api/v1/organisations/{$ORG}/affiliations/{$AFL}/suspension", [], $sessionAutorite);
$representationApresSuspension = $requete('POST', "/api/v1/organisations/{$ORG}/representation/verification", [
    'identite_reference' => $identiteMembreRef,
], $sessionAutorite);
$verifier(
    ($suspensionAffiliation['corps']['resultat']['etat'] ?? null) === 'SUSPENDUE'
        && ($representationApresSuspension['corps']['opposable'] ?? null) === false,
    'la suspension de l’affiliation retire immédiatement la représentation opposable',
);

// Le limiteur de débit HTTP de l'application (`throttle:N,1`) clé son
// compteur uniquement sur domaine+IP (voir
// Illuminate\Routing\Middleware\ThrottleRequests::resolveRequestSignature),
// pas sur la route : toutes les routes throttlées de ce parcours partagent
// donc un seul compteur. Ce comportement est celui de l'application entière,
// pas une règle propre à CAP-CORE-002 ; ce test, plus riche en requêtes que
// les autres parcours v1, purge explicitement le magasin de cache (pilote de
// test isolé, `CACHE_STORE=array`) entre ses phases pour rester représentatif
// du comportement métier plutôt que du seuil de débit.
$app->make('cache.store')->flush();

// 15, 16 — relation interorganisationnelle créée ; cycle refusé.
$identiteOrgB = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'organisation', 'libelle' => 'Organisation B V1 P1',
], $sessionAutorite);
$inscriptionB = $requete('POST', '/api/v1/organisations', [
    'identite_reference' => (string) ($identiteOrgB['corps']['identite']['reference'] ?? ''),
    'type_organisation_reference' => 'ASSOCIATION', 'proprietaire_reference' => 'AUT-GAMAD-001',
    'denomination_officielle' => 'Association B V1 P1', 'classification_reference' => 'INTERNE',
], $sessionAutorite);
$ORG_B = (string) ($inscriptionB['corps']['resultat']['reference'] ?? '');
$requete('POST', "/api/v1/organisations/{$ORG_B}/activation", [], $sessionAutorite);
$relation = $requete('POST', "/api/v1/organisations/{$ORG}/relations", [
    'organisation_cible_reference' => $ORG_B, 'type_relation_reference' => 'FILIALE_DE',
    'classification_reference' => 'INTERNE',
], $sessionAutorite);
$relationCycle = $requete('POST', "/api/v1/organisations/{$ORG_B}/relations", [
    'organisation_cible_reference' => $ORG, 'type_relation_reference' => 'FILIALE_DE',
    'classification_reference' => 'INTERNE',
], $sessionAutorite);
$verifier(
    $relation['statut'] === 201
        && $relationCycle['statut'] === 422
        && ($relationCycle['corps']['resultat']['refus'] ?? null) === 'CYCLE_HIERARCHIQUE_DETECTE',
    'une relation interorganisationnelle est créée ; celle qui créerait un cycle hiérarchique est refusée',
);

$app->make('cache.store')->flush();

// 17, 18 — suspension de l’organisation ; nouvelles affiliations refusées.
$suspensionOrg = $requete('POST', "/api/v1/organisations/{$ORG}/suspension", [], $sessionAutorite);
$nouvelleAffiliation = $requete('POST', "/api/v1/organisations/{$ORG}/affiliations", [
    'identite_reference' => $identiteMembreRef, 'type_affiliation_reference' => 'MEMBRE',
    'niveau_assurance_reference' => 'A1', 'classification_reference' => 'INTERNE', 'producteur_reference' => $ORG,
], $sessionAutorite);
$activationNouvelleAffiliation = $requete(
    'POST',
    "/api/v1/organisations/{$ORG}/affiliations/" . (string) ($nouvelleAffiliation['corps']['resultat']['reference'] ?? '') . '/activation',
    [],
    $sessionAutorite,
);
$verifier(
    ($suspensionOrg['corps']['resultat']['etat'] ?? null) === 'SUSPENDUE'
        && $activationNouvelleAffiliation['statut'] === 422
        && ($activationNouvelleAffiliation['corps']['resultat']['refus'] ?? null) === 'ORGANISATION_NON_ACTIVE',
    'une organisation suspendue ne peut plus voir de nouvelle affiliation activée',
);

$app->make('cache.store')->flush();

// 19, 20 — dissolution ; historique lisible.
$reactivation = $requete('POST', "/api/v1/organisations/{$ORG}/activation", [], $sessionAutorite);
$dissolution = $requete('POST', "/api/v1/organisations/{$ORG}/dissolution", [], $sessionAutorite);
$ficheApresDissolution = $requete('GET', "/api/v1/organisations/{$ORG}", null, $sessionAutorite);
$verifier(
    ($dissolution['corps']['resultat']['etat'] ?? null) === 'DISSOUTE'
        && $ficheApresDissolution['statut'] === 200
        && count($ficheApresDissolution['corps']['historique'] ?? []) >= 3,
    'la dissolution est terminale ; l’historique complet reste lisible',
);

// Audit : le parcours est chaîné, sans secret.
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:' . $fichiers['journal']);
$evenements = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE categorie = 'ORGANISATIONS'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $evenements >= 8,
    'le parcours des organisations est chaîné dans l’audit CAP-CORE-013',
);

echo "\n";
if ($echecs === 0) {
    echo "Organisations v1 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Organisations v1 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
