<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-010 — Lexique canonique (CTR-19).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 *
 * Ce que le test vérifie :
 *   · INV-64 — la version de référence est RECALCULÉE et confrontée à la
 *              déclaration de l'Article 6, jamais présumée ;
 *   · INV-63 — l'observation lexicale non tranchée demeure non tranchée,
 *              quel que soit le nombre de textes qui la reprennent ;
 *   · INV-59 — l'absence de décision lexicale est déclarée et motivée ;
 *   · Article 45 — numérotation, statut des synonymes et gouvernance des
 *     termes locaux demeurent non établis.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où l'empreinte de référence du Lexique a été délibérément modifiée, ce test
 * DOIT échouer. Falsification sur COPIE HORS DÉPÔT, via CORPUS_PATH.
 */

use Gamad\RegistreLexique\Ctr19;

require __DIR__ . '/../src/Ctr19.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr19 = new Ctr19($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — LEXIQUE CANONIQUE (CAP-CORE-010 / CTR-19)\n\n";

$ecarts = $ctr19->ecarts();

/* ------------------------------------------------------------------ INV-64 */
echo "  INV-64 — une version de référence se vérifie, elle ne se présume pas\n";

$verifier(Ctr19::CAPACITE === 'CAP-CORE-010', "le module déclare la capacité qu'il sert", Ctr19::CAPACITE);

$version = $ctr19->versionDeReference();

$verifier(
    $version['empreinte_declaree'] !== null,
    "l'empreinte déclarée est relevée du corpus, non supposée",
    (string) $version['empreinte_declaree'],
);

$verifier(
    $version['empreinte_reelle'] !== null,
    "l'empreinte réelle du Lexique est recalculée par le service",
    (string) $version['empreinte_reelle'],
);

// Le cœur de INV-64 : la concordance est CONSTATÉE, pas supposée. C'est ce
// point que la contre-épreuve retourne.
$verifier(
    $version['concordante'] === true && $ecarts['empreinte_concordante'] === true,
    "l'empreinte déclarée et l'empreinte recalculée concordent",
    $version['empreinte_declaree'] === $version['empreinte_reelle']
        ? 'identiques'
        : 'ÉCART : déclarée ' . $version['empreinte_declaree'] . ' ≠ réelle ' . $version['empreinte_reelle'],
);

$verifier(
    $version['version'] === '0.1',
    "la version applicable est celle que le registre déclare",
    (string) $version['version'],
);

$verifier(
    str_contains((string) $version['portee'], 'INV-43'),
    "le service ne met à jour aucune empreinte déclarée : un écart se soumet",
);

/* ------------------------------------------------ résolution d'un terme */
echo "\n  CTR-19 — résoudre un terme, sa définition et le texte qui la porte\n";

$verifier(
    $ecarts['entrees'] === 341,
    "les 341 entrées de LEXICON-0001 sont dérivées, non comptées de mémoire",
    $ecarts['entrees'] . ' entrée(s)',
);

$connu = $ctr19->resoudreTerme('Registre lexical');
$verifier(
    $connu['trouve'] === true && $connu['entree'] === 335,
    "un terme canonique se résout vers son entrée et sa source",
    (string) $connu['source'],
);

// Un terme absent est restitué comme absent, jamais rapproché du plus
// ressemblant : rapprocher serait trancher une ambiguïté réservée à l'autorité.
$absent = $ctr19->resoudreTerme('Registraire constitutionnel');
$verifier(
    $absent['trouve'] === false && $absent['definition'] === null,
    "un terme absent est restitué absent, sans rapprochement approximatif",
);

/* ------------------------------------------------------------------ INV-63 */
echo "\n  INV-63 — une observation reportée n'est pas une observation tranchée\n";

$observations = $ctr19->observationsNonTranchees();

$verifier(
    count($observations) === 1,
    "l'observation non tranchée de l'Article 8 est relevée du corpus",
    count($observations) . ' observation(s)',
);

$observation = $observations[0] ?? null;

$verifier(
    is_array($observation) && $observation['statut'] === Ctr19::NON_TRANCHE,
    "elle demeure NON TRANCHÉE",
    is_array($observation) ? (string) $observation['statut'] : 'absente',
);

$verifier(
    is_array($observation) && $observation['arbitrages'] === 0,
    "aucun arbitrage n'est enregistré, et aucun n'est présumé",
);

// Le cœur de INV-63 : le terme est employé dans au moins un texte ET reporté
// dans au moins un autre. Deux textes qui signalent la même observation sans
// la trancher ne font pas un traitement.
$verifier(
    is_array($observation) && $observation['employe_dans'] !== [],
    "le terme est employé dans au moins un texte adopté",
    is_array($observation) ? implode(', ', $observation['employe_dans']) : '',
);

$verifier(
    is_array($observation) && $observation['reportee_dans'] !== [],
    "l'observation est reportée dans au moins un autre texte — sans y être tranchée",
    is_array($observation) ? implode(', ', $observation['reportee_dans']) : '',
);

$verifier(
    is_array($observation) && $observation['present_au_lexique'] === false,
    "le terme observé demeure absent des entrées du Lexique",
);

/* ------------------------------------------------------------------ INV-59 */
echo "\n  INV-59 — l'absence de décision lexicale est déclarée et motivée\n";

$decisions = $ctr19->decisionsEtConflits();

$verifier(
    $decisions['absence_declaree'] === true,
    "l'absence de l'Article 7 est relevée avec son motif, non supposée",
    substr((string) $decisions['motif'], 0, 110) . '…',
);

$verifier(
    $decisions['decisions_lexicales'] === 0 && $decisions['conflits'] === 0,
    "aucune décision lexicale ni conflit n'est enregistré, et aucun n'est inventé",
);

$verifier(
    str_contains((string) $decisions['qualification'], 'INV-59'),
    "l'absence est qualifiée de déclarée et motivée — non d'inventaire manquant",
    (string) $decisions['qualification'],
);

/* ---------------------------------------------------- champs non établis */
echo "\n  Article 45 — les décisions ouvertes ne sont pas comblées\n";

foreach (Ctr19::CHAMPS_DECLARABLES as $champ) {
    $verifier(
        in_array($champ, $ecarts['champs_non_etablis'], true),
        "le champ « {$champ} » demeure non établi",
    );
}

$verifier(
    $ecarts['controle_lexical_mecanise'] === false,
    "le contrôle lexical mécanisé de l'Article 3 n'est pas déclaré établi",
);

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — l'état du lexique est consultable sans lancer un test\n";

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
$verifier(str_contains($rendu, 'NON TRANCHÉE'), "la page nomme ce que nul n'a tranché");
$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-010 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
