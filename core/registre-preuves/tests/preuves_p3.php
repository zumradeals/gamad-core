<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-015 — preuves d'intégrité (CTR-10).
 *
 * Garde de comportement propre à cette capacité, au sens de la doctrine
 * arrêtée par ADOPTION-0035, Art. 2.2 : une capacité n'hérite pas de la preuve
 * d'une autre. Les gardes de CTR-04 et CTR-15 éprouvent des services voisins,
 * non celui-ci.
 *
 * Ce que le test vérifie :
 *   · INV-5  — la politique des algorithmes est DÉRIVÉE du registre adopté,
 *              non codée en dur dans le service ;
 *   · INV-31 — une empreinte sans algorithme nommé n'est pas une preuve : le
 *              service REFUSE de calculer pour un algorithme inconnu plutôt
 *              que de rendre une valeur qui ressemblerait à une preuve ;
 *   · INV-32 — le service sait produire la double conservation, et chiffre
 *              l'écart entre ce qu'il sait faire et ce que le corpus déclare ;
 *   · INV-34 — toute attestation porte `signee: false` et sa portée dans son
 *              corps, jamais en note ;
 *   · INV-35 — l'empreinte réelle est RECALCULÉE depuis le fichier, et le
 *              recalcul indépendant opéré par ce test la confirme ;
 *   · M-33   — l'inventaire nomme les actes d'adoption comme objets sans
 *              preuve déclarée, au lieu de taire l'écart.
 *
 * Ce test n'éprouve DÉLIBÉRÉMENT pas l'absence globale de discordance : c'est
 * l'objet du contrôle documentaire Python, et les deux gardes demeurent
 * séparées (ADOPTION-0027, Art. 4). Il éprouve le contrat CTR-10.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * dont un objet déclaré a été altéré d'un octet, ce test DOIT échouer. Un test
 * qui ne peut pas échouer ne prouve rien. La falsification s'opère sur COPIE
 * HORS DÉPÔT, en pointant CORPUS_PATH sur cette copie.
 *
 * Exécution :             php core/registre-preuves/tests/preuves_p3.php
 * Contre-épreuve : CORPUS_PATH=/chemin/copie/altérée php core/registre-preuves/tests/preuves_p3.php
 * Code de sortie : 0 si la preuve passe, 1 sinon.
 */

use Gamad\RegistrePreuves\Ctr10;
use Gamad\RegistrePreuves\Empreinte;

require __DIR__ . '/../src/Empreinte.php';
require __DIR__ . '/../src/Ctr10.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr10 = new Ctr10($corpus);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — PREUVES D'INTÉGRITÉ (CAP-CORE-015 / CTR-10)\n\n";

/* ------------------------------------------------------------------- INV-5 */
echo "  INV-5 — politique dérivée du corpus, non codée en dur\n";

$politique = $ctr10->politique();
$parCode = [];
foreach ($politique as $a) {
    $parCode[$a['code']] = $a;
}

$verifier(
    count($politique) >= 2,
    "au moins deux algorithmes sont déclarés par le registre adopté",
    sprintf('%d algorithme(s) dérivé(s) : %s', count($politique), implode(', ', array_keys($parCode))),
);

$verifier(
    ($parCode['git-sha1']['fait_foi'] ?? false) === true
        && ($parCode['git-sha1']['statut'] ?? null) === 'AFFAIBLI',
    "git-sha1 est déclaré AFFAIBLI et fait néanmoins foi",
    "constat inconfortable et exact : le révoquer invaliderait le corpus entier",
);

$verifier(
    ($parCode['sha256']['statut'] ?? null) === 'ADMIS',
    "sha256 est déclaré ADMIS",
);

/* ------------------------------------------------------------------ INV-31 */
echo "\n  INV-31 — une empreinte sans algorithme nommé n'est pas une preuve\n";

$refuse = false;
try {
    Empreinte::calculer('md5-maison', 'contenu');
} catch (\InvalidArgumentException) {
    $refuse = true;
}
$verifier($refuse, "le service refuse de calculer pour un algorithme non déclaré");

