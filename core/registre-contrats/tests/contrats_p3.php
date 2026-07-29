<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-009 — Catalogue des contrats communs (CTR-06).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2) :
 * la neuvième. Une capacité n'hérite pas de la preuve d'une autre.
 *
 * Ce que le test vérifie :
 *   · INV-42 — le catalogue dérive : il restitue les familles que l'Atlas
 *              définit, sans en créer ni en omettre aucune ;
 *   · INV-43 — une famille qu'aucun module ne sert est déclarée sans
 *              producteur, jamais rattachée par ressemblance de numéro ;
 *              une famille qu'aucune capacité ne revendique est déclarée
 *              sans titulaire, et ce constat est distingué selon que son
 *              domaine gardien est tenu ou non ;
 *   · INV-44 — les dépendances entre contrats sont RELEVÉES DANS LE CODE :
 *              le test en connaît une, réelle, et exige qu'elle soit vue ;
 *   · INV-45 — version, compatibilité, stratégie d'erreur et procédure de
 *              sortie demeurent NON ÉTABLI tant que le registre initial des
 *              contrats n'est pas adopté ;
 *   · Article 10 — les quatre écarts nommés sont restitués, aucun corrigé.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * dont une famille de contrat a été délibérément retirée du tableau de l'Atlas,
 * ce test DOIT échouer — le catalogue cessant de dériver l'ensemble complet, et
 * la dépendance observée perdant l'une de ses extrémités. La falsification
 * s'opère sur COPIE HORS DÉPÔT, via CORPUS_PATH.
 *
 * Exécution :             php core/registre-contrats/tests/contrats_p3.php
 * Contre-épreuve : CORPUS_PATH=/chemin/copie/altérée php core/registre-contrats/tests/contrats_p3.php
 * Code de sortie : 0 si la preuve passe, 1 sinon.
 */

use Gamad\RegistreAnnuaire\Ctr14;
use Gamad\RegistreContrats\Ctr06;

require dirname(__DIR__, 2) . '/registre-annuaire/src/Ctr14.php';
require __DIR__ . '/../src/Ctr06.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr06 = new Ctr06($corpus, new Ctr14($corpus));

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — CATALOGUE DES CONTRATS COMMUNS (CAP-CORE-009 / CTR-06)\n\n";

$catalogue = $ctr06->catalogue();
$ecarts    = $ctr06->ecarts();

/* ------------------------------------------------------------------ INV-42 */
echo "  INV-42 — le catalogue dérive, il ne crée aucun contrat\n";

$verifier(
    $ecarts['familles'] === 18,
    "les dix-huit familles définies par l'Atlas sont dérivées, ni plus ni moins",
    $ecarts['familles'] . ' famille(s) cataloguée(s)',
);

$verifier(
    $ctr06->resoudreContrat('CTR-99') === null,
    "une famille que l'Atlas ne définit pas n'est pas inventée",
);

// La capacité que le module sert est déclarée par lui (INV-41) : le numéro de
// famille ne suffit pas, CTR-10 servant deux capacités.
$verifier(
    Ctr06::CAPACITE === 'CAP-CORE-009',
    "le module déclare la capacité qu'il sert",
    Ctr06::CAPACITE,
);

// Le catalogue emprunte le relevé des familles à CTR-14 au lieu de le refaire :
// une seconde lecture du même tableau finirait par en donner une autre vérité.
$verifier(
    array_keys($catalogue) === array_keys((new Ctr14($corpus))->familles()),
    "le relevé des familles est celui de CTR-14, non un second analyseur",
);

/* ------------------------------------------------------------------ INV-43 */
echo "\n  INV-43 — sans producteur, sans titulaire : le vide est déclaré\n";

