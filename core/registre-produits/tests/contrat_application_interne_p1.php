<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/ContratApplicationInterne.php';

use Gamad\RegistreProduits\ContratApplicationInterne;

$travel = [
    'produit_reference' => 'PRD-TEST-TRAVEL',
    'identifiant' => 'gamad.travel',
    'nom' => 'GAMAD Travel',
    'domaine_public' => 'gamadtravel.com',
    'route_console' => 'console.travel.index',
    'route_accueil' => 'travel.accueil',
    'capacites' => ['sejours', 'reservations'],
];
$couture = [
    'produit_reference' => 'PRD-TEST-COUTURE',
    'identifiant' => 'gamad.couture',
    'nom' => 'GAMAD Couture',
    'domaine_public' => 'gamadcouture.com',
    'route_console' => 'console.couture.index',
    'route_accueil' => 'couture.accueil',
    'capacites' => ['commandes'],
];

$attenduRefus = static function (callable $appel): void {
    try {
        $appel();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Une déclaration invalide a été acceptée');
};

ContratApplicationInterne::verifierUnicite([$travel, $couture]);
$attenduRefus(static fn () => ContratApplicationInterne::verifierUnicite([$travel, array_replace($couture, ['domaine_public' => 'GAMADTRAVEL.COM'])]));
$attenduRefus(static fn () => ContratApplicationInterne::verifierUnicite([$travel, array_replace($couture, ['identifiant' => 'gamad.travel'])]));
$attenduRefus(static fn () => ContratApplicationInterne::valider(array_replace($travel, ['domaine_public' => 'https://gamadtravel.com'])));
$attenduRefus(static fn () => ContratApplicationInterne::valider(array_replace($travel, ['domaine_public' => 'gamadtravel.com/other'])));
$attenduRefus(static fn () => ContratApplicationInterne::valider(array_replace($travel, ['route_console' => '../admin'])));
$attenduRefus(static fn () => ContratApplicationInterne::valider(array_replace($travel, ['capacites' => ['valid', '../invalid']])));
echo "OK — contrat applicatif interne : validation et collisions.\n";

foreach (['localhost', '127.0.0.1', 'foo.local', 'a.test', 'a.invalid', 'a.example', 'example.com', 'a.example.org', 'a.onion', '*.gamad.com', 'gamad.com:443', 'gamad.com/path', 'gamad.com..', 'a..com', 'xn--caf-dma.com', 'café.com', 'user@gamad.com'] as $domaine) {
    $attenduRefus(static fn () => ContratApplicationInterne::domaine($domaine));
}
if (ContratApplicationInterne::domaine(' GAMADTRAVEL.COM. ') !== 'gamadtravel.com') {
    throw new RuntimeException('Normalisation DNS incorrecte');
}
ContratApplicationInterne::verifierUnicite([
    array_replace($travel, ['domaine_public' => null]),
    array_replace($couture, ['domaine_public' => null]),
]);
echo "OK — domaines stricts, normalisation et applications sans domaine.\n";
