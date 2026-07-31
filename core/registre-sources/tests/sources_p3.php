<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-006 — résolution d'une source (CTR-15).
 *
 * Une capacité n'hérite pas de la garde d'une autre : les gardes de CTR-04
 * éprouvent le registre des normes, non celui des sources.
 *
 * Ce que le test vérifie :
 *   · INV-7  — une source se résout par sa référence canonique ; une référence
 *              inconnue rend null, jamais une source approchante ;
 *   · INV-8  — le rang restitué est fondé : soit un rang établi par l'index,
 *              soit INDETERMINE. Jamais une valeur inventée ;
 *   · INV-9  — l'authenticité ne se déduit pas de l'adoption : une source
 *              inscrite par un acte en vigueur reste AUTH-1 si l'index le
 *              déclare ainsi ;
 *   · INV-11 — la lignée distingue « source inconnue » de « source sans
 *              lignée » ; l'ignorance ne se déguise pas en fait.
 *
 * CONTRE-ÉPREUVE : exécuté sur un index amputé de ses sources, ce test DOIT
 * échouer. Un test qui ne peut pas échouer ne prouve rien ; la contre-épreuve
 * est exécutée à la fin du fichier.
 *
 * Exécution : php core/registre-sources/tests/sources_p3.php
 * Code de sortie : 0 si la garde passe, 1 sinon.
 */

use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreSources\Ctr15;

require __DIR__ . '/../../registre-normes/bootstrap.php';

// Base éphémère dédiée au test, indépendante de tout déploiement.
$fichier = sys_get_temp_dir() . '/regn-sources-p3-' . getmypid() . '.sqlite';
@unlink($fichier);
putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $fichier);

$pdo = Db::connect();
BaselineOperationnelle::standard()->reconstruire($pdo);
$ctr15 = new Ctr15($pdo);

$echecs = 0;
$verifier = function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]   ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — RÉSOLUTION D'UNE SOURCE (CAP-CORE-006 / CTR-15)\n\n";

/* ------------------------------------------------------------------ INV-7 */
echo "  INV-7 — identité canonique\n";

$sources = $ctr15->resoudreSource('SOURCES-0001');
$verifier(
    $sources !== null && $sources['reference'] === 'SOURCES-0001',
    "SOURCES-0001 se résout par sa référence canonique",
    $sources === null ? 'la source ne se résout pas' : 'titre : ' . $sources['titre'],
);

$verifier(
    $ctr15->resoudreSource('SRC-9999') === null,
    "une référence inconnue rend null, sans source approchante",
);

/* ------------------------------------------------------------------ INV-8 */
echo "\n  INV-8 — rang fondé, jamais inventé\n";

$rangs = $pdo->query('SELECT code FROM rang_normatif')->fetchAll(\PDO::FETCH_COLUMN);
$rang  = $sources['rang'] ?? null;
$verifier(
    $rang === 'INDETERMINE' || in_array($rang, $rangs, true),
    "le rang de SOURCES-0001 est fondé ou déclaré indéterminé",
    sprintf('rang rendu : %s — %d rang(s) présent(s) dans l’index', var_export($rang, true), count($rangs)),
);

$verifier(
    $rangs !== [],
    "les rangs normatifs sont des données de l'index, non des constantes du code",
    count($rangs) . ' rang(s) présent(s) dans l’index',
);

/* ------------------------------------------------------------------ INV-9 */
echo "\n  INV-9 — authenticité distincte de l'adoption\n";

// SRC-0007 est inscrite par un acte en vigueur et demeure néanmoins AUTH-1 :
// l'adoption n'authentifie pas le contenu.
$silsila = $ctr15->resoudreSource('SRC-0007');
$verifier(
    $silsila !== null && str_starts_with((string) $silsila['authenticite'], 'AUTH-1'),
    "SRC-0007, inscrite par un acte en vigueur, demeure AUTH-1",
    $silsila === null ? 'source absente' : 'authenticité : ' . $silsila['authenticite'],
);

$verifier(
    $silsila !== null && $silsila['reserve'] !== null && $silsila['reserve'] !== '',
    "la réserve inscrite sur SRC-0007 est restituée, non masquée",
);

$verifier(
    ($silsila['authenticite'] ?? null) !== ($sources['authenticite'] ?? null),
    "l'authenticité d'une source n'est pas alignée sur celle d'une autre",
    sprintf(
        'SRC-0007 : %s — SOURCES-0001 : %s',
        var_export($silsila['authenticite'] ?? null, true),
        var_export($sources['authenticite'] ?? null, true),
    ),
);

$verifier(
    ($ctr15->resoudreSource('SOURCES-0001')['authenticite'] ?? null) === 'AUTH-3',
    "SOURCES-0001 est déclarée AUTH-3 par l'index, sans présomption",
);

/* ----------------------------------------------------------------- INV-11 */
echo "\n  INV-11 — la lignée distingue l'ignorance du fait\n";

$lignee = $ctr15->resoudreLignee('SOURCES-0001');
$verifier(
    $lignee !== null && is_array($lignee['amont']) && is_array($lignee['aval']),
    "une source connue rend une lignée, fût-elle vide",
    $lignee === null ? 'lignée absente' : sprintf(
        '%d amont, %d aval — aucune supersession n’est à ce jour enregistrée',
        count($lignee['amont']),
        count($lignee['aval']),
    ),
);

$verifier(
    $ctr15->resoudreLignee('SRC-9999') === null,
    "une source inconnue rend null, et non une lignée vide",
);

/* ------------------------------------------------------- CONTRE-ÉPREUVE */
echo "\n  Contre-épreuve — la garde doit savoir échouer\n";

$pdo->exec("DELETE FROM source WHERE reference = 'SOURCES-0001'");
$verifier(
    $ctr15->resoudreSource('SOURCES-0001') === null,
    "une source retirée de l'index cesse d'être résolue",
);

@unlink($fichier);

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-006 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-006 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