// CTR-06 est servie par le présent module : le catalogue doit se voir lui-même.
$fiche = $ctr06->resoudreContrat('CTR-06');
$verifier(
    $fiche !== null
        && is_array($fiche['producteur'])
        && $fiche['producteur']['module'] === 'registre-contrats'
        && $fiche['producteur']['capacite'] === 'CAP-CORE-009',
    "le catalogue observe son propre producteur sur le disque",
    $fiche === null || !is_array($fiche['producteur'])
        ? 'aucun producteur observé'
        : (string) $fiche['producteur']['module'] . ' → ' . (string) $fiche['producteur']['capacite'],
);

$sansProducteur = $ctr06->sansProducteur();
$verifier(
    $sansProducteur !== [] && $sansProducteur === array_values(array_filter(
        $sansProducteur,
        static fn (string $r) => $ctr06->resoudreContrat($r)['producteur'] === null,
    )),
    "toute famille déclarée sans producteur l'est réellement",
    implode(' · ', $sansProducteur),
);

// Aucun rattachement par ressemblance : le producteur relevé pour une famille
// est le module qui déclare la servir, jamais celui dont le numéro s'en
// rapproche. Les producteurs relevés doivent tous être des modules présents.
foreach ($ctr06->producteurs() as $reference => $producteur) {
    if ($producteur === null) {
        continue;
    }
    $verifier(
        is_dir($corpus . '/core/' . $producteur['module'] . '/src'),
        "le producteur de {$reference} est un module présent sur le disque",
        (string) $producteur['module'],
    );
}

// CTR-09 — Données et droits : vacance STRUCTURELLE, aucune capacité ne garde
// DOM-07 depuis ADOPTION-0045. Elle est constatée, non traitée en défaut.
$verifier(
    in_array('CTR-09', $ctr06->sansTitulaire(), true),
    "CTR-09 est restituée sans titulaire, comme le corpus l'établit",
    'sans titulaire : ' . implode(' · ', $ctr06->sansTitulaire()),
);

$verifier(
    !array_key_exists('CTR-09', $ctr06->sansTitulaireMalgreGardien()),
    "la vacance de CTR-09 est reconnue structurelle : nulle capacité ne garde DOM-07",
);

// CTR-07 — Événement commun : le Registre la rattachait à CAP-CORE-014 en prose
// seulement (Article 48, « État actuel »), et non dans un champ dérivable. Le
// service a nommé la vacance sans l'attribuer ; l'autorité l'a portée à la
// forme dérivable par ADOPTION-0049, Titre XXX. Le titulaire est LU, non déduit.
$verifier(
    $ctr06->resoudreContrat('CTR-07')['titulaires'] === ['CAP-CORE-014'],
    "CTR-07 est rattachée à CAP-CORE-014 par une déclaration dérivable",
    implode(' · ', $ctr06->resoudreContrat('CTR-07')['titulaires']),
);

// Le tri des deux espèces de vacance demeure le contrôle : toute famille encore
// sans titulaire doit l'être STRUCTURELLEMENT, aucune capacité ne gardant son
// domaine. Une vacance d'une autre espèce reparaîtrait ici.
$malgreGardien = $ctr06->sansTitulaireMalgreGardien();
$verifier(
    $malgreGardien === [],
    "toute vacance restante est structurelle, aucune ne procède d'une déclaration manquante",
    $malgreGardien === [] ? 'aucune' : implode(' · ', array_keys($malgreGardien)),
);

$verifier(
    $ctr06->sansTitulaire() === ['CTR-09', 'CTR-12', 'CTR-13'],
    "les trois vacances structurelles sont celles que le corpus prévoit",
    implode(' · ', $ctr06->sansTitulaire()),
);

/* ------------------------------------------------------------------ INV-44 */
echo "\n  INV-44 — une dépendance est observée dans le code, jamais déduite\n";

$dependances = $ctr06->dependances();
$verifier(
    $dependances !== [],
    "des dépendances entre contrats sont relevées",
    count($dependances) . ' dépendance(s)',
);

