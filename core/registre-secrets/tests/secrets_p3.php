<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-016 — Gouvernance des secrets et clés (CTR-20).
 *
 * Garde de comportement propre à cette capacité (ADOPTION-0035, Art. 2.2).
 * Capacité de criticité RACINE — la dernière des dix à être éprouvée.
 *
 * Ce que le test vérifie :
 *   · INV-61 — le service ne franchit pas les exclusions de mission des
 *              Articles 4 : l'inventaire demeure NON INVENTORIÉ ;
 *   · INV-66 — l'interdiction absolue de l'Article 3 est relevée, ATTESTÉE
 *              dans les sources lues, et le service n'en restitue jamais la
 *              correspondance ;
 *   · le détecteur n'est pas vacuant — il reconnaît une forme quand on lui en
 *     présente une, sinon son attestation ne vaudrait rien ;
 *   · Article 51 — coffres, détenteurs, seuils, rotation et clés racines
 *     demeurent non établis.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * où l'interdiction absolue de l'Article 3 a été délibérément effacée, ce test
 * DOIT échouer. Falsification sur COPIE HORS DÉPÔT, via CORPUS_PATH — elle
 * RETIRE une interdiction, elle n'introduit AUCUNE valeur secrète, fût-elle
 * fictive, dans aucun fichier.
 */

use Gamad\RegistreSecrets\Ctr20;

require __DIR__ . '/../src/Ctr20.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr20 = new Ctr20($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — GOUVERNANCE DES SECRETS ET CLÉS (CAP-CORE-016 / CTR-20)\n\n";

$ecarts = $ctr20->ecarts();

/* ------------------------------------------------------------------ CTR-20 */
echo "  CTR-20 — résoudre les métadonnées d'un secret, jamais sa valeur\n";

$verifier(Ctr20::CAPACITE === 'CAP-CORE-016', "le module déclare la capacité qu'il sert", Ctr20::CAPACITE);

$schema = $ctr20->schema();
$verifier(
    $ecarts['schema_champs'] === 10,
    "les dix champs du schéma de l'Article 2 sont dérivés, non comptés de mémoire",
    $ecarts['schema_champs'] . ' champ(s)',
);

// L'exclusion de la valeur n'est pas une limitation de la famille : elle en
// est la définition. Une famille qui porterait la valeur violerait la Loi 40.
$verifier(
    !in_array('valeur', array_map('mb_strtolower', $schema), true),
    "la VALEUR ne figure pas au schéma — c'est la définition de la famille",
    implode(' · ', $schema),
);

/* ------------------------------------------------------------------ INV-61 */
echo "\n  INV-61 — le service ne franchit pas une exclusion de mission\n";

$exclusions = $ctr20->exclusionsDeMission();

$verifier(
    $ecarts['exclusions_declarees'] === 2,
    "les DEUX exclusions — secrets et accès privilégiés — sont relevées du corpus",
    $ecarts['exclusions_declarees'] . ' exclusion(s)',
);

foreach ($exclusions as $exclusion) {
    $verifier(
        $exclusion['declaree'] === true && $exclusion['inventaire'] === Ctr20::NON_INVENTORIE,
        "« {$exclusion['objet']} » : " . (string) $exclusion['inventaire'],
        substr((string) $exclusion['motif'], 0, 100) . '…',
    );
}

$inventaire = $ctr20->inventaire();
foreach (['secrets', 'cles', 'certificats', 'coffres', 'detenteurs'] as $objet) {
    $verifier(
        ($inventaire[$objet] ?? null) === Ctr20::NON_INVENTORIE,
        "« {$objet} » demeure non inventorié, et le service le déclare",
    );
}

$verifier(
    str_contains((string) $inventaire['source'], 'ADOPTION-0025'),
    "la frontière invoquée est celle des accès réservés, nommée avec sa source",
    (string) $inventaire['source'],
);

/* ------------------------------------------------------------------ INV-66 */
echo "\n  INV-66 — une interdiction absolue borne le service, non seulement sa portée\n";

$interdiction = $ctr20->interdictionAbsolue();

// Le cœur de INV-66, et le point que la contre-épreuve retourne.
$verifier(
    $interdiction['declaree'] === true && $ecarts['interdiction_declaree'] === true,
    "l'interdiction absolue de l'Article 3 est relevée du corpus, non supposée",
    substr((string) $interdiction['enonce'], 0, 110) . '…',
);

$verifier(
    in_array('Loi 40', $interdiction['fondements'], true),
    "ses fondements sont nommés — la Loi 40 de CORE-LAWS-0001",
    implode(', ', $interdiction['fondements']),
);

$attestation = $ctr20->attesterInterdiction();

$verifier(
    $attestation['tenue'] === true && $ecarts['interdiction_tenue'] === true,
    "l'interdiction est ATTESTÉE tenue dans les sources lues",
    $ecarts['occurrences_de_valeur'] . ' occurrence(s) de forme de valeur',
);

$verifier(
    $attestation['releve'] === [],
    "le relevé est vide, et il ne porterait de toute façon aucune correspondance",
);

$verifier(
    str_contains((string) $attestation['portee'], 'jamais la correspondance'),
    "le service restitue le nom du motif et le nombre, jamais ce qu'il a trouvé",
);

/* ------------------------------------------ le détecteur n'est pas vacuant */
echo "\n  Un détecteur qui ne peut rien reconnaître n'atteste rien\n";

// Échantillon assemblé À L'EXÉCUTION à partir de caractères de remplissage.
// Ce n'est pas un secret : c'est la FORME d'un secret, et aucune valeur
// secrète — fictive ou non — n'est écrite dans ce fichier ni dans le dépôt.
$temoin = 'mot de passe : ' . str_repeat('X', 16);

$reconnues = $ctr20->formesDetectees($temoin);
$verifier(
    $reconnues !== [],
    "présenté à une forme de valeur, le détecteur la reconnaît",
    implode(', ', $reconnues),
);

$verifier(
    $ctr20->formesDetectees('gardien, finalité, politique de rotation') === [],
    "présenté à des métadonnées, il ne reconnaît rien — aucun faux positif",
);

/* ---------------------------------------------------- champs non établis */
echo "\n  Article 51 — les décisions ouvertes ne sont pas comblées\n";

foreach (Ctr20::CHAMPS_DECLARABLES as $champ) {
    $verifier(
        in_array($champ, $ecarts['champs_non_etablis'], true),
        "le champ « {$champ} » demeure non établi",
    );
}

$verifier(
    str_contains((string) $ecarts['ecart_global_securite'], 'Article 72'),
    "l'écart global de sécurité de l'Article 72 est nommé, non comblé",
);

/* ------------------------------------------- point d'entrée de consultation */
echo "\n  Point d'entrée — l'état des secrets est consultable sans lancer un test\n";

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
$verifier(str_contains($rendu, 'NON INVENTORIÉ'), "la page déclare ce que le service n'inventorie pas");
$verifier(
    $ctr20->formesDetectees($rendu) === [],
    "la page elle-même ne présente aucune forme de valeur secrète",
);
$verifier(!preg_match('/\b(Fatal error|Warning|Notice|Deprecated)\b/', $rendu), "la page ne laisse échapper aucun diagnostic PHP");

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-016 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
