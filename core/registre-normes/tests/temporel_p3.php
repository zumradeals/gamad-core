<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-007 — reconstruction temporelle.
 *
 * Vérifie qu'à une date passée donnée, le service restitue l'état RÉELLEMENT
 * en vigueur à cette date, et que le diagnostic de l'index constate ce qui est
 * réellement présent. L'index est initialisé depuis la baseline opérationnelle,
 * sans lecture d'aucun fichier documentaire.
 *
 * Exécution : php core/registre-normes/tests/temporel_p3.php
 * Code de sortie : 0 si la garde passe, 1 sinon.
 */

use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Ctr04;
use Gamad\RegistreNormes\Db;

require __DIR__ . '/../bootstrap.php';

// Base éphémère dédiée au test, indépendante de tout déploiement.
$fichier = sys_get_temp_dir() . '/regn-p3-' . getmypid() . '.sqlite';
@unlink($fichier);
register_shutdown_function(static fn () => @unlink($fichier));
putenv('DATABASE_URL='); // force SQLite
putenv('SQLITE_PATH=' . $fichier);

$pdo = Db::connect();
$baseline = BaselineOperationnelle::standard();
$baseline->reconstruire($pdo);
$ctr04 = new Ctr04($pdo, $baseline);

$cas = [
    ['2026-07-26', 'EN CONCEPTION', 'avant le changement d’état'],
    ['2026-07-27', 'CONÇUE',        "le jour de l'état suivant"],
    ['2026-08-01', 'CONÇUE',        'après le changement d’état'],
];

$echecs = 0;
echo "GARDE — RECONSTRUCTION TEMPORELLE DE CAP-CORE-007\n\n";
foreach ($cas as [$date, $attendu, $libelle]) {
    // L'état de conception d'une capacité se résout par `resoudreCapacite` et
    // non par `resoudreNorme` : une capacité n'est pas une norme.
    $r = $ctr04->resoudreCapacite('CAP-CORE-007', 'conception', $date);
    $obtenu = $r['valeur'] ?? '(aucun)';
    $ok = $obtenu === $attendu;
    printf("  %s  au %s (%s) : attendu %-14s obtenu %-14s\n",
        $ok ? '[OK]  ' : '[ÉCHEC]', $date, $libelle, $attendu, $obtenu);
    if (!$ok) {
        $echecs++;
    }
}

// Une date antérieure à tout état connu ne doit pas inventer un état.
$avant = $ctr04->resoudreCapacite('CAP-CORE-007', 'conception', '2020-01-01');
$ok = $avant === null;
printf(
    "  %s  aucun état n'est inventé avant le premier état connu\n",
    $ok ? '[OK]  ' : '[ÉCHEC]',
);
if (!$ok) {
    $echecs++;
}

// Une capacité inconnue rend null : le service déclare son ignorance.
$ok = $ctr04->resoudreCapacite('CAP-CORE-999', 'conception') === null;
printf("  %s  une capacité inconnue rend null, sans valeur approchante\n", $ok ? '[OK]  ' : '[ÉCHEC]');
if (!$ok) {
    $echecs++;
}

// Diagnostic opérationnel : la baseline est intègre et l'index concorde.
$diagnostic = $ctr04->diagnostiquerIndex();
$ok = $diagnostic['coherent'] === true && $diagnostic['baseline']['concorde'] === true;
printf(
    "  %s  diagnostic de l'index : %s, %d divergence(s)\n",
    $ok ? '[OK]  ' : '[ÉCHEC]',
    $diagnostic['baseline']['concorde'] ? 'baseline intègre' : 'baseline altérée',
    count($diagnostic['divergences']),
);
if (!$ok) {
    $echecs++;
    foreach ($diagnostic['divergences'] as $divergence) {
        echo "          {$divergence}\n";
    }
}

// Contre-épreuve : un index amputé DOIT être signalé par le diagnostic.
$pdo->exec("DELETE FROM etat_capacite WHERE capacite_reference = 'CAP-CORE-007'");
$apres = $ctr04->diagnostiquerIndex();
$ok = $apres['coherent'] === false && $apres['divergences'] !== [];
printf(
    "  %s  un index amputé est signalé, non présumé conforme\n",
    $ok ? '[OK]  ' : '[ÉCHEC]',
);
if (!$ok) {
    $echecs++;
}

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-007 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-007 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
