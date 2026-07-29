<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-002 — Registre des organisations (CTR-17).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 *
 * Ce que le test vérifie :
 *   · INV-55 — l'absence d'une source canonique est constatée, jamais
 *              suppléée : le Registre initial des organisations existe
 *              désormais, et le fait est dérivé du disque ;
 *   · INV-56 — être nommée par un texte ne vaut pas reconnaissance : GAMAD,
 *              Wasplex et IKOMA sont nommés par des textes adoptés, et une
 *              seule de ces trois entités est une organisation inscrite ;
 *   · Article 37 — les champs que le corpus n'établit pas sont déclarés tels.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où une entité nommée mais non inscrite a été délibérément inscrite avec un
 * statut hors vocabulaire, ce test DOIT échouer. Falsification sur COPIE HORS
 * DÉPÔT, via CORPUS_PATH.
 *
 * Exécution :             php core/registre-organisations/tests/organisations_p3.php
 * Contre-épreuve : CORPUS_PATH=/chemin/copie/altérée php core/registre-organisations/tests/organisations_p3.php
 */

use Gamad\RegistreOrganisations\Ctr17;

require __DIR__ . '/../src/Ctr17.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr17 = new Ctr17($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — REGISTRE DES ORGANISATIONS (CAP-CORE-002 / CTR-17)\n\n";

$ecarts = $ctr17->ecarts();

/* ------------------------------------------------------------------ INV-55 */
echo "  INV-55 — la source canonique existe, et le fait est dérivé du disque\n";

$verifier(
    Ctr17::CAPACITE === 'CAP-CORE-002',
    "le module déclare la capacité qu'il sert",
    Ctr17::CAPACITE,
);

$verifier(
    $ecarts['registre_constitue'] === true,
    "le Registre initial des organisations est constitué",
);

/* ------------------------------------------------------------------ INV-56 */
echo "\n  INV-56 — être nommée par un texte ne vaut pas reconnaissance\n";

$verifier(
    $ecarts['organisations'] === 1,
    "une seule organisation est inscrite, et ce nombre est un constat",
    $ecarts['organisations'] . ' organisation(s)',
);

$gamad = $ctr17->resoudreOrganisation('ORG-GAMAD-001');
$verifier(
    $gamad !== null
        && $gamad['libelle'] === 'GAMAD'
        && $gamad['type'] === 'SOUVERAINE'
        && $gamad['statut'] === 'RECONNUE'
        && $gamad['source'] === 'ACTE-0001',
    "GAMAD est inscrite, souveraine, reconnue, et fondée par ACTE-0001",
    $gamad === null ? 'inscription absente' : implode(' | ', [
        (string) $gamad['type'], (string) $gamad['statut'], (string) $gamad['source'],
    ]),
);

// Wasplex et IKOMA sont nommés en toutes lettres par ADOPTION-0025, Art. 3.d
// et inscrits au Registre des produits. Aucun des deux n'est une organisation
// inscrite : la mention ne reconnaît rien.
foreach (['Wasplex', 'IKOMA'] as $nomme) {
    $trouve = false;
    foreach ($ctr17->organisations() as $o) {
        if (stripos((string) $o['libelle'], $nomme) !== false) {
            $trouve = true;
        }
    }
    $verifier(
        !$trouve,
        "{$nomme}, nommé par un texte adopté, n'est pas pour autant inscrit",
    );
}

$verifier(
    $ctr17->resoudreOrganisation('ORG-GAMAD-999') === null,
    "une organisation que le Registre ne porte pas n'est pas inventée",
);

$verifier(
    $ecarts['reconnues'] === ['ORG-GAMAD-001'],
    "les organisations reconnues sont dérivées du statut, non présumées",
    implode(' · ', $ecarts['reconnues']),
);

/* ------------------------------------------------------------- vocabulaire */
echo "\n  Vocabulaire — un type ou un statut hors liste est nommé, jamais rapproché\n";

$verifier(
    $ecarts['hors_vocabulaire'] === [],
    "aucun type ni statut employé n'est hors des vocabulaires arrêtés",
    $ecarts['hors_vocabulaire'] === []
        ? 'aucun'
        : implode(' · ', array_map(
            static fn (array $h) => (string) $h['organisation'] . ' : ' . (string) $h['champ'] . ' = ' . (string) $h['valeur'],
            $ecarts['hors_vocabulaire'],
        )),
);

/* --------------------------------------------------------- champs non établis */
echo "\n  Article 37 — les champs que le corpus n'établit pas sont déclarés tels\n";

foreach (Ctr17::CHAMPS_DECLARABLES as $champ) {
    $verifier(
        ($ctr17->champs()[$champ] ?? null) === Ctr17::NON_ETABLI,
        "le champ « {$champ} » demeure non établi, jamais comblé",
    );
}

// Les organisations propriétaires des familles de produits partenaires ne sont
// nommées par aucun texte adopté. Les inscrire exigerait de leur donner un nom
// que le corpus n'a pas écrit.
$verifier(
    $ecarts['proprietaires_partenaires'] === Ctr17::NON_ETABLI,
    "les propriétaires des familles de produits partenaires demeurent non établis",
);

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

$verifier(
    str_contains($rendu, 'ORG-GAMAD-001'),
    "l'organisation inscrite figure sur la page rendue",
);

$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-002 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
