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
    $ecarts['familles'] === 20,
    "les vingt familles de contrat sont relevées à l'Atlas",
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
// laquelle registre-preuves sert réellement. Depuis ADOPTION-0057, les deux
// capacités du partage portent chacune leur module — l'intégrité atteste d'un
// objet, l'audit atteste d'un acte —, et c'est la DISTINCTION des modules, non
// l'absence de l'un d'eux, qui établit qu'un partage ne les confond pas.
$preuves = $ctr14->observer('CAP-CORE-015');
$audit = $ctr14->observer('CAP-CORE-013');
$verifier(
    $preuves['module'] === 'registre-preuves'
        && $audit['module'] === 'registre-audit'
        && $preuves['module'] !== $audit['module'],
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

// Ce décompte a longtemps été éprouvé comme « ni nul ni total » : tant qu'une
// capacité restait à coder, un service qui aurait répondu « toutes » se serait
// trahi. Depuis ADOPTION-0057 les vingt sont codées, et la borne haute cesse
// d'être un signal — la conserver ferait échouer la preuve sur un fait vrai.
// Ce qui demeure éprouvable est que le décompte soit DÉRIVÉ : il doit valoir
// exactement le nombre de modules observés, et jamais le total présumé.
$verifier(
    $ecarts['capacites_codees'] > 0
        && $ecarts['capacites_codees'] <= $ecarts['capacites']
        && $ecarts['capacites_codees'] === count(array_filter(
            $ctr14->modules(),
            static fn (array $m): bool => $m['capacite'] !== null,
        )),
    "le décompte des capacités codées est dérivé des modules observés, non présumé",
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
// Un chiffre écrit de mémoire finit par être faux. ADOPTION-0053 a affirmé
// « sept des huit RACINE » là où le corpus en porte dix, dont huit codées.
// Les décomptes par criticité sont désormais DÉRIVÉS, et éprouvés ici : un
// acte qui les cite peut les relire du corpus au lieu de les reconstituer.
$parCriticite = $ctr14->parCriticite();
$attendus = [
    'RACINE'   => ['total' => 10, 'codees' => 10],
    'CRITIQUE' => ['total' => 10, 'codees' => 10],
];
foreach ($attendus as $criticite => $attendu) {
    $releve = $parCriticite[$criticite] ?? null;
    $verifier(
        $releve !== null
            && $releve['total'] === $attendu['total']
            && count($releve['codees']) === $attendu['codees'],
        "le décompte des capacités {$criticite} est dérivé, non écrit de mémoire",
        $releve === null
            ? 'criticité absente'
            : sprintf('%d codée(s) sur %d', count($releve['codees']), $releve['total']),
    );
}

$verifier(
    array_sum(array_column($parCriticite, 'total')) === $ecarts['capacites'],
    "les décomptes par criticité couvrent les vingt capacités, sans reste",
    array_sum(array_column($parCriticite, 'total')) . ' sur ' . $ecarts['capacites'],
);

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

/* -------------------------------- INV-67 à INV-72 — dossier d'admission */
echo "\n  INV-67 à INV-72 — le dossier d'admission s'assemble, et ne conclut pas\n";

// ADOPTION-0060 a rattaché l'admission d'une implémentation souveraine à
// CTR-14 : c'est donc ici, et non ailleurs, que le dossier se dérive.

$verifier(
    $ctr14->admissions() === [],
    "aucune admission n'est inscrite, et le service n'en invente aucune",
    count($ctr14->admissions()) . ' admission(s) relevée(s) à la forme de l\'Article 174',
);

$dossier = $ctr14->dossierAdmission('CAP-CORE-020');

$piecesAttendues = [
    'identite', 'commit_presente', 'acte_adoptant', 'garde', 'contre_epreuve',
    'concordance', 'ecarts_ouverts', 'exclusions_de_mission', 'audit',
];
$absentes = array_values(array_diff($piecesAttendues, array_keys($dossier['pieces'])));
$verifier(
    $absentes === [] && count($dossier['pieces']) === 9,
    "les neuf pièces dérivables de l'Article 13 sont assemblées, ni plus ni moins",
    $absentes === [] ? count($dossier['pieces']) . ' pièce(s)' : 'absente(s) : ' . implode(', ', $absentes),
);

// Une capacité dont la conception déclare une exclusion de mission ne doit pas
// la voir comptée comme un manque (INV-69). CAP-CORE-016 en porte une.
$secrets = $ctr14->dossierAdmission('CAP-CORE-016');
$verifier(
    $secrets['pieces']['exclusions_de_mission'] !== []
        && !in_array('exclusions_de_mission', $secrets['pieces_manquantes'], true),
    "une exclusion de mission déclarée appartient au périmètre, non aux manques",
    implode(', ', $secrets['pieces']['exclusions_de_mission']) ?: 'aucune relevée',
);

// La contre-épreuve est relevée de l'acte, jamais rejouée ni présumée.
$verifier(
    $secrets['pieces']['contre_epreuve']['declaree'] === true
        && $secrets['pieces']['contre_epreuve']['temoin'] === true,
    "la contre-épreuve et son témoin sont relevés de l'acte adoptant",
    (string) $secrets['pieces']['contre_epreuve']['source'],
);

// Les écarts du dossier sont ceux de l'annuaire, pris de `ecarts()` comme
// l'Article 13 le prescrit. Un dossier qui en tiendrait le compte à part
// présenterait à l'admission un état plus clément que l'annuaire publié.
$ecartsAnnuaire = [];
foreach ($ctr14->ecarts()['divergences_par_type'] as $type => $capacites) {
    foreach ($capacites as $c) {
        $ecartsAnnuaire[(string) $c][] = (string) $type;
    }
}
$ecartsDossiers = [];
foreach ($ctr14->comparerReel() as $ligne) {
    $siens = $ctr14->dossierAdmission((string) $ligne['capacite'])['pieces']['ecarts_ouverts'];
    if ($siens !== []) {
        $ecartsDossiers[(string) $ligne['capacite']] = array_keys($siens);
    }
}
$verifier(
    $ecartsDossiers == $ecartsAnnuaire,
    "les écarts ouverts du dossier sont ceux de l'annuaire, ni moindres ni autres",
    $ecartsAnnuaire === [] ? 'aucun écart ouvert de part ni d\'autre' : count($ecartsAnnuaire) . ' capacité(s) en écart',
);

// INV-72 — le service assemble et ne conclut pas. Aucun champ du dossier ne
// porte un avis, une suffisance ni une proposition d'admission.
$conclusions = array_intersect(
    ['avis', 'admis', 'suffisant', 'recommandation', 'proposition', 'verdict_admission'],
    array_keys($dossier),
);
$verifier(
    $conclusions === [],
    "INV-72 — le dossier ne porte aucune conclusion",
    $conclusions === [] ? 'aucun champ de conclusion' : 'champ(s) : ' . implode(', ', $conclusions),
);

// Article 14 de la conception — quatre questions qu'aucun service ne dérive.
$nonDerivables = $dossier['non_derivable'];
$declarees = array_filter($nonDerivables, static fn (string $v) => $v === Ctr14::NON_DERIVABLE);
$verifier(
    count($nonDerivables) === 4 && count($declarees) === 4,
    "les quatre questions non dérivables sont DÉCLARÉES telles, non comblées",
    implode(', ', array_keys($nonDerivables)),
);

// INV-69 — nul ne se présente à l'admission depuis un état partiel. Les vingt
// capacités sont PARTIELLEMENT MATÉRIALISÉE : aucune n'est recevable, et un
// dossier complet ne change rien à cela.
$recevables = [];
$completsNonRecevables = 0;
$incomplets = [];
foreach ($ctr14->comparerReel() as $ligne) {
    $d = $ctr14->dossierAdmission((string) $ligne['capacite']);
    if ($d['recevable_a_l_admission']) {
        $recevables[] = (string) $ligne['capacite'];
    }
    if ($d['dossier_complet'] && !$d['recevable_a_l_admission']) {
        $completsNonRecevables++;
    }
    if (!$d['dossier_complet']) {
        $incomplets[(string) $ligne['capacite']] = $d['pieces_manquantes'];
    }
}
$verifier(
    $recevables === [],
    "INV-69 — aucune capacité n'est recevable à l'admission depuis un état partiel",
    $recevables === [] ? 'aucune des vingt' : implode(', ', $recevables),
);
$verifier(
    $completsNonRecevables === 19,
    "un dossier complet ne vaut pas recevabilité — dix-neuf le démontrent",
    $completsNonRecevables . ' dossier(s) complet(s) et non recevable(s)',
);

// Le dossier relève un fait historique du corpus, et ne l'arrondit pas :
// `ADOPTION-0029` a adopté le premier incrément codé AVANT que
// `ADOPTION-0032`, Art. 3 n'exige une contre-épreuve. Son acte n'en déclare
// donc aucune, et le dossier de `CAP-CORE-007` est incomplet de cette pièce.
// La contre-épreuve existe — `ADOPTION-0031` l'a produite — mais pas là où
// l'Article 13 la fait chercher. Un service qui irait la prendre ailleurs
// ferait disparaître l'anomalie au lieu de la montrer.
$verifier(
    array_keys($incomplets) === ['CAP-CORE-007']
        && $incomplets['CAP-CORE-007'] === ['contre_epreuve'],
    "le seul dossier incomplet est CAP-CORE-007, faute de contre-épreuve à son acte",
    $incomplets === []
        ? 'aucun dossier incomplet'
        : implode(' · ', array_map(
            static fn (string $c, array $p) => $c . ' : ' . implode(', ', $p),
            array_keys($incomplets),
            $incomplets,
        )),
);

// INV-70, rendu obligatoire par ADOPTION-0061 : l'autorité de décision et
// FCT-CORE-021 étant le même titulaire, aucune admission ne peut être
// prononcée sans porter la mention d'audit non indépendant.
$verifier(
    $dossier['mention_d_audit_requise'] === true,
    "INV-70 — la mention d'audit non indépendant est requise",
    'audit indépendant : ' . var_export($dossier['pieces']['audit']['independante'], true),
);

// La qualité de l'audit est CONSOMMÉE de CAP-CORE-013, jamais recalculée ici.
$verifier(
    str_starts_with((string) $dossier['pieces']['audit']['source'], 'CAP-CORE-013'),
    "la qualité de l'audit est consommée de CAP-CORE-013, non recalculée",
    (string) $dossier['pieces']['audit']['source'],
);

// INV-68 — une admission nomme un commit. Aucune n'étant inscrite, l'état est
// nommé, et non présumé favorable.
$verifier(
    $dossier['etat_admission'] === 'AUCUNE ADMISSION INSCRITE',
    "INV-68 — l'absence d'admission est nommée, non présumée",
    (string) $dossier['etat_admission'],
);

// Une capacité inconnue ne reçoit pas un dossier vide qui passerait pour vrai.
$inconnue = $ctr14->dossierAdmission('CAP-CORE-999');
$verifier(
    ($inconnue['complet'] ?? true) === false,
    "une capacité inconnue ne reçoit aucun dossier",
    (string) ($inconnue['motif'] ?? 'dossier rendu'),
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

// Le dossier n'a de valeur que consultable : l'autorité doit voir sur la page
// ce que le service assemble, y compris le seul dossier incomplet et la mention
// d'audit qu'INV-70 impose. La page porte aussi le refus de conclure, faute de
// quoi un lecteur pressé prendrait un tableau de pièces pour un avis.
$verifier(
    str_contains($rendu, "Dossiers d'admission")
        && str_contains($rendu, 'incomplet — contre_epreuve')
        && str_contains($rendu, 'audit non indépendant')
        && str_contains($rendu, 'ne conclut pas'),
    "les dossiers d'admission sont consultables, et la page ne conclut pas",
    substr_count($rendu, 'incomplet — ') . ' dossier(s) incomplet(s) affiché(s)',
);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-020 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
