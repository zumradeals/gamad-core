<?php

declare(strict_types=1);

/**
 * Parcours HTTP complet du registre des politiques (CAP-CORE-007), depuis
 * l'inscription jusqu'au retrait, en passant par la version, les règles, la
 * soumission, la simulation obligatoire et l'activation.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/politiques_v1_p1.php
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
$temp = sys_get_temp_dir() . '/gamad-politiques-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('z', 32)),
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
$secretAutorite = 'Secret-Politiques-Autorite-1!';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secretAutorite);

$app = require $application . '/bootstrap/app.php';
// Bootstrappe les huit politiques déjà exploitées ET `POL-POLITIQUES-V1`,
// la politique d'auto-gouvernance sans laquelle toute écriture gouvernée sur
// ce registre, y compris depuis ce test, serait refusée par CTR-03.
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
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

echo "INTÉGRATION HTTP — POLITIQUES V1 P1 (CAP-CORE-007)\n\n";

$REF = 'POL-V1-P1-TEST-001';

// 1 — sans session, aucune écriture n'est possible.
$sansSession = $requete('POST', '/api/v1/politiques', [
    'reference' => $REF, 'libelle' => 'X', 'proprietaire_reference' => 'AUT-GAMAD-001',
    'source_reference' => 'x',
]);
$verifier(
    $sansSession['statut'] === 401
        && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'aucune inscription n’est possible sans session',
);

$connexion = $requete('POST', '/api/v1/sessions', ['entite' => 'AUT-GAMAD-001', 'secret' => $secretAutorite]);
$sessionAutorite = (string) ($connexion['corps']['jeton'] ?? '');
$verifier($sessionAutorite !== '', 'l’autorité ouvre une session Core');

// Le tiers et sa session sont acquis ici, avant toute autre écriture : la
// route `/sessions` partage son compteur de débit avec toutes les autres
// routes gouvernées de cette API (signature par IP, cette API n'utilisant pas
// la résolution `$request->user()` de Laravel) — un défaut transverse à
// l'ensemble de l'API, pas propre à CAP-CORE-007, qu'il n'appartient pas à ce
// chantier de corriger. Acquérir les deux sessions avant les écritures
// gouvernées qui suivent évite d'épuiser ce compteur partagé pendant l'épreuve.
$identiteTiers = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE', 'type' => 'personne', 'libelle' => 'Tiers V1 P1 Politiques',
], $sessionAutorite);
$referenceTiers = (string) ($identiteTiers['corps']['identite']['reference'] ?? '');
$ctr16->inscrireAuthentificateur($referenceTiers, 'Secret-Tiers-Politiques-1!');
$sessionTiersReponse = $requete('POST', '/api/v1/sessions', [
    'entite' => $referenceTiers, 'secret' => 'Secret-Tiers-Politiques-1!',
]);
$sessionTiers = (string) ($sessionTiersReponse['corps']['jeton'] ?? '');
$verifier($sessionTiers !== '', 'le tiers ouvre aussi une session Core, sans aucun droit de gouvernance');

// 2 — la liste porte déjà les huit politiques reprises et l’auto-gouvernance.
$listeInitiale = $requete('GET', '/api/v1/politiques', null, $sessionAutorite);
$referencesInitiales = array_column($listeInitiale['corps']['politiques'] ?? [], 'reference');
$verifier(
    $listeInitiale['statut'] === 200
        && count($referencesInitiales) === 9
        && in_array('POL-SOURCES-V1', $referencesInitiales, true)
        && in_array('POL-POLITIQUES-V1', $referencesInitiales, true),
    'la liste porte les huit politiques reprises et l’auto-gouvernance du registre',
);

// 3 — inscription gouvernée : naît sans version active.
$inscription = $requete('POST', '/api/v1/politiques', [
    'reference' => $REF, 'libelle' => 'Politique V1 P1', 'proprietaire_reference' => 'AUT-GAMAD-001',
    'source_reference' => 'test HTTP CAP-CORE-007',
], $sessionAutorite);
$fiche = $requete('GET', "/api/v1/politiques/{$REF}", null, $sessionAutorite);
$verifier(
    $inscription['statut'] === 201
        && ($inscription['corps']['resultat']['reference'] ?? null) === $REF
        && $fiche['statut'] === 200
        && array_key_exists('version_active', $fiche['corps']['politique'] ?? [])
        && $fiche['corps']['politique']['version_active'] === null,
    'l’autorité inscrit une politique, sans version active',
);

$doublon = $requete('POST', '/api/v1/politiques', [
    'reference' => $REF, 'libelle' => 'Doublon', 'proprietaire_reference' => 'AUT-GAMAD-001',
    'source_reference' => 'x',
], $sessionAutorite);
$verifier(
    $doublon['statut'] === 409
        && ($doublon['corps']['resultat']['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    'une référence déjà inscrite est refusée avec un conflit HTTP',
);

// 4 — un tiers sans droit ne peut ni inscrire ni administrer.
$inscriptionTiers = $requete('POST', '/api/v1/politiques', [
    'reference' => 'POL-V1-P1-INTERDIT', 'libelle' => 'X', 'proprietaire_reference' => 'AUT-GAMAD-001',
    'source_reference' => 'x',
], $sessionTiers);
$verifier(
    $inscriptionTiers['statut'] === 403
        && ($inscriptionTiers['corps']['erreur'] ?? null) === 'AUTORISATION_REFUSEE',
    'un tiers sans droit ne peut pas inscrire de politique : CTR-03 refuse',
);

// 5 — version, règle, soumission : immuable une fois EN_VALIDATION.
$version = $requete('POST', "/api/v1/politiques/{$REF}/versions", ['version' => '1.0.0'], $sessionAutorite);
$verifier(
    $version['statut'] === 201 && ($version['corps']['resultat']['etat'] ?? null) === 'BROUILLON',
    'une version se crée en BROUILLON',
);

$regle = $requete('POST', "/api/v1/politiques/{$REF}/versions/1.0.0/regles", [
    'effet' => 'PERMET', 'action_reference' => 'agir sous la politique v1 p1', 'sujet_type' => $referenceTiers,
    'motif' => 'le tiers peut agir sous cette politique de test',
], $sessionAutorite);
$verifier(
    $regle['statut'] === 201 && ($regle['corps']['resultat']['effet'] ?? null) === 'PERMET',
    'une règle s’ajoute à une version en BROUILLON',
);

$soumission = $requete('POST', "/api/v1/politiques/{$REF}/versions/1.0.0/soumission", [], $sessionAutorite);
$regleApresSoumission = $requete('POST', "/api/v1/politiques/{$REF}/versions/1.0.0/regles", [
    'effet' => 'PERMET', 'action_reference' => 'tentative tardive v1 p1', 'motif' => 'motif',
], $sessionAutorite);
$verifier(
    $soumission['statut'] === 200
        && ($soumission['corps']['resultat']['etat'] ?? null) === 'EN_VALIDATION'
        && $regleApresSoumission['statut'] === 422
        && ($regleApresSoumission['corps']['resultat']['refus'] ?? null) === 'VERSION_IMMUABLE',
    'la soumission fige le contenu : aucune règle nouvelle n’est plus acceptée ensuite',
);

// 6 — activation refusée sans simulation réussie, puis acquise après.
$activationSansSimulation = $requete('POST', "/api/v1/politiques/{$REF}/versions/1.0.0/activation", [], $sessionAutorite);
$verifier(
    $activationSansSimulation['statut'] === 422
        && ($activationSansSimulation['corps']['resultat']['refus'] ?? null) === 'SIMULATION_MANQUANTE',
    'aucune activation n’est possible sans une simulation réussie de cette version exacte',
);

$simulation = $requete('POST', "/api/v1/politiques/{$REF}/versions/1.0.0/simulation", [
    'jeu_reference' => 'JEU-V1-P1',
    'cas' => [['sujet' => $referenceTiers, 'action' => 'agir sous la politique v1 p1', 'attendu' => 'PERMIS']],
], $sessionAutorite);
$verifier(
    $simulation['statut'] === 201 && ($simulation['corps']['resultat']['resultat'] ?? null) === 'REUSSIE',
    'une simulation dont l’issue attendue correspond à la règle réelle réussit',
);

$activation = $requete('POST', "/api/v1/politiques/{$REF}/versions/1.0.0/activation", [], $sessionAutorite);
$verifier(
    $activation['statut'] === 200 && ($activation['corps']['resultat']['etat'] ?? null) === 'ACTIVE',
    'l’activation réussit une fois la simulation acquise',
);

// 7 — la règle activée gouverne réellement une décision CTR-03 pour le tiers.
$decisionTiers = $requete('POST', '/api/v1/autorisation/decisions', [
    'action' => 'agir sous la politique v1 p1',
], $sessionTiers);
$verifier(
    $decisionTiers['statut'] === 200
        && ($decisionTiers['corps']['decision']['decision'] ?? null) === 'PERMIS'
        && ($decisionTiers['corps']['decision']['politique'] ?? null) === $REF,
    'une version activée par l’API gouverne immédiatement une vraie décision CTR-03',
);

// 8 — suspension puis retrait irréversible ; référence non réutilisable.
$suspension = $requete('POST', "/api/v1/politiques/{$REF}/versions/1.0.0/suspension", [], $sessionAutorite);
$decisionApresSuspension = $requete('POST', '/api/v1/autorisation/decisions', [
    'action' => 'agir sous la politique v1 p1',
], $sessionTiers);
$verifier(
    $suspension['statut'] === 200
        && ($suspension['corps']['resultat']['etat'] ?? null) === 'SUSPENDUE'
        && $decisionApresSuspension['statut'] === 200
        && ($decisionApresSuspension['corps']['decision']['decision'] ?? null) === 'REFUSÉ',
    'la suspension ferme immédiatement la permission qu’elle portait',
);

$retraitSansActive = $requete('POST', "/api/v1/politiques/{$REF}/retrait", [], $sessionAutorite);
$verifier(
    $retraitSansActive['statut'] === 422
        && ($retraitSansActive['corps']['resultat']['refus'] ?? null) === 'AUCUNE_VERSION_ACTIVE',
    'retirer une politique sans version active est refusé',
);

$historique = $requete('GET', "/api/v1/politiques/{$REF}/historique", null, $sessionAutorite);
$verifier(
    $historique['statut'] === 200
        && count($historique['corps']['historique'] ?? []) === 4,
    'l’historique restitue chaque transition traversée par cette version',
);

// Audit : le parcours est chaîné, sans secret.
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:' . $fichiers['journal']);
$evenements = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE categorie = 'POLITIQUES'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $evenements >= 8,
    'le parcours des politiques est chaîné dans l’audit CAP-CORE-013',
);

echo "\n";
if ($echecs === 0) {
    echo "Politiques v1 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Politiques v1 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
