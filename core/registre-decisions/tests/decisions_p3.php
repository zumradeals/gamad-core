<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-008 — Registre des décisions (CTR-05).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2) :
 * la dixième. Une capacité n'hérite pas de la preuve d'une autre.
 *
 * Ce que le test vérifie :
 *   · INV-46 — le registre dérive des actes : toute décision restituée a un
 *              acte au dépôt, aucune n'est inventée ni omise ;
 *   · INV-47 — une décision ouverte ne se clôt que par un acte qui la nomme ;
 *              le silence ne clôt rien, et une clôture qui invoque un acte
 *              absent ne clôt rien non plus ;
 *   · INV-48 — les trois inventaires sont confrontés et NON réconciliés :
 *              l'écart du tableau consolidé demeure visible et chiffré ;
 *   · INV-49 — un statut hors du vocabulaire de l'Article 17 est nommé, et
 *              jamais traduit vers le terme le plus proche ;
 *   · INV-50 — la classe d'une décision est dérivée lorsqu'un texte la porte,
 *              NON ÉTABLI sinon, jamais étendue par ressemblance d'objet ;
 *   · Article 27 — les champs qu'aucun texte n'établit sont déclarés tels.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où une décision ouverte a été délibérément close par une déclaration invoquant
 * un acte inexistant, ce test DOIT échouer. La falsification s'opère sur COPIE
 * HORS DÉPÔT, via CORPUS_PATH.
 *
 * Exécution :             php core/registre-decisions/tests/decisions_p3.php
 * Contre-épreuve : CORPUS_PATH=/chemin/copie/altérée php core/registre-decisions/tests/decisions_p3.php
 * Code de sortie : 0 si la preuve passe, 1 sinon.
 */

use Gamad\RegistreDecisions\Ctr05;

require __DIR__ . '/../src/Ctr05.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr05 = new Ctr05($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — REGISTRE DES DÉCISIONS (CAP-CORE-008 / CTR-05)\n\n";

$ecarts     = $ctr05->ecarts();
$inventaire = $ecarts['inventaire'];

/* ------------------------------------------------------------------ INV-46 */
echo "  INV-46 — le registre dérive des actes, il n'en fonde aucun\n";

$verifier(
    Ctr05::CAPACITE === 'CAP-CORE-008',
    "le module déclare la capacité qu'il sert",
    Ctr05::CAPACITE,
);

$verifier(
    $inventaire['actes'] > 0 && $inventaire['actes'] === $inventaire['index'],
    "chaque acte présent au dépôt est inscrit à l'index, et réciproquement",
    sprintf('%d acte(s) · %d ligne(s) d\'index', $inventaire['actes'], $inventaire['index']),
);

$verifier(
    $inventaire['absents_index'] === [] && $inventaire['absents_disque'] === [],
    "aucune décision n'est inventée, aucune n'est omise",
    'sur disque hors index : ' . (implode(' · ', $inventaire['absents_index']) ?: 'aucune')
        . ' | à l\'index sans acte : ' . (implode(' · ', $inventaire['absents_disque']) ?: 'aucune'),
);

// Une référence peut porter plusieurs fichiers — ADOPTION-0025 porte l'acte et
// sa feuille d'exécution. Compter les fichiers donnerait un inventaire faux.
$verifier(
    $inventaire['fichiers'] > $inventaire['actes'],
    "l'inventaire compte les références, non les fichiers",
    sprintf('%d fichier(s) pour %d référence(s)', $inventaire['fichiers'], $inventaire['actes']),
);

$verifier(
    $ctr05->resoudreDecision('ADOPTION-9999') === null,
    "une décision que le corpus ne porte pas n'est pas inventée",
);

/* ------------------------------------------------------------------ INV-47 */
echo "\n  INV-47 — une décision ouverte ne se clôt que par un acte qui la nomme\n";

