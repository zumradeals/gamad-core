<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-013 — Audit commun (CTR-10, volet audit).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 * `CTR-10` est partagée avec `CAP-CORE-015` ; cette garde n'éprouve que le
 * volet audit, et `preuves_p3.php` demeure la garde du volet intégrité.
 *
 * Ce que le test vérifie :
 *   · INV-62 — les deux réserves de `G0` dont la levée porte sa propre
 *              restriction sont restituées AVEC cette restriction ;
 *   · la non-indépendance de la fonction `AUDIT` est nommée, non atténuée ;
 *   · les trois formes de trace d'adoption sont constatées, non uniformisées ;
 *   · Article 49 — événements auditables, conservation, délais, accès et
 *     indépendance de la fonction demeurent non établis.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où la restriction « non par résolution technique complète » a été
 * délibérément effacée, ce test DOIT échouer. Falsification sur COPIE HORS
 * DÉPÔT, via CORPUS_PATH.
 */

use Gamad\RegistreAudit\Ctr10;

require __DIR__ . '/../src/Ctr10.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$audit = new Ctr10($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — AUDIT COMMUN (CAP-CORE-013 / CTR-10, volet audit)\n\n";

$ecarts = $audit->ecarts();

/* ------------------------------------------------------------------ INV-62 */
echo "  INV-62 — une réserve levée par décision n'est pas une réserve résolue\n";

$verifier(Ctr10::CAPACITE === 'CAP-CORE-013', "le module déclare la capacité qu'il sert", Ctr10::CAPACITE);

$verifier(
    $ecarts['reserves'] === 5,
    "les cinq réserves du Titre V sont dérivées du corpus, non comptées de mémoire",
    $ecarts['reserves'] . ' réserve(s)',
);

$verifier(
    $ecarts['reserves_levees'] === 5,
    "les cinq sont déclarées levées, et le service ne le conteste pas",
    $ecarts['reserves_levees'] . ' levée(s)',
);

// Le cœur de INV-62, et le point que la contre-épreuve retourne : deux levées
// écrivent elles-mêmes ce qu'elles ne valent pas. Un constat global —
// « les cinq écarts sont tous levés » — les rendrait invisibles.
$restreintes = $audit->reservesLeveesSousRestriction();

$verifier(
    $ecarts['reserves_restreintes'] === 2 && count($restreintes) === 2,
    "DEUX de ces levées portent leur propre restriction, et elle est restituée",
    $ecarts['reserves_restreintes'] . ' restreinte(s)',
);

foreach ($restreintes as $reserve) {
    $verifier(
        is_string($reserve['restriction']) && $reserve['restriction'] !== '',
        "la restriction de « {$reserve['objet']} » est relevée du corpus",
        (string) $reserve['restriction'],
    );
}

$verifier(
    str_contains((string) $ecarts['portee'], 'ne prononce'),
    "le service ne prononce, ne requalifie et ne juge aucune levée",
);

/* ------------------------------------------- indépendance de la fonction */
echo "\n  La fonction AUDIT est tenue par l'autorité qu'elle devrait auditer\n";

$independance = $audit->independanceDeLAudit();

$verifier(
    $independance['independante'] === false && $ecarts['audit_independant'] === false,
    "l'audit n'est jamais déclaré indépendant",
);

$verifier(
    in_array('AUDIT', $independance['fonctions_transitoires'], true),
    "la fonction AUDIT est nommée parmi les fonctions attribuées à titre transitoire",
    implode(', ', $independance['fonctions_transitoires']),
);

$verifier(
    str_contains((string) $independance['risque_associe'], 'RISK-SEC-0001'),
    "le risque associé est nommé avec sa référence",
    (string) $independance['risque_associe'],
);

$verifier(
    str_contains((string) $independance['portee'], 'ne l\'atténue pas'),
    "le service nomme la non-indépendance sans l'atténuer",
);

/* --------------------------------------------------- trace d'adoption */
echo "\n  CTR-10 — qui a fait quoi, sous quelle autorité, à quelle date\n";

$trace = $audit->traceDAdoption('ADOPTION-0055');
$verifier(
    $trace['reconstituable'] === true,
    "la trace d'un acte se reconstitue : autorité, date et forme",
    (string) $trace['autorite'] . ' · ' . (string) $trace['date'],
);

$formes = $audit->formesDeTrace();

// L'Article 49 range « impossibilité de reconstruire une action » parmi les
// risques de cette capacité. Le corpus enregistre sa propre trace sous trois
// formes jamais unifiées : le service le constate, il ne le corrige pas.
$verifier(
    $ecarts['formes_de_trace'] === 3,
    "les TROIS formes de trace du corpus sont constatées, non confondues",
    implode(' · ', array_map(
        static fn (string $f, int $n): string => $f . ' (' . $n . ')',
        array_keys($formes['formes']),
        array_values($formes['formes']),
    )),
);

$verifier(
    $ecarts['traces_incompletes'] > 0,
    "les actes dont la trace ne se reconstitue pas sont nommés, non passés sous silence",
    $ecarts['traces_incompletes'] . ' acte(s) : ' . implode(', ', $formes['incompletes']),
);

$verifier(
    str_contains((string) $formes['portee'], 'ne réécrit aucun acte'),
    "le service ne réécrit aucun acte pour uniformiser la trace (INV-43)",
);

$inexistant = $audit->traceDAdoption('ADOPTION-9999');
$verifier(
    $inexistant['reconstituable'] === false && $inexistant['autorite'] === null,
    "un acte inexistant ne produit aucune trace inventée",
);

/* ---------------------------------------------------- champs non établis */
echo "\n  Article 49 — les décisions ouvertes ne sont pas comblées\n";

foreach (Ctr10::CHAMPS_DECLARABLES as $champ) {
    $verifier(
        in_array($champ, $ecarts['champs_non_etablis'], true),
        "le champ « {$champ} » demeure non établi",
    );
}

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — l'état de l'audit est consultable sans lancer un test\n";

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
$verifier(str_contains($rendu, 'RISK-SEC-0001'), "la page nomme le risque sous lequel elle est écrite");
$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-013 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
