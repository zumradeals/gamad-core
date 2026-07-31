<?php

declare(strict_types=1);

/**
 * Amorçage commun des gardes autonomes du Core.
 *
 * Charge les classes du registre des normes sans autoloader Composer, et
 * localise la racine du dépôt pour les gardes qui inspectent le code livré.
 * Aucun corpus documentaire n'est requis ni recherché.
 */

require __DIR__ . '/src/Db.php';
require __DIR__ . '/src/Schema.php';
require __DIR__ . '/src/BaselineOperationnelle.php';
require __DIR__ . '/../registre-sources/src/Ctr15.php';
require __DIR__ . '/src/Ctr04.php';

if (!defined('GAMAD_RACINE')) {
    define('GAMAD_RACINE', dirname(__DIR__, 2)); // core/registre-normes -> racine du dépôt
}
