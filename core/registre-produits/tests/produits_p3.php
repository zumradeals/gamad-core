<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-011 — Registre des produits (CTR-08, part produits).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 * La famille CTR-08 sert deux capacités ; chacune a sa garde, et aucune
 * n'hérite de la preuve de l'autre.
 *
 * Ce que le test vérifie :
 *   · INV-52 — admission et conformité ne se présument jamais : aucun produit
 *              non admis n'est restitué comme conforme ;
 *   · INV-53 — l'état courant procède du dernier Titre, l'état initial
 *              demeurant lisible, et un état hors du vocabulaire de
 *              l'Article 22 est nommé tel, jamais traduit ;
 *   · la réserve d'ADOPTION-0025, Art. 3.c — aucun produit certifié — est
 *     dérivée du corpus et non recopiée.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où un produit non admis a été délibérément déclaré conforme, ce test DOIT
 * échouer. Falsification sur COPIE HORS DÉPÔT, via CORPUS_PATH.
 *
 * Exécution :             php core/registre-produits/tests/produits_p3.php
 * Contre-épreuve : CORPUS_PATH=/chemin/copie/altérée php core/registre-produits/tests/produits_p3.php
 */

use Gamad\RegistreProduits\Ctr08;

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

echo "PREUVE P3 — REGISTRE DES PRODUITS (CAP-CORE-011 / CTR-08)\n\n";

$ecarts = $ctr08->ecarts();

/* ------------------------------------------------------------- dérivation */
echo "  Le portefeuille est dérivé du Registre initial des produits\n";

$verifier(
    Ctr08::CAPACITE === 'CAP-CORE-011',
    "le module déclare la capacité qu'il sert — CTR-08 en sert deux",
    Ctr08::CAPACITE,
);

$verifier(
    $ecarts['produits'] === 4,
    "les quatre produits historiques sont dérivés du tableau de l'Article 43",
    $ecarts['produits'] . ' produit(s)',
);

$verifier(
    $ctr08->resoudreProduit('PRD-GAMAD-999') === null,
    "un produit que le Registre ne porte pas n'est pas inventé",
);

/* ------------------------------------------------------------------ INV-52 */
echo "\n  INV-52 — admission et conformité ne se présument jamais\n";

$verifier(
    count($ecarts['non_admis']) === 4,
    "aucun des quatre produits n'a d'admission acquise",
    implode(' · ', $ecarts['non_admis']),
);

$verifier(
    $ecarts['pretentions_sans_dossier'] === [],
    "aucun produit non admis n'est restitué comme conforme",
    $ecarts['pretentions_sans_dossier'] === []
        ? 'aucune prétention'
        : implode(' · ', array_map(
            static fn (array $p) => (string) $p['produit'] . ' : ' . (string) $p['conformite'],
            $ecarts['pretentions_sans_dossier'],
        )),
);

// GamaDrive est reconnu produit officiel depuis ADOPTION-0023, et son dossier
// d'admission demeure à constituer. Reconnaissance et admission sont deux
// choses ; les confondre certifierait un produit que nul n'a évalué.
$drive = $ctr08->resoudreProduit('PRD-GAMAD-002');
$verifier(
    $drive !== null
        && str_contains((string) $drive['etat'], 'PRODUIT OFFICIEL RECONNU')
        && $drive['admission'] === 'DOSSIER À CONSTITUER'
        && $drive['conformite'] === 'NON ÉVALUÉ',
    "un produit officiel reconnu n'est pas pour autant admis ni conforme",
    $drive === null ? 'produit absent' : (string) $drive['admission'] . ' | ' . (string) $drive['conformite'],
);

$verifier(
    $ecarts['produits_certifies'] === 0,
    "la réserve d'ADOPTION-0025, Art. 3.c est dérivée : aucun produit certifié",
);

$verifier(
    count($ecarts['sans_proprietaire']) === 4,
    "aucun propriétaire institutionnel n'est désigné, et cela est déclaré",
    implode(' · ', $ecarts['sans_proprietaire']),
);

/* ------------------------------------------------------------------ INV-53 */
echo "\n  INV-53 — l'état courant procède du dernier Titre, jamais traduit\n";

$changes = $ctr08->etatsChanges();
$verifier(
    count($changes) === 4,
    "les quatre états ont été changés par un Titre postérieur",
    count($changes) . ' changement(s)',
);

// L'état initial n'est pas effacé : un registre qui le perdrait perdrait la
// trace de la décision qui l'a changé.
$id = $ctr08->resoudreProduit('PRD-GAMAD-001');
$verifier(
    $id !== null
        && $id['etat_initial'] === 'HISTORIQUE À QUALIFIER'
        && str_contains((string) $id['etat'], 'DISSOUS'),
    "l'état initial demeure lisible à côté de l'état courant",
    $id === null ? 'produit absent' : (string) $id['etat_initial'] . ' → ' . (string) $id['etat'],
);

$hors = $ctr08->etatsHorsVocabulaire();
$verifier(
    count($hors) === 3,
    "les états courants absents du vocabulaire de l'Article 22 sont nommés",
    implode(' · ', array_map(
        static fn ($e, $p) => $e . ' (' . count($p) . ')',
        array_keys($hors),
        $hors,
    )),
);

// Aucun de ces états n'a été rapproché d'ADMIS, de RETIRÉ ni d'ARCHIVÉ.
foreach ($ctr08->portefeuille() as $reference => $p) {
    $verifier(
        !in_array($p['etat'], Ctr08::ETATS_PORTEFEUILLE, true),
        "l'état de {$reference} est restitué mot pour mot, non traduit",
        (string) $p['etat'],
    );
}

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — le portefeuille est consultable sans lancer un test\n";

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

preg_match_all('/PRD-GAMAD-\d{3}/', $rendu, $mv);
$verifier(count(array_unique($mv[0])) === 4, "les quatre produits figurent sur la page rendue", count(array_unique($mv[0])) . ' produit(s)');

$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-011 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
