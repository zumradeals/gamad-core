<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-019 — Sauvegarde et restauration souveraines (CTR-18).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 * Capacité de criticité RACINE.
 *
 * Ce que le test vérifie :
 *   · INV-60 — une redondance de fait n'est pas une sauvegarde éprouvée :
 *              la Loi 44 exige vérification d'intégrité et tests périodiques
 *              de restauration, et aucun n'existe ;
 *   · INV-61 — le service ne franchit pas l'exclusion de mission de
 *              l'Article 4 : l'inventaire technique demeure NON INVENTORIÉ,
 *              réservé à l'autorité ;
 *   · Article 54 — objectifs de reprise, mode dégradé, plan de succession et
 *     stratégie de sortie fournisseur demeurent non établis.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où la redondance de fait a été délibérément requalifiée en plan de
 * sauvegarde testé, ce test DOIT échouer. Falsification sur COPIE HORS DÉPÔT,
 * via CORPUS_PATH.
 */

use Gamad\RegistreContinuite\Ctr18;

require __DIR__ . '/../src/Ctr18.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr18 = new Ctr18($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — SAUVEGARDE ET RESTAURATION SOUVERAINES (CAP-CORE-019 / CTR-18)\n\n";

$ecarts = $ctr18->ecarts();

/* ------------------------------------------------------------------ INV-60 */
echo "  INV-60 — une redondance de fait n'est pas une sauvegarde éprouvée\n";

$verifier(Ctr18::CAPACITE === 'CAP-CORE-019', "le module déclare la capacité qu'il sert", Ctr18::CAPACITE);

$redondance = $ctr18->redondanceDeFait();
$verifier(
    $redondance['constatee'] === true,
    "la redondance de fait est constatée : origin et un clone local",
    substr((string) $redondance['constat'], 0, 100) . '…',
);

$verifier(
    $redondance['plan_teste'] === false && $ecarts['plan_de_sauvegarde_teste'] === false,
    "elle n'est jamais qualifiée de plan de sauvegarde testé (Loi 44)",
    (string) $redondance['qualification'],
);

$verifier(
    $ecarts['tests_de_restauration'] === 0,
    "aucun test de restauration n'est inscrit, et aucun n'est présumé",
    $ecarts['tests_de_restauration'] . ' test(s)',
);

// L'Article 54 attend « au moins un exercice de restauration pour les preuves
// et sources racines » parmi ses preuves G0. Cette attente n'est pas
// satisfaite, et la redondance de fait ne la satisfait pas à sa place.
$verifier(
    $redondance['constatee'] === true && $ecarts['tests_de_restauration'] === 0,
    "la preuve G0 attendue par l'Article 54 n'est pas déclarée satisfaite",
);

/* ------------------------------------------------------------------ INV-61 */
echo "\n  INV-61 — le service ne franchit pas l'exclusion de mission\n";

$exclusion = $ctr18->exclusionDeMission();
$verifier(
    $exclusion['declaree'] === true,
    "l'exclusion de mission de l'Article 4 est relevée du corpus, non supposée",
    substr((string) $exclusion['motif'], 0, 110) . '…',
);

$verifier(
    $ecarts['inventaire_technique'] === "NON INVENTORIÉ — réservé à l'autorité",
    "l'inventaire technique demeure non inventorié, et le service le déclare",
    (string) $ecarts['inventaire_technique'],
);

$verifier(
    str_contains((string) $exclusion['source'], 'ADOPTION-0025'),
    "la frontière invoquée est celle des accès réservés, nommée avec sa source",
    (string) $exclusion['source'],
);

/* ---------------------------------------------------- champs non établis */
echo "\n  Article 54 — l'écart global de continuité n'est pas comblé\n";

foreach (Ctr18::CHAMPS_DECLARABLES as $champ) {
    $verifier(
        in_array($champ, $ecarts['champs_non_etablis'], true),
        "le champ « {$champ} » demeure non établi",
    );
}

$verifier(
    str_contains((string) $ecarts['ecart_global_continuite'], 'Article 74'),
    "l'écart global de continuité de l'Article 74 est nommé, non comblé",
);

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — l'état de la continuité est consultable sans lancer un test\n";

$entree = dirname(__DIR__) . '/public/index.php';
$verifier(is_file($entree), "le point d'entrée existe", $entree);

$rendu = '';
$erreur = null;
if (is_file($entree)) {
    ob_start();
    try {
        include $entree;
    } catch (\Throwable $e) {
        $erreur = $e->getMessage();
    }
    $rendu = (string) ob_get_clean();
}

$verifier($erreur === null && $rendu !== '', "la page se rend sans erreur", $erreur ?? strlen($rendu) . ' octets');
$verifier(str_contains($rendu, 'NON INVENTORIÉ'), "la page déclare ce que le service n'inventorie pas");
$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-019 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
