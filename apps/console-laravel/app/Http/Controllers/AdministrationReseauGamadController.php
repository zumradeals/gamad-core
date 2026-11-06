<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\ContactsGamad;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Http\Response;

final class AdministrationReseauGamadController
{
    public function index(): Response
    {
        $registre = Magasin::ouvrir();
        $contacts = $registre->prepare("SELECT COUNT(*) FROM contact_reseau_gamad WHERE etat = 'new' AND cree_le >= ?");
        $contacts->execute([ContactsGamad::dateLimite()]);
        $rubriques = [
            [
                'titre' => 'Modération des contenus',
                'route' => 'reseau.moderation',
                'attente' => (int) $registre->query("SELECT COUNT(*) FROM signalement_reseau_gamad WHERE etat = 'ouvert'")->fetchColumn(),
            ],
            [
                'titre' => 'Vérification des entreprises',
                'route' => 'entreprises.revue',
                'attente' => (int) $registre->query("SELECT COUNT(*) FROM entreprise_gamad WHERE etat = 'pending'")->fetchColumn(),
            ],
            [
                'titre' => 'Messages de contact',
                'route' => 'reseau.contact.console',
                'attente' => (int) $contacts->fetchColumn(),
            ],
        ];

        return response()->view('reseau.administration', compact('rubriques'))->header('Cache-Control', 'private, no-store');
    }
}
