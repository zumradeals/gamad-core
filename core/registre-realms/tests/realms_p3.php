<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-012 — Registre des realms (CTR-08, part realms).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 * La famille CTR-08 sert deux capacités ; chacune a sa garde, et aucune
 * n'hérite de la preuve de l'autre. C'est ici que la doctrine se vérifie :
 * les produits sont inventoriés, les realms ne le sont pas, et une preuve
 * commune aurait masqué la seconde moitié de ce fait.
 *
 * Ce que le test vérifie :
 *   · INV-54 — un realm non inscrit n'est pas reconnu ; aucune confiance
 *              n'est implicite, et les partenaires externes ne sont pas
 *              traités en realms fédérés ;
 *   · INV-55 — l'absence d'une source canonique de DOM-04 est constatée et
 *              jamais suppléée par une source voisine ;
 *   · les définitions adoptées sont restituées mot pour mot, non reformulées.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où un realm a été délibérément inscrit, ce test DOIT échouer — le corpus
 * cessant de porter l'absence qu'il constate. Falsification sur COPIE HORS
 * DÉPÔT, via CORPUS_PATH.
 *
 * Exécution :             php core/registre-realms/tests/realms_p3.php
 * Contre-épreuve : CORPUS_PATH=/chemin/copie/altérée php core/registre-realms/tests/realms_p3.php
 */

use Gamad\RegistreRealms\Ctr08;

require __DIR__ . '/../src/Ctr08.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr08 = new Ctr08($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — REGISTRE DES REALMS (CAP-CORE-012 / CTR-08)\n\n";

$ecarts = $ctr08->ecarts();

/* ------------------------------------------------------------------ INV-54 */
echo "  INV-54 — un realm non inscrit n'est pas reconnu\n";

$verifier(
    Ctr08::CAPACITE === 'CAP-CORE-012',
    "le module déclare la capacité qu'il sert — CTR-08 en sert deux",
    Ctr08::CAPACITE,
);

$verifier(
    $ecarts['realms_reconnus'] === 0,
    "aucun realm n'est reconnu, et le service ne prétend pas le contraire",
    $ecarts['realms_reconnus'] . ' realm(s)',
);

$verifier(
    $ecarts['inventaire_constitue'] === false,
    "l'inventaire des realms n'est pas constitué, et le fait est dérivé du disque",
);

// Wasplex et IKOMA sont inscrits au Registre des produits comme partenaires
// externes. Les tenir pour des realms fédérés serait la « confiance
// implicite » que l'Article 47 range en tête de ses risques.
$externes = $ctr08->externesNonRealms();
$verifier(
    count($externes) === 2,
    "les partenaires externes sont relevés, et aucun n'est un realm",
    implode(' · ', array_map(
        static fn (array $x) => (string) $x['libelle'] . ' → ' . (string) $x['realm'],
        $externes,
    )),
);

foreach ($externes as $x) {
    $verifier(
        str_contains((string) $x['etat'], 'APPARTENANCE NON ENTÉRINÉE'),
        "l'appartenance de {$x['libelle']} demeure non entérinée",
        (string) $x['etat'],
    );
}

/* ------------------------------------------------------------------ INV-55 */
echo "\n  INV-55 — l'absence d'une source canonique est constatée, jamais suppléée\n";

$sources = $ctr08->sourcesCanoniques();
$verifier(
    count($sources) === 3,
    "les trois sources canoniques de DOM-04 nommées par l'Article 35 sont confrontées au disque",
    implode(' · ', array_map(
        static fn (array $s) => (string) $s['libelle'] . ' : ' . ($s['presente'] ? 'présente' : 'absente'),
        $sources,
    )),
);

$verifier(
    $sources['Registre des produits']['presente'] === true,
    "le Registre des produits existe depuis ADOPTION-0016",
);

$verifier(
    in_array('Registre des realms', $ecarts['sources_absentes'], true),
    "l'absence du Registre des realms est nommée, non comblée",
    'absentes : ' . (implode(' · ', $ecarts['sources_absentes']) ?: 'aucune'),
);

// Le service ne doit tirer aucun realm du Registre des produits : ce serait
// suppléer une source canonique par une autre.
$verifier(
    $ctr08->realms() === [],
    "aucun realm n'est tiré d'une source qui n'est pas la sienne",
);

/* ------------------------------------------------- définitions et non-établis */
echo "\n  Les définitions adoptées sont restituées, non reformulées\n";

$definitions = $ctr08->definitions();
$verifier(
    ($definitions['realm'] ?? '') !== Ctr08::NON_ETABLI
        && str_contains((string) $definitions['realm'], 'Espace gouverné'),
    "la définition d'un realm est celle de LEXICON-0001, Entrée 47",
    (string) ($definitions['realm'] ?? '(absente)'),
);

$verifier(
    str_contains((string) ($definitions['federation'] ?? ''), 'reconnaissance limitée'),
    "la définition de la fédération est celle de l'Entrée 136",
    (string) ($definitions['federation'] ?? '(absente)'),
);

foreach (['contrat_federation', 'procedure_retrait', 'niveaux_confiance'] as $champ) {
    $verifier(
        $ecarts[$champ] === Ctr08::NON_ETABLI,
        "le champ « {$champ} » demeure non établi, jamais comblé",
        (string) $ecarts[$champ],
    );
}

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — l'absence est consultable sans lancer un test\n";

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

$verifier(
    str_contains($rendu, 'Aucun realm'),
    "la page déclare l'absence plutôt que d'afficher un tableau vide",
);

$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-012 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
