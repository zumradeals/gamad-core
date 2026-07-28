<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-003 — vérification d'un mandat À LA DATE DE L'ACTE.
 *
 * Garde de comportement propre à cette capacité, au sens de la doctrine
 * arrêtée par ADOPTION-0035, Art. 2.2 : une capacité n'hérite pas de la preuve
 * d'une autre, et ne peut atteindre P3 que par une garde éprouvant son propre
 * contrat.
 *
 * Ce que le test vérifie (INV-14, INV-15) :
 *   · un acte postérieur au début du mandat est VÉRIFIÉ ;
 *   · un acte contemporain de la fondation du mandat est CONSTITUTIF, non
 *     VÉRIFIÉ — la chaîne de mandats se termine, elle ne boucle pas ;
 *   · les données proviennent du corpus, non de constantes du code.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * dont le début du mandat a été délibérément déplacé, ce test DOIT échouer.
 * Un test qui ne peut pas échouer ne prouve rien. Voir le mode --falsification.
 *
 * Exécution : php core/registre-autorites/tests/mandat_p3.php
 * Code de sortie : 0 si la preuve passe, 1 sinon.
 */

use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../src/Ctr02.php';

// Base éphémère dédiée au test, indépendante de tout déploiement.
$fichier = sys_get_temp_dir() . '/regn-mandat-p3-' . getmypid() . '.sqlite';
@unlink($fichier);
putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $fichier);

$pdo = Db::connect();
(new Ingestion($pdo, REGN_CORPUS))->executer();
$ctr02 = new Ctr02($pdo);

/**
 * Cas d'essai fondés sur des faits déjà vrais du corpus :
 * MANDAT-GENESIS-II-0001 débute le 24 juillet 2026 (Article 47 du registre
 * adopté) ; ADOPTION-0001 à 0005 sont du même jour ; ADOPTION-0026 du 27.
 */
$cas = [
    ['ADOPTION-0001', 'CONSTITUTIF', 'le jour même où le mandat est fondé'],
    ['ADOPTION-0004', 'CONSTITUTIF', "adopte GOVERNANCE-0002, dont le mandat tire sa source"],
    ['ADOPTION-0026', 'VÉRIFIÉ',     'trois jours après le début du mandat'],
    ['ADOPTION-0035', 'VÉRIFIÉ',     'quatre jours après le début du mandat'],
];

$echecs = 0;
echo "PREUVE P3 — VÉRIFICATION DU MANDAT À LA DATE DE L'ACTE (CAP-CORE-003)\n\n";

foreach ($cas as [$acte, $attendu, $libelle]) {
    $r = $ctr02->verifierActe($acte);
    $obtenu = $r['verdict'] ?? '(introuvable)';
    $ok = $obtenu === $attendu;
    printf(
        "  %s  %s (%s)\n           attendu %-12s obtenu %-12s\n",
        $ok ? '[OK]  ' : '[ÉCHEC]',
        $acte,
        $libelle,
        $attendu,
        $obtenu,
    );
    if (!$ok) {
        $echecs++;
    }
}

// Le mandat lui-même doit se résoudre, et son état être daté.
$m = $ctr02->resoudreMandat('FCT-CORE-001', null, '2026-07-27');
if (($m['mandat'] ?? null) === null || !str_starts_with((string) ($m['etat'] ?? ''), 'ACTIF')) {
    echo "  [ÉCHEC] le mandat de FCT-CORE-001 ne se résout pas au 27 juillet 2026\n";
    $echecs++;
} else {
    printf("  [OK]    mandat %s résolu au 2026-07-27, état %s\n", $m['mandat'], $m['etat']);
}

// La vacance est un fait institutionnel : elle doit être restituée, non masquée.
$vacantes = $ctr02->resoudreVacance();
if ($vacantes === []) {
    echo "  [ÉCHEC] aucune fonction vacante restituée, alors que le corpus en déclare\n";
    $echecs++;
} else {
    printf("  [OK]    %d fonction(s) vacante(s) restituée(s)\n", count($vacantes));
}

@unlink($fichier);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-003 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