$inscrites = $ctr05->inscrites();
$verifier(
    $inscrites !== [],
    "les décisions réservées à l'autorité sont dérivées de leur forme",
    count($inscrites) . ' inscrite(s)',
);

$verifier(
    $ecarts['ouvertes'] > 0 && $ecarts['closes'] > 0,
    "le registre distingue ce qui demeure ouvert de ce qu'un acte a clos",
    sprintf('%d ouverte(s) · %d close(s)', $ecarts['ouvertes'], $ecarts['closes']),
);

// La clôture s'ajoute à l'inscription ; elle ne l'efface pas. DECISION-0024 —
// le rattachement de CTR-07 — a été posée par ADOPTION-0048 et close par
// ADOPTION-0049 : les deux faits doivent demeurer lisibles.
$rattachement = $inscrites['DECISION-0024'] ?? null;
$verifier(
    $rattachement !== null
        && $rattachement['close_par'] === 'ADOPTION-0049'
        && str_contains((string) $rattachement['source'], 'ADOPTION-0048'),
    "une décision close conserve l'acte qui l'a posée et celui qui l'a close",
    $rattachement === null
        ? 'inscription absente'
        : (string) $rattachement['source'] . ' → close par ' . (string) $rattachement['close_par'],
);

// Une clôture qui invoque un acte absent du dépôt ne clôt rien.
$verifier(
    $ecarts['clotures_sans_acte'] === [],
    "aucune clôture n'invoque un acte que le dépôt ne porte pas",
    $ecarts['clotures_sans_acte'] === []
        ? 'aucune'
        : implode(' · ', array_map(
            static fn ($d, $a) => $d . ' → ' . $a,
            array_keys($ecarts['clotures_sans_acte']),
            $ecarts['clotures_sans_acte'],
        )),
);

// Le silence ne clôt rien : les décisions les plus anciennes du corpus, posées
// par un registre adopté le 26 juillet 2026, demeurent ouvertes.
$verifier(
    isset($inscrites['DECISION-0001']) && $inscrites['DECISION-0001']['close_par'] === null,
    "l'ancienneté ne clôt pas : la plus ancienne décision inscrite demeure ouverte",
    isset($inscrites['DECISION-0001']) ? (string) $inscrites['DECISION-0001']['source'] : 'inscription absente',
);

/* ------------------------------------------------------------------ INV-51 */
echo "\n  INV-51 — un acte de lot énumère chacun de ses incréments\n";

// Le contrôle porte aujourd'hui sur un ensemble qui peut être vide : le dépôt
// ne portera d'acte de lot qu'à partir du premier. Ce n'est pas une faiblesse
// du contrôle, c'est son état initial — et c'est la contre-épreuve qui
// l'éprouve, en fabriquant hors dépôt le lot défaillant que le dépôt ne porte
// pas. Un contrôle qui n'a rien à voir aujourd'hui doit voir demain.
$defaillants = $ctr05->incrementsDefaillants();
$verifier(
    $defaillants === [],
    "aucun incrément de lot ne nomme une capacité, une garde ou une CI absente",
    $defaillants === []
        ? sprintf('%d lot(s) · %d incrément(s) énuméré(s)', $ecarts['lots'], $ecarts['increments_de_lot'])
        : implode(' · ', array_map(
            static fn (array $i) => (string) $i['acte'] . ' → ' . (string) $i['capacite'] . ' : ' . (string) $i['motif'],
            $defaillants,
        )),
);

foreach ($ctr05->lots() as $acte => $increments) {
    $verifier(
        $increments !== [],
        "l'acte de lot {$acte} énumère ses incréments",
        count($increments) . ' incrément(s)',
    );
}

/* ------------------------------------------------------------------ INV-48 */
echo "\n  INV-48 — les inventaires sont confrontés, jamais réconciliés\n";

