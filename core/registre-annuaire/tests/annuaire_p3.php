<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-020 — annuaire des capacités et Atlas (CTR-14).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 *
 * Ce que le test vérifie :
 *   · INV-36 — l'annuaire décrit sans fonder : les vingt fiches sont dérivées
 *              du Registre, aucune n'est inventée ni omise ;
 *   · INV-37 — les quatre dimensions d'état sont restituées distinctement, et
 *              l'état courant procède du dernier Titre qui l'a constaté ;
 *   · INV-38 — une divergence est NOMMÉE et jamais arbitrée : un contrat
 *              revendiqué par deux capacités suspend la comparaison au réel
 *              au lieu de trancher ;
 *   · INV-39 — un champ que le corpus n'établit pour aucune capacité est
 *              déclaré non établi, jamais comblé par une valeur plausible ;
 *   · la comparaison Atlas–Registre–réalité exigée par l'Article 55 s'opère
 *     réellement, sur les trois termes.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * dont le domaine d'une capacité a été délibérément modifié dans l'Atlas sans
 * l'être au Registre, ce test DOIT échouer — la divergence Atlas/Registre
 * cessant d'être nulle. La falsification s'opère sur COPIE HORS DÉPÔT, via
 * CORPUS_PATH.
 *
 * Exécution :             php core/registre-annuaire/tests/annuaire_p3.php
 * Contre-épreuve : CORPUS_PATH=/chemin/copie/altérée php core/registre-annuaire/tests/annuaire_p3.php
 * Code de sortie : 0 si la preuve passe, 1 sinon.
 */

use Gamad\RegistreAnnuaire\Ctr14;

require __DIR__ . '/../src/Ctr14.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr14 = new Ctr14($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — ANNUAIRE DES CAPACITÉS ET ATLAS (CAP-CORE-020 / CTR-14)\n\n";

/* ------------------------------------------------------------------ INV-36 */
echo "  INV-36 — l'annuaire décrit, il ne fonde pas\n";

$ecarts = $ctr14->ecarts();
$verifier(
    $ecarts['capacites'] === 20,
    "les vingt capacités souveraines sont dérivées du Registre",
    $ecarts['capacites'] . ' fiche(s) relevée(s)',
);

$fiche = $ctr14->resoudreCapacite('CAP-CORE-020');
$verifier(
    $fiche !== null && $fiche['contrats'] === ['CTR-14'],
    "CAP-CORE-020 résout son propre contrat, CTR-14",
    $fiche === null ? 'fiche absente' : 'contrats : ' . implode(', ', $fiche['contrats']),
);

$verifier(
    $ctr14->resoudreCapacite('CAP-CORE-999') === null,
    "une capacité inexistante n'est pas inventée",
);

/* ------------------------------------------------------------------ INV-37 */
echo "\n  INV-37 — quatre dimensions, jamais confondues\n";

$normes = $ctr14->resoudreCapacite('CAP-CORE-007');
$etats = $normes['etats'] ?? [];
$verifier(
    array_keys($etats) === Ctr14::DIMENSIONS,
    "les quatre dimensions sont restituées séparément",
    implode(' · ', array_map(fn ($d) => $d . '=' . ($etats[$d] ?? '?'), Ctr14::DIMENSIONS)),
);

// L'état courant doit procéder du dernier Titre, non du tableau initial.
$verifier(
    ($etats['conception'] ?? null) === 'CONÇUE' && ($etats['implementation'] ?? null) !== 'NON COMMENCÉE',
    "l'état courant procède du dernier Titre, non du tableau de l'Article 31",
);

/* ------------------------------------------------------------------ INV-38 */
echo "\n  INV-38 — une divergence est nommée, jamais arbitrée\n";

$collisions = $ctr14->collisions();
$verifier(
    $collisions !== [],
    "les collisions de numéro de contrat sont relevées",
    $collisions === [] ? 'aucune' : implode(' · ', array_map(
        fn ($c, $caps) => $c . ' → ' . implode('/', $caps),
        array_keys($collisions),
        $collisions,
    )),
);

// Une capacité dont le contrat est contesté ne reçoit pas de verdict tranché.
$contestee = array_values($collisions)[0][0] ?? null;
$ligne = $contestee === null ? null : ($ctr14->comparerReel($contestee)[0] ?? null);
$verifier(
    $ligne !== null && $ligne['verdict'] === 'INDETERMINE',
    "un contrat contesté suspend la comparaison au réel au lieu de la trancher",
    $ligne === null ? 'aucune capacité contestée' : $contestee . ' → ' . $ligne['verdict'],
);

/* ------------------------------------------------------------------ INV-39 */
echo "\n  INV-39 — un champ non établi est déclaré tel\n";

$verifier(
    in_array('responsable', $ecarts['champs_non_etablis'], true),
    "le responsable, qu'aucune fiche n'établit, est déclaré non établi",
    'champs non établis : ' . implode(', ', $ecarts['champs_non_etablis']),
);

/* --------------------------------------- comparaison Atlas–Registre–réalité */
echo "\n  Article 55 — comparaison Atlas–Registre–réalité\n";

$atlas = $ctr14->comparerAtlas();
$verifier(
    count($atlas) === 20,
    "l'Atlas et le Registre décrivent le même ensemble de capacités",
    count($atlas) . ' capacité(s) confrontée(s)',
);

$verifier(
    $ecarts['atlas_divergent'] === 0,
    "Atlas et Registre concordent sur libellé et domaine",
    $ecarts['atlas_divergent'] . ' divergence(s)',
);

$observe = $ctr14->observer('CAP-CORE-007');
$verifier(
    $observe['code_present'] === true && $observe['garde'] !== null && $observe['garde_en_ci'] === true,
    "la réalité est observée : module, garde et exécution en intégration continue",
    sprintf('module=%s garde=%s ci=%s', (string) $observe['module'], (string) $observe['garde'], $observe['garde_en_ci'] ? 'oui' : 'non'),
);

$verifier(
    $ecarts['capacites_codees'] > 0 && $ecarts['capacites_codees'] < $ecarts['capacites'],
    "le décompte des capacités codées est chiffré, ni nul ni total",
    sprintf('%d capacité(s) codée(s) sur %d', $ecarts['capacites_codees'], $ecarts['capacites']),
);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-020 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
