<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet du registre du vocabulaire canonique (CAP-CORE-010),
 * depuis l'inscription jusqu'au retrait, en passant par la version, le
 * terme, l'évolution vers une version suivante, la soumission, l'analyse de
 * compatibilité obligatoire, la projection obligatoire, la conformité
 * obligatoire et l'activation.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/vocabulaire_v1_p1.php
 */

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-vocabulaire-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'vocabulaire' => $temp . '-vocabulaire.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('v', 32)),
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
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
    'VOCABULARY_REGISTRY_URL' => '',
    'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
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
PolitiquesMagasin::connecter();
$ctr16 = new Ctr16(AccesMagasin::connecter());
$secretAutorite = 'Secret-Vocabulaire-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
// Bootstrappe `POL-VOCABULAIRE-V1` (auto-gouvernance) et les trente-trois
// vocabulaires déjà exploités — vingt-quatre repris depuis les constantes
// réelles de CAP-CORE-001/006/007/011, plus neuf propres à CAP-CORE-002
// depuis l'ouverture du registre des organisations. Sans
// `POL-VOCABULAIRE-V1`, toute écriture gouvernée sur ce registre, y compris
// depuis ce test, serait refusée par CTR-03.
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:vocabulaire:bootstrap');
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

echo "INTÉGRATION HTTP — VOCABULAIRE V1 P1 (CAP-CORE-010)\n\n";

$REF = 'VOC-V1-P1-TEST-001';

// 1 — sans session, aucune écriture n'est possible.
$sansSession = $requete('POST', '/api/v1/vocabulaires', [
    'reference' => $REF, 'namespace' => 'v1.p1.test', 'nom' => 'X', 'domaine' => 'test',
    'portee' => 'CORE', 'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'x',
]);
$verifier(
    $sansSession['statut'] === 401
        && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'aucune inscription n’est possible sans session',
);

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$sessionAutorite = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($sessionAutorite !== '', 'l’autorité ouvre une session Core');

$identiteTiers = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'personne', 'libelle' => 'Tiers V1 P1 Vocabulaire',
], $sessionAutorite);
$referenceTiers = (string) ($identiteTiers['corps']['identite']['reference'] ?? '');
$ctr16->inscrireAuthentificateur($referenceTiers, 'Secret-Tiers-Vocabulaire-1!');
$sessionTiersReponse = $requete('POST', '/api/v1/sessions', [
    'entite' => $referenceTiers, 'secret' => 'Secret-Tiers-Vocabulaire-1!',
]);
$sessionTiers = (string) ($sessionTiersReponse['corps']['jeton'] ?? '');
$verifier($sessionTiers !== '', 'le tiers ouvre aussi une session Core, sans aucun droit de gouvernance');

// 2 — la liste porte déjà les trente-trois vocabulaires repris.
$listeInitiale = $requete('GET', '/api/v1/vocabulaires', null, $sessionAutorite);
$referencesInitiales = array_column($listeInitiale['corps']['vocabulaires'] ?? [], 'reference');
$verifier(
    $listeInitiale['statut'] === 200
        && count($referencesInitiales) === 33
        && in_array('VOC-GAMAD-IDENTITE-TYPE', $referencesInitiales, true)
        && in_array('VOC-GAMAD-CONTRAT-TYPE-OPERATION', $referencesInitiales, true)
        && in_array('VOC-GAMAD-ORGANISATION-TYPE', $referencesInitiales, true),
    'la liste porte les trente-trois vocabulaires repris par le bootstrap',
);

// 3 — inscription gouvernée : naît sans version active.
$inscription = $requete('POST', '/api/v1/vocabulaires', [
    'reference' => $REF, 'namespace' => 'v1.p1.test', 'nom' => 'Vocabulaire V1 P1', 'domaine' => 'test',
    'portee' => 'CORE', 'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'test HTTP CAP-CORE-010',
], $sessionAutorite);
$fiche = $requete('GET', "/api/v1/vocabulaires/{$REF}", null, $sessionAutorite);
$verifier(
    $inscription['statut'] === 201
        && ($inscription['corps']['resultat']['reference'] ?? null) === $REF
        && $fiche['statut'] === 200
        && array_key_exists('version_active', $fiche['corps']['vocabulaire'] ?? [])
        && $fiche['corps']['vocabulaire']['version_active'] === null,
    'l’autorité inscrit un vocabulaire, sans version active',
);

$doublon = $requete('POST', '/api/v1/vocabulaires', [
    'reference' => $REF, 'namespace' => 'v1.p1.doublon', 'nom' => 'Doublon', 'domaine' => 'test',
    'portee' => 'CORE', 'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'x',
], $sessionAutorite);
$verifier(
    $doublon['statut'] === 409
        && ($doublon['corps']['resultat']['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    'une référence déjà inscrite est refusée avec un conflit HTTP',
);

// 4 — un tiers sans droit ne peut ni inscrire ni administrer.
$inscriptionTiers = $requete('POST', '/api/v1/vocabulaires', [
    'reference' => 'VOC-V1-P1-INTERDIT', 'namespace' => 'v1.p1.interdit', 'nom' => 'X', 'domaine' => 'test',
    'portee' => 'CORE', 'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => 'x',
], $sessionTiers);
$verifier(
    $inscriptionTiers['statut'] === 403
        && ($inscriptionTiers['corps']['erreur'] ?? null) === 'AUTORISATION_REFUSEE',
    'un tiers sans droit ne peut pas inscrire de vocabulaire : CTR-03 refuse',
);

// 5 — version, terme, soumission : immuable ensuite.
$version = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions", ['version' => '1.0.0'], $sessionAutorite);
$verifier(
    $version['statut'] === 201 && ($version['corps']['resultat']['etat'] ?? null) === 'BROUILLON',
    'une version se crée en BROUILLON',
);

$terme = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/1.0.0/termes", [
    'reference' => 'TERM-V1-P1-ACTIF', 'code' => 'ACTIF', 'definition' => 'le terme est actif', 'type_semantique' => 'ETAT',
], $sessionAutorite);
$verifier($terme['statut'] === 201, 'un terme se déclare sur la version en BROUILLON');

