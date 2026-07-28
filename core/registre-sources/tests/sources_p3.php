<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-006 — résolution et authenticité d'une source (CTR-09).
 *
 * Garde de comportement propre à cette capacité, au sens de la doctrine
 * arrêtée par ADOPTION-0035, Art. 2.2 : une capacité n'hérite pas de la preuve
 * d'une autre, et ne peut atteindre P3 que par une garde éprouvant son propre
 * contrat. Les gardes de CTR-04 éprouvent le registre des normes, non celui
 * des sources ; elles ne prouvent rien d'ici.
 *
 * Ce que le test vérifie :
 *   · INV-7  — une source se résout par sa référence canonique ; une référence
 *              inconnue rend null, jamais une source approchante ;
 *   · INV-8  — le rang restitué est fondé : soit un rang que SOURCES-0001
 *              établit, soit INDETERMINE. Jamais une valeur inventée ;
 *   · INV-9  — l'authenticité ne se déduit pas de l'adoption : une source
 *              adoptée par un acte en vigueur reste AUTH-1 si le corpus le
 *              déclare ainsi. Et l'invérifiable est déclaré invérifiable,
 *              jamais présumé concordant ;
 *   · INV-1  — l'empreinte réelle est RECALCULÉE depuis le fichier et comparée
 *              à l'empreinte déclarée, jamais recopiée depuis l'index ;
 *   · INV-11 — la lignée distingue « source inconnue » de « source sans
 *              lignée » ; l'ignorance ne se déguise pas en fait.
 *
 * CONTRE-ÉPREUVE OBLIGATOIRE (ADOPTION-0032, Art. 3) : exécuté contre un corpus
 * dont le niveau d'authenticité de SOURCES-0001 a été délibérément abaissé au
 * registre des sources, ce test DOIT échouer. Un test qui ne peut pas échouer
 * ne prouve rien. La falsification s'opère sur COPIE HORS DÉPÔT, en pointant
 * la variable d'environnement CORPUS_PATH sur cette copie.
 *
 * Exécution :             php core/registre-sources/tests/sources_p3.php
 * Contre-épreuve : CORPUS_PATH=/chemin/copie/altérée php core/registre-sources/tests/sources_p3.php
 * Code de sortie : 0 si la preuve passe, 1 sinon.
 */

use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\GitBlob;
use Gamad\RegistreNormes\Ingestion;
use Gamad\RegistreSources\Ctr09;

require __DIR__ . '/../../registre-normes/bootstrap.php';

// Base éphémère dédiée au test, indépendante de tout déploiement.
$fichier = sys_get_temp_dir() . '/regn-sources-p3-' . getmypid() . '.sqlite';
@unlink($fichier);
putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $fichier);

$pdo = Db::connect();
(new Ingestion($pdo, REGN_CORPUS))->executer();
$ctr09 = new Ctr09($pdo, REGN_CORPUS);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — RÉSOLUTION ET AUTHENTICITÉ D'UNE SOURCE (CAP-CORE-006 / CTR-09)\n\n";

/* ------------------------------------------------------------------ INV-7 */
echo "  INV-7 — identité canonique\n";

$sources = $ctr09->resoudreSource('SOURCES-0001');
$verifier(
    $sources !== null && $sources['reference'] === 'SOURCES-0001',
    "SOURCES-0001 se résout par sa référence canonique",
    $sources === null ? 'la source ne se résout pas' : 'titre : ' . $sources['titre'],
);

$verifier(
    $ctr09->resoudreSource('SRC-9999') === null,
    "une référence inconnue rend null, sans source approchante",
);

/* ------------------------------------------------------------------ INV-8 */
echo "\n  INV-8 — rang fondé, jamais inventé\n";

$rangs = $pdo->query('SELECT code FROM rang_normatif')->fetchAll(\PDO::FETCH_COLUMN);
$rang  = $sources['rang'] ?? null;
$verifier(
    $rang === 'INDETERMINE' || in_array($rang, $rangs, true),
    "le rang de SOURCES-0001 est fondé ou déclaré indéterminé",
    sprintf('rang rendu : %s — %d rang(s) établi(s) par le corpus', var_export($rang, true), count($rangs)),
);

$verifier(
    $rangs !== [],
    "les rangs normatifs sont dérivés du corpus, non codés en dur",
    count($rangs) . ' rang(s) dérivé(s) de SOURCES-0001',
);

/* ------------------------------------------------------------------ INV-9 */
echo "\n  INV-9 — authenticité distincte de l'adoption\n";

// SRC-0007 (silsila GAMAD ZUMARA) est inscrite par un acte EN VIGUEUR et
// demeure néanmoins AUTH-1 : l'adoption n'authentifie pas le contenu.
$silsila = $ctr09->resoudreSource('SRC-0007');
$verifier(
    $silsila !== null && str_starts_with((string) $silsila['authenticite'], 'AUTH-1'),
    "SRC-0007, inscrite par un acte en vigueur, demeure AUTH-1",
    $silsila === null ? 'source absente' : 'authenticité : ' . $silsila['authenticite'],
);

$verifier(
    $silsila !== null && $silsila['reserve'] !== null && $silsila['reserve'] !== '',
    "la réserve inscrite sur SRC-0007 est restituée, non masquée",
);

$auth = $ctr09->verifierAuthenticite('SRC-0007');
$verifier(
    $auth !== null && $auth['verifiable'] === false && $auth['concorde'] === null,
    "une source non portée en fichier est déclarée invérifiable, non concordante",
    $auth === null ? 'source absente' : 'motif : ' . (string) $auth['motif'],
);

/* ------------------------------------------------------------------ INV-1 */
echo "\n  INV-1 — empreinte recalculée, jamais recopiée\n";

$authSources = $ctr09->verifierAuthenticite('SOURCES-0001');
$verifier(
    $authSources !== null && $authSources['verifiable'] === true && $authSources['concorde'] === true,
    "l'empreinte de SOURCES-0001 concorde avec celle que le corpus déclare",
    $authSources === null ? 'source absente' : 'empreinte : ' . (string) $authSources['empreinte_reelle'],
);

// L'empreinte rendue doit être celle du fichier réel, recalculée hors index.
$attendue = is_file(REGN_CORPUS . '/' . (string) ($authSources['chemin'] ?? ''))
    ? GitBlob::hashFile(REGN_CORPUS . '/' . (string) $authSources['chemin'])
    : null;
$verifier(
    $attendue !== null && ($authSources['empreinte_reelle'] ?? null) === $attendue,
    "l'empreinte rendue est bien celle du fichier sur disque",
);

// Le niveau d'authenticité déclaré doit être celui du corpus, non une présomption.
$verifier(
    ($authSources['authenticite'] ?? null) === 'AUTH-3',
    "SOURCES-0001 est déclarée AUTH-3 par le corpus",
    'authenticité rendue : ' . var_export($authSources['authenticite'] ?? null, true),
);

/* ----------------------------------------------------------------- INV-11 */
echo "\n  INV-11 — la lignée distingue l'ignorance du fait\n";

$lignee = $ctr09->resoudreLignee('SOURCES-0001');
$verifier(
    $lignee !== null && is_array($lignee['amont']) && is_array($lignee['aval']),
    "une source connue rend une lignée, fût-elle vide",
    $lignee === null ? 'lignée absente' : sprintf(
        '%d amont, %d aval — le corpus ne déclare à ce jour aucune supersession',
        count($lignee['amont']),
        count($lignee['aval']),
    ),
);

$verifier(
    $ctr09->resoudreLignee('SRC-9999') === null,
    "une source inconnue rend null, et non une lignée vide",
);

@unlink($fichier);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-006 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
