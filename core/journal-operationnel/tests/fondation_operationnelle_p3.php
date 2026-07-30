<?php

declare(strict_types=1);

/**
 * Garde de comportement du premier socle opérationnel P0/P1.
 *
 * Exécution :
 *   php core/journal-operationnel/tests/fondation_operationnelle_p3.php
 */

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin;

require __DIR__ . '/../src/Schema.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/Journal.php';

$fichier = sys_get_temp_dir() . '/gamad-journal-p3-' . getmypid() . '.sqlite';
@unlink($fichier);
register_shutdown_function(static fn () => @unlink($fichier));

putenv('JOURNAL_OPERATIONNEL_URL=');
putenv('JOURNAL_OPERATIONNEL_PATH=' . $fichier);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE — CORE OPERATIONAL FOUNDATION V1\n\n";

$pdo = Magasin::connecter();
$journal = new Journal($pdo);

$ouverture = $journal->enregistrer([
    'categorie' => 'SECURITE',
    'type' => 'OUVERTURE_SESSION_API',
    'acteur' => 'AUT-GAMAD-001',
    'action' => 'ouvrir une session API',
    'decision' => 'ACCEPTEE',
    'donnees' => [
        'assurance' => 'AS1',
        'secret' => 'CE-SECRET-NE-DOIT-JAMAIS-ETRE-ECRIT',
        'authorization' => 'Bearer JETON-INTERDIT',
    ],
]);
$decision = $journal->enregistrer([
    'categorie' => 'AUTORISATION',
    'type' => 'DECISION_AUTORISATION',
    'acteur' => 'AUT-GAMAD-001',
    'action' => 'inscrire une identité',
    'ressource' => 'personne',
    'decision' => 'REFUSE',
    'motif' => 'refus par défaut',
    'correlation_id' => $ouverture['correlation_id'],
]);

$verifier(
    str_starts_with((string) $ouverture['reference'], 'EVT-OP-')
        && strlen((string) $ouverture['empreinte']) === 64,
    'chaque événement reçoit une référence et une empreinte SHA-256',
);
$verifier(
    $decision['correlation_id'] === $ouverture['correlation_id'],
    'la décision reste corrélée à l’authentification',
);

$brut = (string) $pdo->query(
    "SELECT donnees FROM evenement_operationnel WHERE reference = " . $pdo->quote($ouverture['reference'])
)->fetchColumn();
$verifier(
    !str_contains($brut, 'CE-SECRET-NE-DOIT-JAMAIS-ETRE-ECRIT')
        && !str_contains($brut, 'JETON-INTERDIT')
        && substr_count($brut, '[EXPURGÉ]') === 2,
    'secrets et jetons sont expurgés avant persistance',
);

$diagnostic = $journal->verifierIntegrite();
$verifier(
    $diagnostic['valide'] === true
        && $diagnostic['evenements'] === 2
        && $diagnostic['tete'] === $decision['empreinte'],
    'le chaînage complet est vérifiable',
);

$mutationRefusee = false;
try {
    $pdo->exec("UPDATE evenement_operationnel SET decision = 'PERMIS' WHERE sequence_id = 2");
} catch (\PDOException) {
    $mutationRefusee = true;
}
$verifier($mutationRefusee, 'UPDATE est refusé par le magasin append-only');

// Contre-épreuve : un attaquant administrateur peut supprimer le verrou de
// schéma. La vérification cryptographique doit alors détecter la falsification.
$pdo->exec('DROP TRIGGER evenement_operationnel_refuser_update');
$pdo->exec("UPDATE evenement_operationnel SET decision = 'PERMIS' WHERE sequence_id = 2");
$falsifie = $journal->verifierIntegrite();
$verifier(
    $falsifie['valide'] === false
        && in_array("empreinte invalide à {$decision['reference']}", $falsifie['ecarts'], true),
    'une falsification après neutralisation du verrou brise la preuve',
);

$racine = dirname(__DIR__, 3);
$routes = (string) file_get_contents($racine . '/apps/console-laravel/routes/api.php');
$controleur = (string) file_get_contents(
    $racine . '/apps/console-laravel/app/Http/Controllers/Api/V1/IdentiteController.php'
);
$casUsage = (string) file_get_contents(
    $racine . '/apps/console-laravel/app/Application/Identites/InscrireIdentite.php'
);
$verifier(
    str_contains($routes, "Route::prefix('v1')")
        && str_contains($routes, "Route::middleware('gamad.api')")
        && str_contains($routes, "Route::post('/identites'"),
    'l’écriture d’identité est versionnée et placée derrière l’authentification API',
);
$positionDecision = strpos($casUsage, "->autoriser(");
$positionEcriture = strpos($casUsage, "->inscrireIdentite(");
$verifier(
    str_contains($controleur, 'InscrireIdentite $inscrire')
        && $positionDecision !== false
        && $positionEcriture !== false
        && $positionDecision < $positionEcriture
        && str_contains($casUsage, "\$decision['decision'] === 'PERMIS'"),
    'CAP-CORE-004 décide avant toute inscription et le refus est appliqué',
);

echo "\n";
if ($echecs === 0) {
    echo "Preuve : ÉTABLIE. Le premier socle P0/P1 tient ses invariants testés.\n";
    exit(0);
}

echo "Preuve : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
