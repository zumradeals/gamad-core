<?php

declare(strict_types=1);

/**
 * Contrôles statiques de sécurité et de cohérence de l'accueil public.
 * Exécution : php tests/Integration/accueil_public_configuration.php
 */
$racine = dirname(__DIR__, 2);
$configuration = require $racine . '/config/gamad_public.php';
$vue = file_get_contents($racine . '/resources/views/reseau/accueil.blade.php');
$pied = file_get_contents($racine . '/resources/views/reseau/pied-public.blade.php');
$routes = file_get_contents($racine . '/routes/web.php');

$verifier = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "ÉCHEC : {$message}\n");
        exit(1);
    }
};

$verifier(isset($configuration['statistics']) && is_array($configuration['statistics']), 'Statistiques configurables');
foreach ($configuration['statistics'] as $statistique) {
    $verifier(isset($statistique['label'], $statistique['visible']), 'Contrat minimal de statistique');
    $verifier(!$statistique['visible'] || (isset($statistique['value']) && is_numeric($statistique['value'])), 'Aucune statistique visible sans valeur numérique');
}
$verifier(isset($configuration['footer']['columns']) && is_array($configuration['footer']['columns']), 'Colonnes de footer configurables');
$verifier(str_contains($vue, "config('gamad_public.statistics'"), 'Vue alimentée par la configuration');
$verifier(str_contains($vue, 'is_numeric'), 'Filtrage des chiffres');
$verifier(str_contains($pied, "config('gamad_public.footer'"), 'Footer alimenté par la configuration');
$verifier(str_contains($pied, 'FILTER_VALIDATE_URL'), 'Validation des liens sociaux');
$verifier(str_contains($routes, "Route::view('/', 'reseau.accueil')"), 'Accueil public à la racine');
$verifier(!str_contains($vue, 'numéro GAMAD') && !str_contains($pied, 'numéro GAMAD'), 'Identifiant GAMAD non affiché');
echo "OK — contrats statiques de l'accueil public.\n";
