<?php

declare(strict_types=1);

/**
 * Épreuve de la console de lecture du Matching (CAP-CORE-021).
 *
 * La console n'expose aucune écriture (voir MatchingConsoleController) :
 * cette épreuve vérifie seulement que les trois écrans (tableau de bord,
 * contexte, demande) se rendent sans erreur et affichent l'information
 * attendue, sur des données semées directement via `RegistreMatching`.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/matching_console_p1.php
 */

use App\Application\Matching\AccesMatching;
use App\Http\Controllers\MatchingConsoleController;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\MoteurMatching\Magasin as MatchingMagasin;
use Gamad\MoteurMatching\RegistreMatching;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-matching-console-' . getmypid();
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
    'matching' => $temp . '-matching.sqlite',
];
foreach ($fichiers as $f) {
    @unlink($f);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $f) {
        @unlink($f);
    }
});

$environnement = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('c', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php', 'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php', 'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'errorlog',
    'DATABASE_URL' => '', 'SQLITE_PATH' => $fichiers['index'],
    'MAGASIN_URL' => '', 'MAGASIN_PATH' => $fichiers['acces'],
    'IDENTITY_REGISTRY_URL' => '', 'IDENTITY_REGISTRY_PATH' => $fichiers['identites'],
    'JOURNAL_OPERATIONNEL_URL' => '', 'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
    'ORGANIZATION_REGISTRY_URL' => '', 'ORGANIZATION_REGISTRY_PATH' => $fichiers['organisations'],
    'PRODUCT_REGISTRY_URL' => '', 'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'CONTRACT_REGISTRY_URL' => '', 'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
    'VOCABULARY_REGISTRY_URL' => '', 'VOCABULARY_REGISTRY_PATH' => $fichiers['vocabulaire'],
    'POLICY_REGISTRY_URL' => '', 'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
    'REALM_REGISTRY_URL' => '', 'REALM_REGISTRY_PATH' => $fichiers['realms'],
    'SOURCE_REGISTRY_URL' => '', 'SOURCE_REGISTRY_PATH' => $fichiers['sources'],
    'EVENT_JOURNAL_URL' => '', 'EVENT_JOURNAL_PATH' => $fichiers['evenements'],
    'MATCHING_REGISTRY_URL' => '', 'MATCHING_REGISTRY_PATH' => $fichiers['matching'],
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
$ctr16->inscrireAuthentificateur(PolitiqueInscription::AUTORITE_INSCRIPTION, 'Secret-Matching-Console-1!');

$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:contrats:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:matching:bootstrap-gouvernance');
$app->make(Kernel::class)->bootstrap();
$app->make('view')->share('errors', new ViewErrorBag());

$acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
$registre = new RegistreMatching(MatchingMagasin::connecter());
$contexte = $registre->inscrireContexte([
    'code_canonique' => 'WASPLEX_AUDIENCE', 'nom' => 'Test console', 'finalite' => 'Épreuve de console',
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0', 'classification' => 'INTERNE',
    'supervision_humaine' => 'AUCUNE', 'score_autorise' => true, 'segment_autorise' => true,
    'activation_autorisee' => true, 'mesure_autorisee' => true, 'source_reference' => 'SRC-TEST',
], $acteur);
$registre->activerContexte($contexte['reference'], $acteur);
$profil = $registre->compilerProfil([
    'politique_reference' => 'POL-MATCHING-V1', 'politique_version' => '1.0.0', 'contexte_reference' => $contexte['reference'],
    'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'criteres' => [[
        'critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'traitement_inconnu' => 'INDETERMINE',
        'obligatoire' => true, 'poids' => 1.0, 'sources_autorisees' => ['SRC-TEST'], 'facteur_public_autorise' => true,
    ]],
], $acteur);
$registre->activerProfil($profil['reference'], 'PRV-TEST-SIMULATION', $acteur);
$demande = $registre->soumettreDemande([
    'idempotency_key' => 'IDEMP-CONSOLE-001', 'consommateur_produit' => 'PRD-GAMAD-002', 'contexte_reference' => $contexte['reference'],
    'finalite_reference' => 'Épreuve de console', 'realm_reference' => 'RLM-GAMAD-001', 'environnement' => 'TEST',
    'mode_resultat' => 'CLASSEMENT', 'classification' => 'INTERNE', 'contrat_reference' => 'CTR-MAT-01', 'contrat_version' => '1',
    'correlation_id' => 'COR-CONSOLE-001',
    'objets' => [['role_objet' => 'CANDIDAT', 'objet_type' => 'PERSONNE', 'objet_reference_externe' => 'CAND-CONSOLE-A', 'source_reference' => 'SRC-TEST', 'contrat_reference' => 'CTR-MAT-02', 'valide_depuis' => '2026-01-01T00:00:00Z', 'classification' => 'INTERNE']],
    'criteres' => [['critere_reference' => 'CRT-REGION', 'operateur' => 'EQ', 'valeur_normalisee' => 'ABJ', 'obligatoire' => true, 'origine' => 'POLITIQUE']],
], $acteur);
$registre->enregistrerSignal([
    'sujet_type' => 'PERSONNE', 'sujet_reference' => 'CAND-CONSOLE-A', 'signal_code' => 'CRT-REGION', 'valeur_type' => 'TEXTE',
    'valeur_normalisee' => 'ABJ', 'source_reference' => 'SRC-TEST', 'finalite_reference' => 'Épreuve de console',
    'realm_reference' => 'RLM-GAMAD-001', 'contrat_reference' => 'CTR-MAT-03', 'contrat_version' => '1',
    'observation_le' => '2026-08-01T00:00:00Z', 'valide_jusqua' => '2027-08-01T00:00:00Z', 'classification' => 'INTERNE',
]);
$registre->executer($demande['reference'], [], $acteur, '2026-08-15T00:00:00Z');

