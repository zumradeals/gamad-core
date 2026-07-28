<?php

declare(strict_types=1);

/**
 * Preuve P3 — reconstruction temporelle (conception d'implémentation, Titre V).
 *
 * Vérifie qu'à une date passée donnée, le service restitue le statut RÉELLEMENT
 * en vigueur à cette date. Cas d'essai fondé sur un fait déjà vrai : l'état de
 * conception de CAP-CORE-007, EN CONCEPTION jusqu'à ADOPTION-0026, CONÇUE
 * ensuite. Ce test est la preuve P3 aujourd'hui manquante de la capacité.
 *
 * Exécution : php core/registre-normes/tests/temporel_p3.php
 * Code de sortie : 0 si la preuve passe, 1 sinon.
 */

use Gamad\RegistreNormes\Ctr04;
use Gamad\RegistreNormes\Ingestion;
use Gamad\RegistreNormes\Db;

require __DIR__ . '/../bootstrap.php';

// Base éphémère dédiée au test, indépendante de tout déploiement.
$fichier = sys_get_temp_dir() . '/regn-p3-' . getmypid() . '.sqlite';
@unlink($fichier);
putenv('DATABASE_URL='); // force SQLite
putenv('SQLITE_PATH=' . $fichier);

$pdo = Db::connect();
(new Ingestion($pdo, REGN_CORPUS))->executer();
$ctr04 = new Ctr04($pdo, REGN_CORPUS);

$cas = [
    ['2026-07-26', 'EN CONCEPTION', 'la veille de son adoption'],
    ['2026-07-27', 'CONÇUE',        'le jour de ADOPTION-0026'],
    ['2026-08-01', 'CONÇUE',        'après adoption'],
];

$echecs = 0;
echo "PREUVE P3 — RECONSTRUCTION TEMPORELLE DE CAP-CORE-007\n\n";
foreach ($cas as [$date, $attendu, $libelle]) {
    $r = $ctr04->resoudreNorme('CAP-CORE-007', null, $date);
    $obtenu = $r['statut'] ?? '(aucun)';
    $ok = $obtenu === $attendu;
    printf("  %s  au %s (%s) : attendu %-14s obtenu %-14s\n",
        $ok ? '[OK]  ' : '[ÉCHEC]', $date, $libelle, $attendu, $obtenu);
    if (!$ok) {
        $echecs++;
    }
}

@unlink($fichier);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-007 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
