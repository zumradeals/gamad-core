<?php

declare(strict_types=1);

namespace Gamad\RegistreProduits;

use InvalidArgumentException;

/**
 * Contrat pur des applications intégrées : ne crée ni produit, ni compte,
 * ni session, ni configuration DNS. Le registre CAP-CORE-011 reste souverain.
 */
final class ContratApplicationInterne
{
    public static function valider(array $declaration): array
    {
        foreach (['produit_reference', 'identifiant', 'nom', 'domaine_public', 'route_console'] as $cle) {
            if (!isset($declaration[$cle]) || !is_string($declaration[$cle]) || trim($declaration[$cle]) === '') {
                throw new InvalidArgumentException("Champ obligatoire absent : {$cle}");
            }
        }

        $identifiant = strtolower(trim($declaration['identifiant']));
        if (!preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $identifiant)) {
            throw new InvalidArgumentException('Identifiant applicatif invalide');
        }

        $domaine = strtolower(rtrim(trim($declaration['domaine_public']), '.'));
        if (str_contains($domaine, '/') || str_contains($domaine, ':') || !filter_var($domaine, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException('Domaine public invalide : nom DNS seul requis');
        }

        $route = trim($declaration['route_console']);
        if (!preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $route)) {
            throw new InvalidArgumentException('Route console invalide');
        }

        $capacites = $declaration['capacites'] ?? [];
        if (!is_array($capacites) || !array_is_list($capacites)) {
            throw new InvalidArgumentException('Les capacités doivent former une liste');
        }
        foreach ($capacites as $capacite) {
            if (!is_string($capacite) || !preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $capacite)) {
                throw new InvalidArgumentException('Capacité invalide');
            }
        }

        return [
            'produit_reference' => trim($declaration['produit_reference']),
            'identifiant' => $identifiant,
            'nom' => trim($declaration['nom']),
            'domaine_public' => $domaine,
            'route_console' => $route,
            'capacites' => array_values(array_unique($capacites)),
        ];
    }

    public static function verifierUnicite(array $declarations): void
    {
        $identifiants = [];
        $domaines = [];
        foreach ($declarations as $declaration) {
            $application = self::valider($declaration);
            if (isset($identifiants[$application['identifiant']])) {
                throw new InvalidArgumentException('Identifiant applicatif déjà déclaré');
            }
            if (isset($domaines[$application['domaine_public']])) {
                throw new InvalidArgumentException('Domaine public déjà déclaré');
            }
            $identifiants[$application['identifiant']] = true;
            $domaines[$application['domaine_public']] = true;
        }
    }
}