$controleur = $app->make(MatchingConsoleController::class);
$acces = $app->make(AccesMatching::class);

$requete = static function (string $uri) use ($acteur): Request {
    $request = Request::create($uri, 'GET');
    $request->attributes->set('gamad_entite', $acteur);
    $request->attributes->set('gamad_assurance', 'AS1');

    return $request;
};

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "INTÉGRATION — CONSOLE DU MATCHING P1 (CAP-CORE-021)\n\n";

$tableauDeBord = $controleur->index($requete('/matching'), $acces)->render();
$verifier(
    str_contains($tableauDeBord, $contexte['reference']) && str_contains($tableauDeBord, $demande['reference']) && !str_contains($tableauDeBord, '<pre>'),
    'le tableau de bord liste le contexte et la demande sans erreur de vue',
);

$ficheContexte = $controleur->showContexte($requete("/matching/contextes/{$contexte['reference']}"), $acces, $contexte['reference'])->render();
$verifier(
    str_contains($ficheContexte, 'ACTIF') && str_contains($ficheContexte, $profil['reference']) && !str_contains($ficheContexte, '<pre>'),
    'la fiche de contexte affiche l’état actif et le profil compilé',
);

$ficheDemande = $controleur->showDemande($requete("/matching/demandes/{$demande['reference']}"), $acces, $demande['reference'])->render();
// candidat_reference du résultat est la référence interne de l'objet (OBJ-GAMAD-*), pas la
// référence externe du candidat — indirection déjà présente dans le schéma (doc 02 §13).
$verifier(
    str_contains($ficheDemande, 'TERMINEE') && str_contains($ficheDemande, 'CORRESPONDANCE_FORTE') && !str_contains($ficheDemande, '<pre>'),
    'la fiche de demande affiche le résultat classé sans erreur de vue',
);

$verifier(str_contains($ficheDemande, 'Favorables'), 'l’explication du résultat est déroulée sur la fiche');

try {
    $controleur->showDemande($requete('/matching/demandes/DEM-GAMAD-INEXISTANTE'), $acces, 'DEM-GAMAD-INEXISTANTE');
    $verifier(false, 'une demande inconnue renvoie 404');
} catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
    $verifier(true, 'une demande inconnue renvoie 404');
}

echo "\n";
if ($echecs === 0) {
    echo "Intégration console Matching : ÉTABLIE.\n";
    exit(0);
}

echo "Intégration console Matching : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