$verifier(
    Empreinte::algorithmeProbable(str_repeat('a', 40)) === 'git-sha1'
        && Empreinte::algorithmeProbable(str_repeat('a', 64)) === 'sha256'
        && Empreinte::algorithmeProbable('trop-court') === null,
    "une déclaration nue est interprétée, et l'inconnu reste inconnu",
);

/* ------------------------------------------------------------ INV-35 / M-33 */
echo "\n  INV-35 — vérification par recalcul, non par recopie\n";

// Objet stable du corpus : le texte fondateur des sources, déclaré par sa
// feuille de statut depuis l'origine.
$objet = 'genesis-ii/sources/SOURCES-0001-hierarchie-authenticite-autorite-sources-gamad.md';
$lignes = $ctr10->verifier($objet);

$verifier(
    count($lignes) === 1 && $lignes[0]['verdict'] === 'CONCORDE',
    "SOURCES-0001 concorde avec l'empreinte que le corpus déclare",
    $lignes === [] ? 'objet non déclaré' : 'déclarant : ' . $lignes[0]['declaree']['declarant'],
);

// Recalcul indépendant, par ce test, sans passer par le service.
$attendue = is_file($corpus . '/' . $objet)
    ? Empreinte::calculerFichier('git-sha1', $corpus . '/' . $objet)
    : null;
$rendue = null;
foreach ($lignes[0]['calculee'] ?? [] as $c) {
    if ($c['algorithme'] === 'git-sha1') {
        $rendue = $c['valeur'];
    }
}
$verifier(
    $attendue !== null && $rendue === $attendue,
    "l'empreinte rendue par le service est bien celle du fichier sur disque",
    'recalcul indépendant : ' . (string) $attendue,
);

/* ------------------------------------------------------------------ INV-32 */
echo "\n  INV-32 — double conservation : ce que le service sait faire, et l'écart\n";

$emission = $ctr10->emettre($objet);
$algos = array_column($emission['empreintes'] ?? [], 'algorithme');
$verifier(
    count(array_unique($algos)) >= 2,
    "le service émet l'objet sous au moins deux algorithmes indépendants",
    'émis : ' . implode(', ', $algos),
);

$inv = $ctr10->inventaire();
$verifier(
    $inv['objets_declares'] === $inv['concordent'] + $inv['discordent'] + $inv['fichiers_absents'],
    "l'inventaire rend compte de chaque objet déclaré, sans en perdre aucun",
    sprintf(
        '%d déclarés = %d concordent + %d discordent + %d absents',
        $inv['objets_declares'],
        $inv['concordent'],
        $inv['discordent'],
        $inv['fichiers_absents'],
    ),
);

$verifier(
    $inv['double_conservation'] < $inv['objets_declares'],
    "l'écart de double conservation est chiffré, non masqué",
    sprintf(
        '%d objet(s) sur %d satisfont INV-32 — l\'écart est déclaré, il n\'est pas comblé',
        $inv['double_conservation'],
        $inv['objets_declares'],
    ),
);

/* -------------------------------------------------------------------- M-33 */
echo "\n  M-33 — les actes d'adoption sont nommés comme objets sans preuve\n";

$actes = glob($corpus . '/genesis-ii/registre/ADOPTION-*.md') ?: [];
$verifier(
    $inv['actes_sans_preuve'] === count($actes) && count($actes) > 0,
    "aucun acte d'adoption ne porte d'empreinte déclarée, et l'inventaire le dit",
    sprintf('%d acte(s) sur disque, %d sans preuve déclarée', count($actes), $inv['actes_sans_preuve']),
);

/* ------------------------------------------------------------------ INV-34 */
echo "\n  INV-34 — une attestation non signée le déclare dans son corps\n";

$attestation = $ctr10->attester($objet);
$verifier(
    $attestation !== null && $attestation['signee'] === false,
    "l'attestation porte signee: false, explicitement et non par omission",
);

$verifier(
    $attestation !== null
        && is_string($attestation['portee'])
        && str_contains($attestation['portee'], 'origine'),
    "l'attestation énonce elle-même qu'elle ne vaut pas preuve d'origine",
);

$verifier(
    $ctr10->attester('genesis-ii/nexiste-pas.md') === null,
    "un objet non déclaré ne reçoit aucune attestation",
);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-015 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