$soumission = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/1.0.0/soumission", [], $sessionAutorite);
$verifier(
    $soumission['statut'] === 200 && ($soumission['corps']['resultat']['etat'] ?? null) === 'EN_VALIDATION',
    'la soumission fige le contenu et transite vers EN_VALIDATION',
);

// Les refus VERSION_IMMUABLE, ANALYSE_MANQUANTE et PROJECTION_MANQUANTE sont
// déjà éprouvés exhaustivement par `vocabulaire_p3.php` ; cette épreuve HTTP
// se concentre sur le parcours heureux et les codes HTTP, en restant sous la
// limite de débit partagée par toutes les routes POST gouvernées de l'API
// (`throttle:20,1`, par IP — voir `contrats_v1_p1.php`).
$analyse = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/1.0.0/analyse", [], $sessionAutorite);
$verifier(
    $analyse['statut'] === 201 && ($analyse['corps']['resultat']['resultat'] ?? null) === 'COMPATIBLE',
    'la première version d’un vocabulaire s’analyse toujours COMPATIBLE',
);

$projection = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/1.0.0/projections", ['type_projection' => 'JSON'], $sessionAutorite);
$verifier($projection['statut'] === 201, 'une projection JSON se génère pour cette version exacte');

$conformite = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/1.0.0/conformite", [
    'resultat' => 'CONFORME', 'consommateur_reference' => 'CAP-CORE-010', 'type_consommateur' => 'CAPACITE',
], $sessionAutorite);
$verifier($conformite['statut'] === 201, 'la conformité s’enregistre pour cette version exacte');

$activation = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/1.0.0/activation", [], $sessionAutorite);
$verifier(
    $activation['statut'] === 200 && ($activation['corps']['resultat']['etat'] ?? null) === 'ACTIVE',
    'l’activation réussit une fois l’analyse, la projection et la conformité acquises',
);

// 6 — évolution vers une seconde version : la lignée est reconnue par
// l'analyse de compatibilité, pas seulement la disparition du terme d'avant.
$version2 = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions", ['version' => '2.0.0'], $sessionAutorite);
$verifier($version2['statut'] === 201, 'une seconde version se crée en BROUILLON');

$evolution = $requete('POST', '/api/v1/termes/TERM-V1-P1-ACTIF/evolution', [
    'nouvelle_version' => '2.0.0', 'reference' => 'TERM-V1-P1-ACTIF-2', 'code' => 'ACTIF_RENOMME',
], $sessionAutorite);
$verifier(
    $evolution['statut'] === 201 && ($evolution['corps']['resultat']['evolue_depuis'] ?? null) === 'TERM-V1-P1-ACTIF',
    'le terme évolue vers la seconde version sous une référence neuve, reliée à l’ancienne',
);

$soumission2 = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/2.0.0/soumission", [], $sessionAutorite);
$verifier($soumission2['statut'] === 200, 'la seconde version se soumet');

$analyse2 = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/2.0.0/analyse", [], $sessionAutorite);
$divergences2 = array_column($analyse2['corps']['resultat']['divergences'] ?? [], 'type');
$verifier(
    $analyse2['statut'] === 201
        && ($analyse2['corps']['resultat']['resultat'] ?? null) === 'RUPTURE'
        && in_array('code_modifie', $divergences2, true)
        && !in_array('terme_supprime', $divergences2, true),
    'le changement de code est reconnu par lignée comme code_modifie, pas comme une suppression',
);

// 7 — dépréciation puis retrait, référence de version non réutilisable.
$depreciation = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/1.0.0/depreciation", [], $sessionAutorite);
$verifier(
    $depreciation['statut'] === 200 && ($depreciation['corps']['resultat']['etat'] ?? null) === 'DEPRECIEE',
    'la dépréciation transite vers DEPRECIEE',
);

$retrait = $requete('POST', "/api/v1/vocabulaires/{$REF}/versions/1.0.0/retrait", [], $sessionAutorite);
$verifier(
    $retrait['statut'] === 200 && ($retrait['corps']['resultat']['etat'] ?? null) === 'RETIREE',
    'le retrait transite vers RETIREE',
);

// 8 — lecture des termes de la version active.
$termeShow = $requete('GET', '/api/v1/termes/TERM-V1-P1-ACTIF-2', null, $sessionAutorite);
$verifier(
    $termeShow['statut'] === 200 && ($termeShow['corps']['terme']['code'] ?? null) === 'ACTIF_RENOMME',
    'un terme se résout par sa référence, avec son code réel',
);

// Audit : le parcours est chaîné, sans secret.
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:' . $fichiers['journal']);
$evenements = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE categorie = 'VOCABULAIRE'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $evenements >= 10,
    'le parcours du vocabulaire est chaîné dans l’audit CAP-CORE-013',
);

echo "\n";
if ($echecs === 0) {
    echo "Vocabulaire v1 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Vocabulaire v1 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
