<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-017 — Registre des risques et exceptions (CTR-11).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 * CTR-11 sert deux capacités ; chacune a sa garde.
 *
 * Ce que le test vérifie :
 *   · INV-57 — une acceptation sans échéance ferme est nommée telle, et le
 *              temps ne clôt rien ;
 *   · INV-58 — un niveau proposé par un agent artificiel n'est pas un niveau
 *              arbitré : la Loi 65 réserve l'acceptation à l'autorité ;
 *   · Article 52 — les contrôles requis absents sont déclarés, non présumés.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où un risque non arbitré a été délibérément doté d'un arbitrage, ou une
 * exception dotée d'un terme fixe, ce test DOIT échouer. Falsification sur
 * COPIE HORS DÉPÔT, via CORPUS_PATH.
 */

use Gamad\RegistreRisques\Ctr11;

require __DIR__ . '/../src/Ctr11.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr11 = new Ctr11($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — REGISTRE DES RISQUES ET EXCEPTIONS (CAP-CORE-017 / CTR-11)\n\n";

$ecarts = $ctr11->ecarts();

echo "  Dérivation\n";

$verifier(Ctr11::CAPACITE === 'CAP-CORE-017', "le module déclare la capacité qu'il sert — CTR-11 en sert deux", Ctr11::CAPACITE);
$verifier($ecarts['risques'] === 3, "les trois risques inscrits sont dérivés du tableau de l'Article 5", $ecarts['risques'] . ' risque(s)');
$verifier($ecarts['exceptions'] === 1, "l'exception inscrite est dérivée du Registre des exceptions", $ecarts['exceptions'] . ' exception(s)');
$verifier($ctr11->resoudreRisque('RISK-SEC-9999') === null, "un risque que le corpus ne porte pas n'est pas inventé");

/* ------------------------------------------------------------------ INV-58 */
echo "\n  INV-58 — un niveau proposé n'est pas un niveau arbitré\n";

$verifier(
    $ecarts['non_arbitres'] === ['RISK-SEC-0002', 'RISK-SEC-0003'],
    "les risques non arbitrés sont nommés : la Loi 65 réserve l'acceptation à l'autorité",
    implode(' · ', $ecarts['non_arbitres']),
);

$audit = $ctr11->resoudreRisque('RISK-SEC-0001');
$verifier(
    $audit !== null && $audit['arbitre_par'] === 'ADOPTION-0022' && $audit['niveau_arbitre'] === 'S3',
    "RISK-SEC-0001 est arbitré, et l'acte qui l'arbitre est nommé",
    $audit === null ? 'risque absent' : (string) $audit['arbitre_par'] . ' → ' . (string) $audit['niveau_arbitre'],
);

$crlf = $ctr11->resoudreRisque('RISK-SEC-0002');
$verifier(
    $crlf !== null && $crlf['niveau_propose'] === 'S1' && $crlf['niveau_arbitre'] === null,
    "un niveau proposé demeure proposé : il n'est jamais promu en arbitré",
    $crlf === null ? 'risque absent' : 'proposé ' . (string) $crlf['niveau_propose'] . ' · arbitré ' . var_export($crlf['niveau_arbitre'], true),
);

/* ------------------------------------------------------------------ INV-57 */
echo "\n  INV-57 — une acceptation sans échéance ferme est nommée telle\n";

$sansTerme = $ecarts['sans_echeance_ferme'];
$verifier(
    count($sansTerme) === 2,
    "l'acceptation et l'exception sans terme fixe sont l'une et l'autre relevées",
    implode(' · ', array_map(
        static fn (array $s) => (string) $s['reference'] . ' (' . (string) $s['espece'] . ')',
        $sansTerme,
    )),
);

$references = array_column($sansTerme, 'reference');
foreach (['RISK-SEC-0001', 'EXC-SEC-0001'] as $attendu) {
    $verifier(
        in_array($attendu, $references, true),
        "{$attendu} est nommé sans échéance ferme, et aucun terme ne lui est fixé",
    );
}

$verifier(
    $ecarts['exceptions_ouvertes'] === ['EXC-SEC-0001'],
    "l'exception demeure ouverte : aucun rétablissement n'est constaté",
    implode(' · ', $ecarts['exceptions_ouvertes']),
);

$verifier(
    $ecarts['sans_compensation_technique'] === ['EXC-SEC-0001'],
    "l'absence de compensation technique est relevée, non suppléée par la transparence documentaire",
    implode(' · ', $ecarts['sans_compensation_technique']),
);

/* ---------------------------------------------------- champs non établis */
echo "\n  Article 52 — les décisions ouvertes ne sont pas comblées\n";

foreach (['methode_evaluation', 'seuils', 'frequence_revue'] as $champ) {
    $verifier(
        $ecarts[$champ] === Ctr11::NON_ETABLI,
        "le champ « {$champ} » demeure non établi",
        (string) $ecarts[$champ],
    );
}

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — le registre est consultable sans lancer un test\n";

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
preg_match_all('/RISK-SEC-\d{4}/', $rendu, $mv);
$verifier(count(array_unique($mv[0])) === 3, "les trois risques figurent sur la page rendue", count(array_unique($mv[0])) . ' risque(s)');
$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-017 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