$verifier(
    $inventaire['consolide'] > 0 && $inventaire['consolide'] < $inventaire['index'],
    "le tableau consolidé de l'Article 92 est relevé tel quel, non prolongé",
    sprintf('%d ligne(s) au tableau consolidé pour %d à l\'index', $inventaire['consolide'], $inventaire['index']),
);

$verifier(
    count($inventaire['absents_consolide']) === $inventaire['index'] - $inventaire['consolide'],
    "l'écart entre les deux tables est chiffré et énuméré, non résorbé",
    count($inventaire['absents_consolide']) . ' décision(s) hors du tableau consolidé',
);

$verifier(
    $inventaire['hors_index'] === [],
    "le tableau consolidé ne porte aucune décision absente de l'index",
);

/* ------------------------------------------------------------------ INV-49 */
echo "\n  INV-49 — un statut hors vocabulaire est nommé, jamais traduit\n";

$hors = $ctr05->statutsHorsVocabulaire();
$verifier(
    $hors !== [],
    "les statuts employés sont confrontés au vocabulaire de l'Article 17",
    implode(' · ', array_map(
        static fn ($s, $r) => $s . ' (' . count($r) . ')',
        array_keys($hors),
        $hors,
    )),
);

// « LU ET ADOPTÉ — EN VIGUEUR » ressemble à ADOPTÉE suivi de EN VIGUEUR. Le
// service ne doit pas avoir opéré ce rapprochement : le statut restitué pour
// une décision est celui que l'index porte, mot pour mot.
$g0 = $ctr05->resoudreDecision('ADOPTION-0025');
$verifier(
    $g0 !== null && $g0['statut'] === 'SIGNÉ — G0 CONSTATÉE',
    "le statut restitué est celui que l'index porte, mot pour mot",
    $g0 === null ? 'décision absente' : (string) $g0['statut'],
);

$verifier(
    !in_array($g0['statut'] ?? '', Ctr05::ETATS_DECISION, true)
        && array_key_exists((string) ($g0['statut'] ?? ''), $hors),
    "un statut absent du vocabulaire est relevé comme tel, sans être remplacé",
);

/* ------------------------------------------------------------------ INV-50 */
echo "\n  INV-50 — classe et niveau de risque ne sont pas déduits de l'objet\n";

// Dix-sept adoptions portent une classe au tableau de l'Article 92 : elle est
// dérivée pour celles-là.
$organique = $ctr05->resoudreDecision('ADOPTION-0005');
$verifier(
    ($organique['champs']['classe'] ?? null) === 'Organique',
    "la classe est dérivée lorsqu'un texte adopté la porte",
    (string) ($organique['champs']['classe'] ?? '(absente)'),
);

// Les autres n'en portent aucune, et aucune ne leur est étendue par
// ressemblance d'objet — fût-elle évidente.
$verifier(
    ($ctr05->resoudreDecision('ADOPTION-0040')['champs']['classe'] ?? null) === Ctr05::NON_ETABLI,
    "hors des dix-sept classées, la classe demeure non établie",
);

foreach (['niveau_risque', 'dossier', 'contestation'] as $champ) {
    $verifier(
        in_array($champ, $ecarts['champs_non_etablis'], true),
        "le champ « {$champ} », qu'aucun texte n'établit, est déclaré non établi",
    );
}

$verifier(
    $ecarts['inscription_exhaustive'] === Ctr05::NON_ETABLI,
    "l'exhaustivité de l'inscription n'est pas prétendue",
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

$verifier(
    $erreur === null && $rendu !== '',
    "la page se rend sans erreur",
    $erreur ?? strlen($rendu) . ' octets',
);

preg_match_all('/DECISION-\d{4}/', $rendu, $mv);
$verifier(
    count(array_unique($mv[0])) === count($inscrites),
    "toutes les décisions inscrites figurent sur la page rendue",
    count(array_unique($mv[0])) . ' sur ' . count($inscrites),
);

$verifier(
    !preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu),
    "la page ne laisse échapper aucun diagnostic PHP",
);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-008 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
