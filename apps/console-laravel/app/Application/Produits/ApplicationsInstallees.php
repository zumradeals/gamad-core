<?php

declare(strict_types=1);

namespace App\Application\Produits;

/** Adaptateurs livrés par le code, pas un registre de produits ni une habilitation. */
final class ApplicationsInstallees
{
    public const CATALOGUE = [
        'gamad.reseau' => [
            'route_accueil' => 'reseau.accueil',
            'route_console' => 'reseau.administration',
            'capacites' => [
                'administration' => 'reseau.administration',
                'moderation' => 'reseau.moderation',
                'entreprises' => 'entreprises.revue',
                'contacts' => 'reseau.contact.console',
                'parametres' => 'reseau.parametres',
            ],
        ],
    ];

    public static function accepte(array $declaration): bool
    {
        $contrat = self::CATALOGUE[$declaration['identifiant'] ?? ''] ?? null;

        return $contrat !== null
            && ($declaration['route_accueil'] ?? null) === $contrat['route_accueil']
            && ($declaration['route_console'] ?? null) === $contrat['route_console']
            && is_array($declaration['capacites'] ?? null)
            && array_diff($declaration['capacites'], array_keys($contrat['capacites'])) === [];
    }

    /** CAP-CORE-004 ne filtre pas la ressource : l'action porte le contexte explicitement. */
    public static function action(string $identifiant, string $capacite): string
    {
        if (!isset(self::CATALOGUE[$identifiant]['capacites'][$capacite])) {
            throw new \InvalidArgumentException('Capacité applicative non installée');
        }
        $action = 'application '.$identifiant.' '.$capacite;
        if (strlen($action) > 64) {
            throw new \LogicException('Action trop longue pour CAP-CORE-004');
        }

        return $action;
    }
}
