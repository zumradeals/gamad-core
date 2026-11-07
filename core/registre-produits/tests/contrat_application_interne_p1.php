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
    'capacites' => ['sejours', 'reservations'],
];
$couture = [
    'produit_reference' => 'PRD-TEST-COUTURE',
    'identifiant' => 'gamad.couture',
    'nom' => 'GAMAD Couture',
    'domaine_public' => 'gamadcouture.com',
    'route_console' => 'console.couture.index',
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
