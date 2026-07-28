<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-001 — reconstruction temporelle d'une identité.
 *
 * Garde de comportement propre à cette capacité (doctrine d'ADOPTION-0035,
 * Art. 2.2) : elle éprouve CTR-01 et n'hérite rien des gardes de CTR-04 ni
 * de CTR-02.
 *
 * Cas d'essai fondé sur un fait déjà vrai : PRD-GAMAD-001 — GAMAD ID — était
 * `HISTORIQUE À QUALIFIER` au 26 juillet 2026, et `DISSOUS — IDENTITÉ RENDUE
 * AU CORE` à compter du 27 (ADOPTION-0023). Une identité dissoute demeure
 * consultable (INV-21).
 *
 * CONTRE-ÉPREUVE (ADOPTION-0032, Art. 3) : exécuté contre un corpus dont la
 * date d'ADOPTION-0023 a été déplacée, ce test DOIT échouer.
 *
 * Exécution : php core/registre-identites/tests/identite_p3.php
 * Code de sortie : 0 si la preuve passe, 1 sinon.
 */

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../src/Ctr01.php';

$fichier = sys_get_temp_dir() . '/regn-identite-p3-' . getmypid() . '.sqlite';
@unlink($fichier);
putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $fichier);

$pdo = Db::connect();
(new Ingestion($pdo, REGN_CORPUS))->executer();
$ctr01 = new Ctr01($pdo);

$echecs = 0;
echo "PREUVE P3 — RECONSTRUCTION TEMPORELLE D'UNE IDENTITÉ (CAP-CORE-001)\n\n";

// --- Reconstruction temporelle de l'identité dissoute.
$cas = [
    ['2026-07-26', 'HISTORIQUE À QUALIFIER',            'la veille de sa dissolution'],
    ['2026-07-27', 'DISSOUS — IDENTITÉ RENDUE AU CORE', 'le jour de ADOPTION-0023'],
    ['2026-08-01', 'DISSOUS — IDENTITÉ RENDUE AU CORE', 'après dissolution'],
];

foreach ($cas as [$date, $attendu, $libelle]) {
    $r = $ctr01->resoudreIdentite('PRD-GAMAD-001', $date);
    $obtenu = $r['etat'] ?? '(aucun)';
    $ok = $obtenu === $attendu;
    printf(
        "  %s  PRD-GAMAD-001 au %s (%s)\n           attendu %-34s obtenu %s\n",
        $ok ? '[OK]  ' : '[ÉCHEC]',
        $date,
        $libelle,
        $attendu,
        $obtenu,
    );
    if (!$ok) {
        $echecs++;
    }
}

// --- Une identité dissoute demeure consultable (INV-21).
$r = $ctr01->resoudreIdentite('PRD-GAMAD-001');
if (($r['reference'] ?? null) !== 'PRD-GAMAD-001') {
    echo "  [ÉCHEC] une identité dissoute doit demeurer consultable (INV-21)\n";
    $echecs++;
} else {
    echo "  [OK]    l'identité dissoute demeure consultable\n";
}

// --- Les divergences de dénomination sont signalées, non tranchées (M-15).
$divergentes = array_filter($ctr01->resoudreDenominations(), fn ($d) => $d['divergente']);
if ($divergentes === []) {
    echo "  [ÉCHEC] aucune divergence de dénomination signalée, alors que le corpus en porte\n";
    $echecs++;
} else {
    foreach ($divergentes as $d) {
        printf("  [OK]    divergence signalée : %s porte %d dénominations (%s)\n",
            $d['reference'], count($d['libelles']), implode(' / ', $d['libelles']));
    }
}

// --- L'inventaire restitue les entités que le corpus déclare, et elles seules.
$inventaire = $ctr01->resoudreInventaire();
if (count($inventaire) < 7) {
    printf("  [ÉCHEC] inventaire incomplet : %d entités, au moins 7 attendues\n", count($inventaire));
    $echecs++;
} else {
    printf("  [OK]    inventaire : %d entités connues du Core\n", count($inventaire));
}

@unlink($fichier);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-001 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
