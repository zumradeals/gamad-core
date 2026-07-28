<?php

declare(strict_types=1);

/**
 * Amorçage commun : chargement des classes et localisation du corpus.
 * Le corpus (racine du dépôt gamad-core) est deux niveaux au-dessus du module,
 * sauf indication explicite par la variable CORPUS_PATH (utile en déploiement).
 */

require __DIR__ . '/src/GitBlob.php';
require __DIR__ . '/src/Db.php';
require __DIR__ . '/src/Schema.php';
require __DIR__ . '/src/Ingestion.php';
require __DIR__ . '/src/Ctr04.php';

if (!defined('REGN_CORPUS')) {
    $corpus = getenv('CORPUS_PATH');
    if (!is_string($corpus) || $corpus === '') {
        $corpus = dirname(__DIR__, 2); // core/registre-normes -> racine du dépôt
    }
    define('REGN_CORPUS', $corpus);
}