// Dépendance réelle et connue : le service CTR-04 (registre-normes) délègue la
// résolution des sources au service CTR-15 (registre-sources). Aucun texte
// adopté ne la déclare — c'est le Constat 3 de la conception.
$normesVersSources = array_values(array_filter(
    $dependances,
    static fn (array $d) => $d['consommateur'] === 'CTR-04' && $d['produit'] === 'CTR-15',
));
$verifier(
    $normesVersSources !== [],
    "la dépendance CTR-04 → CTR-15, réelle et non déclarée, est vue dans le code",
    $normesVersSources === [] ? 'non relevée' : $normesVersSources[0]['module_consommateur']
        . ' → ' . $normesVersSources[0]['module_produit'],
);

$verifier(
    $normesVersSources !== [] && $normesVersSources[0]['declaree'] === 'non',
    "elle est restituée comme non déclarée, et n'est pas corrigée",
);

// Le présent service dépend lui-même de CTR-14 : INV-44 s'applique à son auteur.
$contratsVersAnnuaire = array_values(array_filter(
    $dependances,
    static fn (array $d) => $d['consommateur'] === 'CTR-06' && $d['produit'] === 'CTR-14',
));
$verifier(
    $contratsVersAnnuaire !== [],
    "le catalogue relève sa propre dépendance à CTR-14",
);

$verifier(
    in_array('CTR-04', $ctr06->consommateurs()['CTR-15'] ?? [], true),
    "le relevé des consommateurs est le miroir exact des dépendances",
    'consommateurs de CTR-15 : ' . implode(' · ', $ctr06->consommateurs()['CTR-15'] ?? []),
);

// Une dépendance n'est jamais inventée : un contrat qu'aucun module n'importe
// n'a pas de consommateur.
$verifier(
    $ctr06->consommateursDe('CTR-13') === [],
    "une famille que nul n'importe n'a aucun consommateur",
);

/* ------------------------------------------------------------------ INV-45 */
echo "\n  INV-45 — version et compatibilité ne sont pas inventées\n";

$verifier(
    $ecarts['registre_initial_adopte'] === false,
    "le registre initial des contrats n'est pas adopté : le service le constate",
);

$verifier(
    $ecarts['champs_non_etablis'] === Ctr06::CHAMPS_DECLARABLES,
    "les quatre champs sont restitués NON ÉTABLI, aucun comblé",
    implode(' · ', $ecarts['champs_non_etablis']),
);

foreach (Ctr06::CHAMPS_DECLARABLES as $champ) {
    $verifier(
        ($catalogue['CTR-06']['champs'][$champ] ?? null) === Ctr06::NON_ETABLI,
        "le champ « {$champ} » ne porte aucune valeur plausible",
        (string) ($catalogue['CTR-06']['champs'][$champ] ?? '(absent)'),
    );
}

/* ------------------------------------------------- Article 10 — les écarts */
echo "\n  Article 10 — les quatre écarts sont nommés, aucun n'est arbitré\n";

$verifier(
    $ecarts['familles_servies'] > 0 && $ecarts['familles_servies'] < $ecarts['familles'],
    "le décompte des familles servies est chiffré, ni nul ni total",
    sprintf('%d famille(s) servie(s) sur %d', $ecarts['familles_servies'], $ecarts['familles']),
);

$verifier(
    $ecarts['dependances_non_declarees'] !== [],
    "les dépendances non déclarées sont relevées plutôt que passées sous silence",
    count($ecarts['dependances_non_declarees']) . ' dépendance(s) non déclarée(s)',
);

$verifier(
    str_contains((string) $ecarts['portee'], 'jamais autoritatif'),
    "la portée du catalogue est énoncée avec son résultat",
);

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — le catalogue est consultable sans lancer un test\n";

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

preg_match_all('/CTR-\d{2}/', $rendu, $mv);
$verifier(
    count(array_unique($mv[0])) >= 18,
    "les dix-huit familles figurent sur la page rendue",
    count(array_unique($mv[0])) . ' famille(s) restituée(s)',
);

$verifier(
    str_contains($rendu, Ctr06::NON_ETABLI),
    "la page déclare ce que le corpus n'établit pas",
);

$verifier(
    !preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu),
    "la page ne laisse échapper aucun diagnostic PHP",
);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-009 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
