<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-014 — Journal d'événements communs (CTR-07).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 *
 * Ce que le test vérifie :
 *   · INV-65 — la famille `CTR-07` est adoptée ET le journal n'est pas
 *              établi : l'une n'emporte pas l'autre ;
 *   · les TROIS espèces d'absence sont distinguées, et celle du journal est
 *     l'absence NON DÉCLARÉE — ni motivée, ni exclue, simplement absente ;
 *   · le service n'invente aucun type d'événement ni aucune convention ;
 *   · Article 48 — types, version, livraison, ordre et conservation demeurent
 *     non établis, et les données exclues sont restituées.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où un registre d'événements a été délibérément fabriqué, ce test DOIT
 * échouer. Falsification sur COPIE HORS DÉPÔT, via CORPUS_PATH.
 */

use Gamad\RegistreEvenements\Ctr07;

require __DIR__ . '/../src/Ctr07.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr07 = new Ctr07($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — JOURNAL D'ÉVÉNEMENTS COMMUNS (CAP-CORE-014 / CTR-07)\n\n";

$ecarts = $ctr07->ecarts();

/* ------------------------------------------------------- moitié établie */
echo "  INV-65 — ce que le corpus A adopté : la famille CTR-07\n";

$verifier(Ctr07::CAPACITE === 'CAP-CORE-014', "le module déclare la capacité qu'il sert", Ctr07::CAPACITE);

$famille = $ctr07->familleRattachee();

$verifier(
    $famille['adoptee'] === true && $famille['famille'] === 'CTR-07',
    "le rattachement de CTR-07 est dérivé du Titre XXX, non supposé",
    (string) $famille['famille'] . ' — ' . (string) $famille['objet'],
);

$verifier(
    $famille['gardien'] === 'DOM-06',
    "le domaine gardien de la famille est relevé de l'Atlas",
    (string) $famille['gardien'],
);

/* ----------------------------------------------------- moitié non établie */
echo "\n  INV-65 — ce que le corpus n'a PAS établi : le journal lui-même\n";

$journal = $ctr07->journal();

// Le cœur de INV-65, et le point que la contre-épreuve retourne : l'existence
// d'une famille de contrat n'établit ni les types, ni le mécanisme, ni la
// conservation.
$verifier(
    $journal['existe'] === false && $ecarts['journal_etabli'] === false,
    "aucun registre d'événements n'existe dans le corpus, et aucun n'est présumé",
);

$verifier(
    $journal['declaration_motivee'] === null && $ecarts['declaration_motivee'] === false,
    "aucune déclaration motivée de cette absence n'existe non plus",
);

$verifier(
    $ecarts['types_etablis'] === 0,
    "aucun type d'événement n'est établi, et aucun n'est inventé",
    $ecarts['types_etablis'] . ' type(s)',
);

$verifier(
    $ecarts['famille_adoptee'] === true && $ecarts['journal_etabli'] === false,
    "une famille adoptée et un journal non établi coexistent, sans que l'une emporte l'autre",
);

/* --------------------------------------------- les trois espèces d'absence */
echo "\n  Les trois espèces d'absence, distinguées et non confondues\n";

$especes = $ctr07->especesDAbsence();

$verifier(
    count($especes) === 3 && $ecarts['especes_distinguees'] === 3,
    "les trois espèces sont dérivées de leurs textes, non énoncées de mémoire",
    count($especes) . ' espèce(s)',
);

$attendues = [
    'CAP-CORE-018' => Ctr07::ABSENCE_MOTIVEE,
    'CAP-CORE-019' => Ctr07::ABSENCE_EXCLUSION,
    'CAP-CORE-014' => Ctr07::ABSENCE_NON_DECLAREE,
];

foreach ($especes as $espece) {
    $capacite = (string) $espece['capacite'];
    $verifier(
        ($attendues[$capacite] ?? null) === $espece['espece'],
        "« {$espece['objet']} » : " . (string) $espece['espece'],
        $capacite,
    );
}

// La troisième espèce est la plus dangereuse : elle ne se distingue d'un oubli
// par aucun signe. Rien n'est écrit, donc rien ne peut être vérifié.
$verifier(
    $ecarts['espece_d_absence'] === Ctr07::ABSENCE_NON_DECLAREE,
    "celle du journal est l'absence NON DÉCLARÉE — ni motivée, ni exclue",
    (string) $ecarts['espece_d_absence'],
);

/* -------------------------------------------- données minimales et exclues */
echo "\n  Article 48 — les données exclues valent autant que les données minimales\n";

$donnees = $ctr07->donnees();

$verifier(
    $ecarts['donnees_minimales'] > 0,
    "les données minimales sont relevées de la fiche",
    implode(', ', $donnees['minimales']),
);

$verifier(
    $ecarts['donnees_exclues'] > 0,
    "les données EXCLUES sont relevées : ce qu'un journal ne doit jamais porter",
    implode(' · ', $donnees['exclues']),
);

$verifier(
    in_array('secrets', $donnees['exclues'], true),
    "les secrets figurent parmi les données que le journal ne portera jamais",
);

/* ---------------------------------------------------- champs non établis */
echo "\n  Article 48 — les décisions ouvertes ne sont pas comblées\n";

foreach (Ctr07::CHAMPS_DECLARABLES as $champ) {
    $verifier(
        in_array($champ, $ecarts['champs_non_etablis'], true),
        "le champ « {$champ} » demeure non établi",
    );
}

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — l'état du journal est consultable sans lancer un test\n";

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
$verifier(str_contains($rendu, 'ABSENCE NON DÉCLARÉE'), "la page nomme l'espèce d'absence qu'elle constate");
$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-014 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
