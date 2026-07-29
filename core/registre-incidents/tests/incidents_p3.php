<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-018 — Registre des incidents (CTR-11).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 * CTR-11 sert deux capacités ; chacune a sa garde. C'est ici que la doctrine
 * se vérifie une seconde fois : les risques sont inscrits, les incidents ne
 * le sont pas, et une preuve commune aurait masqué la seconde moitié du fait.
 *
 * Ce que le test vérifie :
 *   · INV-59 — une déclaration motivée d'absence est distinguée d'une absence
 *              d'inventaire : le registre existe, il est ouvert, il est vide,
 *              et il DIT pourquoi ;
 *   · un fait écarté de la qualification d'incident l'est AVEC son motif ;
 *   · Article 53 — classification, délais, autorités de crise et politique de
 *     communication demeurent non établis.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * dont la déclaration motivée d'absence a été retirée, ce test DOIT échouer —
 * le registre redevenant un registre vide et muet. Falsification sur COPIE
 * HORS DÉPÔT, via CORPUS_PATH.
 */

use Gamad\RegistreIncidents\Ctr11;

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

echo "PREUVE P3 — REGISTRE DES INCIDENTS (CAP-CORE-018 / CTR-11)\n\n";

$ecarts = $ctr11->ecarts();

/* ------------------------------------------------------------------ INV-59 */
echo "  INV-59 — une déclaration motivée d'absence n'est pas une absence d'inventaire\n";

$verifier(Ctr11::CAPACITE === 'CAP-CORE-018', "le module déclare la capacité qu'il sert — CTR-11 en sert deux", Ctr11::CAPACITE);

$verifier(
    $ecarts['registre_present'] === true,
    "le Registre initial des incidents existe, à la différence de celui des realms",
);

$verifier(
    $ecarts['absence_declaree'] === true,
    "l'absence d'incident est DÉCLARÉE et motivée, non seulement constatée",
    (string) ($ctr11->declarationAbsence()['motif'] ?? '(aucun motif)'),
);

$verifier(
    $ecarts['incidents'] === 0,
    "aucun incident n'est inscrit, et le service n'en invente aucun",
    $ecarts['incidents'] . ' incident(s)',
);

// L'Article 53 admettait « registre initial des incidents connus OU
// déclaration motivée d'absence ». La seconde branche est satisfaite ; c'est
// ce qui distingue cette absence de celle des realms.
$verifier(
    $ecarts['registre_present'] && $ecarts['absence_declaree'],
    "la preuve G0 attendue par l'Article 53 est satisfaite par sa seconde branche",
);

/* ------------------------------------------------ non-classifications */
echo "\n  Un fait écarté l'est avec son motif — « incident caché » est le premier risque\n";

$exclus = $ctr11->nonClassifications();
$verifier(
    $exclus !== [],
    "les faits écartés de la qualification d'incident sont relevés",
    count($exclus) . ' non-classification(s)',
);

foreach ($exclus as $x) {
    $verifier(
        trim((string) $x['motif']) !== '',
        "l'exclusion « " . substr((string) $x['objet'], 0, 40) . " » porte son motif",
        substr((string) $x['motif'], 0, 90) . '…',
    );
}

/* ---------------------------------------------------- champs non établis */
echo "\n  Article 53 — les décisions ouvertes ne sont pas comblées\n";

foreach (Ctr11::CHAMPS_DECLARABLES as $champ) {
    $verifier(
        in_array($champ, $ecarts['champs_non_etablis'], true),
        "le champ « {$champ} » demeure non établi",
    );
}

foreach (['exercice_scenario', 'canal_signalement'] as $champ) {
    $verifier(
        $ecarts[$champ] === Ctr11::NON_ETABLI,
        "« {$champ} », attendu parmi les preuves G0, est déclaré non établi",
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
$verifier(str_contains($rendu, 'déclarée'), "la page distingue l'absence déclarée de l'absence d'inventaire");
$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-018 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
