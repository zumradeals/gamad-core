<?php

declare(strict_types=1);

/**
 * Contre-épreuve statique du mode dirigeant.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/console_dirigeant_p1.php
 */

$application = dirname(__DIR__, 2);
$layout = (string) file_get_contents($application.'/resources/views/layouts/console.blade.php');
$tableauDeBord = (string) file_get_contents($application.'/resources/views/tableau-de-bord.blade.php');
$politique = (string) file_get_contents($application.'/resources/views/politiques/show.blade.php');
$css = (string) file_get_contents($application.'/public/css/console-dirigeant.css');

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) {
        $echecs++;
    }
};

echo "INTÉGRATION — CONSOLE DIRIGEANT P1\n\n";

$verifier(
    str_contains($layout, 'Poste de commandement')
        && str_contains($layout, 'Personnes et identités')
        && str_contains($layout, 'Règles et autorisations')
        && str_contains($layout, 'Mots officiels')
        && str_contains($layout, 'Sauvegardes et continuité'),
    'la navigation utilise des libellés compréhensibles par un dirigeant',
);

$verifier(
    str_contains($tableauDeBord, 'ce qui demande votre attention')
        && str_contains($tableauDeBord, 'Services fondamentaux')
        && str_contains($tableauDeBord, 'Intégrité du système')
        && str_contains($tableauDeBord, 'Fonctions du Core')
        && str_contains($tableauDeBord, 'Afficher les contrôles techniques'),
    'le tableau de bord présente les décisions avant les détails techniques',
);

$verifier(
    str_contains($politique, 'Règle de fonctionnement')
        && str_contains($politique, 'À retenir')
        && str_contains($politique, 'Préparer une modification')
        && str_contains($politique, 'Afficher les détails techniques')
        && str_contains($politique, 'Examiner les conséquences d’un retrait'),
    'la fiche politique explique son rôle, les actions et leurs conséquences',
);

$verifier(
    ! str_contains($politique, '<h2 class="card__title">Retirer la politique</h2>')
        && ! str_contains($politique, '>Créer en BROUILLON<'),
    'les commandes techniques brutes ne dominent plus la page',
);

$verifier(
    str_contains($css, '.technical-panel')
        && str_contains($css, '.danger-panel')
        && str_contains($css, '.human-summary')
        && str_contains($css, '@media (max-width: 820px)'),
    'les composants du mode dirigeant sont définis et adaptés au mobile',
);

echo "\n";
if ($echecs === 0) {
    echo "Console dirigeant P1 : ÉTABLIE.\n";
    exit(0);
}

echo "Console dirigeant P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
