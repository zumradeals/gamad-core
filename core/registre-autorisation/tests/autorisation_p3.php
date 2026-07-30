<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-004 — refus par défaut et opposabilité des limites.
 *
 * Garde propre à la capacité (doctrine d'ADOPTION-0035, Art. 2.2).
 *
 * Ce que le test éprouve :
 *   · une action inconnue est REFUSÉE (INV-27) — l'absence de règle n'est
 *     jamais une permission ;
 *   · une compétence de l'Article 48 est PERMISE ;
 *   · une limite de l'Article 49 est REFUSÉE AU TITULAIRE DU MANDAT LUI-MÊME
 *     (INV-30) — c'est l'assertion centrale de cette capacité ;
 *   · toute décision porte un motif non vide (INV-28) ;
 *   · les règles proviennent du corpus, non du code (INV-29).
 *
 * Exécution : php core/registre-autorisation/tests/autorisation_p3.php
 */

use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../src/Ctr03.php';

$fichier = sys_get_temp_dir() . '/regn-autz-p3-' . getmypid() . '.sqlite';
@unlink($fichier);
putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $fichier);

$pdo = Db::connect();
(new Ingestion($pdo, REGN_CORPUS))->executer();
$ctr03 = new Ctr03($pdo);

$echecs = 0;
$verifier = function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — REFUS PAR DÉFAUT ET OPPOSABILITÉ DES LIMITES (CAP-CORE-004)\n\n";

// Le titulaire du mandat, tel que le Registre des autorités l'inscrit.
$titulaire = 'AUT-GAMAD-001';

// --- INV-27 : une action qu'aucune politique ne prévoit est refusée.
$inconnue = $ctr03->autoriser($titulaire, 'déployer une application en production');
$verifier(
    $inconnue['decision'] === 'REFUSÉ' && $inconnue['politique'] === null,
    "une action inconnue est REFUSÉE par défaut (INV-27)",
);

// --- Une compétence de l'Article 48 est permise.
$competence = $ctr03->autoriser($titulaire, 'proposition des textes Genesis II');
$verifier(
    $competence['decision'] === 'PERMIS' && $competence['politique'] === 'POL-MANDAT-COMPETENCES',
    "une compétence de l'Article 48 est PERMISE",
);

// --- La politique d'inscription permet précisément l'autorité désignée.
$inscription = $ctr03->autoriser($titulaire, 'inscrire une identité', 'personne');
$verifier(
    $inscription['decision'] === 'PERMIS'
        && $inscription['politique'] === 'POL-INSCRIPTION-IDENTITES-V1'
        && str_contains((string) $inscription['source'], 'POLITIQUE-INSCRIPTION-IDENTITES-0001'),
    "l'autorité désignée peut inscrire une identité sous la politique adoptée",
);
$agentSansDroit = $ctr03->autoriser('AGENT-IA-002', 'inscrire une identité', 'personne');
$verifier(
    $agentSansDroit['decision'] === 'REFUSÉ' && $agentSansDroit['politique'] === null,
    "la politique d'inscription ne donne aucun droit à l'agent qui l'implémente",
);

// --- INV-30 : une limite de l'Article 49 est refusée AU TITULAIRE LUI-MÊME.
$limites = [
    'falsifier une source ou l’histoire',
    'effacer injustement une preuve',
    'convertir le Core en propriété personnelle',
    'prononcer G0 sans l’acte distinct et les contrôles requis',
];
foreach ($limites as $limite) {
    $d = $ctr03->autoriser($titulaire, $limite);
    $verifier(
        $d['decision'] === 'REFUSÉ' && $d['politique'] === 'POL-MANDAT-LIMITES',
        "REFUSÉ au titulaire du mandat : « {$limite} » (INV-30)",
    );
}

// --- INV-28 : toute décision porte un motif.
$sansMotif = 0;
foreach ([$inconnue, $competence] as $d) {
    if (trim((string) ($d['motif'] ?? '')) === '') {
        $sansMotif++;
    }
}
$verifier($sansMotif === 0, 'toute décision porte un motif non vide (INV-28)');

// --- INV-29 : les règles viennent du corpus, avec leur source citée.
$interdits = $ctr03->resoudreInterdits();
$verifier(
    count($interdits) >= 7 && str_contains((string) ($interdits[0]['source'] ?? ''), 'Art. 49'),
    sprintf('%d interdits dérivés du corpus, source citée (INV-29)', count($interdits)),
);

// --- Les interdits s'opposent à tout sujet, non à une catégorie.
$opposableATous = true;
foreach ($interdits as $i) {
    if (!str_contains((string) $i['opposable_a'], 'tout sujet')) {
        $opposableATous = false;
    }
}
$verifier($opposableATous, 'les interdits sont opposables à tout sujet, titulaire compris');

@unlink($fichier);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-004 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
