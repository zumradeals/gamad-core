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
 *   · INV-38 — une divergence est NOMMÉE et jamais arbitrée ;
 *   · INV-39 — un champ que le corpus n'établit pour aucune capacité est
 *              déclaré non établi, jamais comblé par une valeur plausible ;
 *   · INV-40 — une capacité ne porte que les familles dont elle garde le
 *              domaine : le partage d'une famille entre deux capacités qui
 *              gardent toutes deux son domaine est RÉGULIER, une revendication
 *              hors domaine est une usurpation, fût-elle solitaire ;
 *   · INV-41 — chaque module déclare la capacité qu'il sert, le numéro de
 *              famille ne suffisant plus à l'y rattacher ;
 *   · la comparaison Atlas–Registre–réalité exigée par l'Article 55 s'opère
 *     réellement, sur les trois termes.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * dont le domaine gardien d'une famille a été délibérément déplacé dans
 * l'Atlas, ce test DOIT échouer — la capacité qui porte cette famille cessant
 * d'en garder le domaine. La falsification s'opère sur COPIE HORS DÉPÔT, via
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

// Une usurpation est restituée avec sa capacité, sa famille et son motif — les
// éléments du constat — sans qu'aucune correction ne soit appliquée d'office.
foreach ($ctr14->usurpations() as $u) {
    $verifier(
        isset($u['capacite'], $u['famille'], $u['motif'], $u['detail']),
        "une usurpation est nommée sans être corrigée",
        (string) ($u['capacite'] ?? '?') . ' → ' . (string) ($u['motif'] ?? '?'),
    );
}

$verifier(
    $ctr14->usurpations() === [],
    "aucune famille n'est portée hors de son domaine gardien",
    count($ctr14->usurpations()) . ' usurpation(s)',
);

/* ------------------------------------------------------------------ INV-39 */
echo "\n  INV-39 — un champ non établi est déclaré tel\n";

$verifier(
    in_array('responsable', $ecarts['champs_non_etablis'], true),
    "le responsable, qu'aucune fiche n'établit, est déclaré non établi",
    'champs non établis : ' . implode(', ', $ecarts['champs_non_etablis']),
);

/* ------------------------------------------------------------------ INV-40 */
echo "\n  INV-40 — une capacité ne porte que les familles dont elle garde le domaine\n";

$verifier(
    $ecarts['familles'] === 16,
    "les seize familles de contrat sont relevées à l'Atlas",
    $ecarts['familles'] . ' famille(s)',
);

// Le partage n'est pas la faute : trois familles servent deux capacités
// chacune, et l'Atlas l'énonce dans leur intitulé.
$partages = $ctr14->partages();
$verifier(
    array_keys($partages) === ['CTR-08', 'CTR-10', 'CTR-11'],
    "les trois familles partagées sont reconnues régulières, non fautives",
    implode(' · ', array_map(
        fn ($c, $caps) => $c . ' → ' . implode('/', $caps),
        array_keys($partages),
        $partages,
    )),
);

$reattribuees = [
    'CAP-CORE-006' => 'CTR-15',
    'CAP-CORE-005' => 'CTR-16',
];
foreach ($reattribuees as $capacite => $famille) {
    $f = $ctr14->resoudreCapacite($capacite);
    $verifier(
        $f !== null && in_array($famille, $f['contrats'], true),
        "la réattribution de {$capacite} vers {$famille} est dérivée du Registre",
        $f === null ? 'fiche absente' : 'contrats : ' . implode(', ', $f['contrats']),
    );
}

// Un rattachement déclaré par un Titre postérieur porte au champ dérivable une
// famille qu'une fiche nommait en prose seulement — CTR-07 à l'Article 48. Il
// ne retire rien : ce n'est pas une réattribution, et la forme en est distincte.
$journal = $ctr14->resoudreCapacite('CAP-CORE-014');
$verifier(
    $journal !== null && in_array('CTR-07', $journal['contrats'], true),
    "un rattachement déclaré est dérivé, la fiche demeurant intacte",
    $journal === null ? 'fiche absente' : 'contrats : ' . implode(', ', $journal['contrats']),
);

// Une famille retirée par un Titre postérieur ne doit plus être portée, sans
// que le texte qui l'attribuait ait été réécrit.
$sources = $ctr14->resoudreCapacite('CAP-CORE-006');
$verifier(
    $sources !== null && !in_array('CTR-09', $sources['contrats'], true),
    "la famille retirée n'est plus portée, l'article qui l'attribuait demeurant intact",
);

/* ------------------------------------------------------------------ INV-41 */
echo "\n  INV-41 — un module déclare la capacité qu'il sert\n";

$modules = $ctr14->modules();
$verifier(
    $modules !== [] && $ecarts['modules_non_rattaches'] === [],
    "tout module présent déclare sa capacité",
    count($modules) . ' module(s) · non rattachés : '
        . (implode(', ', $ecarts['modules_non_rattaches']) ?: 'aucun'),
);

// CTR-10 sert deux capacités : sans la déclaration du module, rien ne dirait
// laquelle registre-preuves sert réellement.
$preuves = $ctr14->observer('CAP-CORE-015');
$audit = $ctr14->observer('CAP-CORE-013');
$verifier(
    $preuves['module'] === 'registre-preuves' && $audit['module'] === null,
    "une famille partagée n'attribue pas le même module aux deux capacités",
    sprintf(
        'CAP-CORE-015 → %s · CAP-CORE-013 → %s',
        (string) ($preuves['module'] ?? 'aucun'),
        (string) ($audit['module'] ?? 'aucun'),
    ),
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

// Nommer une divergence ne suffit pas si rien n'en fait un obstacle. La carte a
// porté deux actes durant un état que l'acte adopté contredisait — module
// présent déclaré NON COMMENCÉE, garde éprouvée déclarée P1 —, et aucune garde
// n'a échoué : celle-ci savait dire, elle ne savait pas refuser.
//
// Une divergence entre l'état déclaré et la réalité observée fait désormais
// échouer la preuve de la capacité qui a mission de la voir. Ce n'est pas
// arbitrer un écart (INV-38) : c'est refuser d'attester un corpus qui se
// contredit lui-même.
$divergentes = array_values(array_filter(
    $ctr14->comparerReel(),
    static fn (array $l) => $l['verdict'] === 'DIVERGENCE',
));
$verifier(
    $ecarts['divergentes'] === 0,
    "aucune capacité ne déclare un état que la réalité observée contredit",
    $divergentes === []
        ? 'aucune divergence'
        : implode(' · ', array_map(
            static fn (array $l) => (string) $l['capacite'] . ' : ' . implode(' ; ', $l['divergences']),
            $divergentes,
        )),
);

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — l'annuaire est consultable sans lancer un test\n";

// Une capacité dont l'état n'est lisible qu'en exécutant sa propre preuve n'est
// pas consultable par l'autorité. La page est rendue ici en mémoire, sur le
// même corpus que le reste de la garde, et son contenu est vérifié.
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

$verifier(
    $erreur === null && $rendu !== '',
    "la page se rend sans erreur",
    $erreur ?? strlen($rendu) . ' octets',
);

preg_match_all('/CAP-CORE-\d{3}/', $rendu, $mv);
$verifier(
    count(array_unique($mv[0])) === 20,
    "les vingt capacités figurent sur la page rendue",
    count(array_unique($mv[0])) . ' capacité(s) restituée(s)',
);

$verifier(
    !preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu),
    "la page ne laisse échapper aucun diagnostic PHP",
);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-020 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
