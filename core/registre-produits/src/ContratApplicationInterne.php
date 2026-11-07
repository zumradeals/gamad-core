<?php

declare(strict_types=1);

namespace Gamad\RegistreProduits;

use InvalidArgumentException;

/** Valeurs de l'extension CAP-CORE-011 ; aucune identité, session ou configuration DNS. */
final class ContratApplicationInterne
{
    public static function domaine(string $valeur): string
    {
        $domaine = strtolower(trim($valeur));
        if (str_ends_with($domaine, '.')) {
            $domaine = substr($domaine, 0, -1);
        }
        if (strlen($domaine) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $domaine)
            || filter_var($domaine, FILTER_VALIDATE_IP)
            || preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid|example|onion|arpa)$/D', $domaine)
            || preg_match('/(?:^|\.)example\.(?:com|net|org)$/D', $domaine)
            || preg_match('/(?:^|\.)xn--/', $domaine)) {
            throw new InvalidArgumentException('Domaine public invalide : DNS ASCII seul, sans port, chemin, joker, IP ou domaine réservé. IDN non pris en charge.');
        }

        return $domaine;
    }

    public static function valider(array $declaration): array
    {
        foreach (['produit_reference', 'identifiant', 'route_console', 'route_accueil'] as $cle) {
            if (!isset($declaration[$cle]) || !is_string($declaration[$cle]) || trim($declaration[$cle]) === '') {
                throw new InvalidArgumentException("Champ obligatoire absent : {$cle}");
            }
        }
        $reference = trim($declaration['produit_reference']);
        if (!preg_match('/^[A-Z0-9][A-Z0-9-]{0,63}$/D', $reference)) {
            throw new InvalidArgumentException('Référence produit invalide');
        }
        $identifiant = strtolower(trim($declaration['identifiant']));
        if (strlen($identifiant) > 48 || !preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $identifiant)) {
            throw new InvalidArgumentException('Identifiant applicatif invalide');
        }
        foreach (['route_console', 'route_accueil'] as $cle) {
            if (strlen($declaration[$cle]) > 120 || !preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $declaration[$cle])) {
                throw new InvalidArgumentException('Route nommée invalide');
            }
        }
        $domaine = $declaration['domaine_public'] ?? null;
        if ($domaine !== null && !is_string($domaine)) {
            throw new InvalidArgumentException('Domaine public invalide');
        }
        $capacites = $declaration['capacites'] ?? [];
        if (!is_array($capacites) || !array_is_list($capacites) || count($capacites) > 40) {
            throw new InvalidArgumentException('Les capacités doivent former une liste de 40 éléments maximum');
        }
        foreach ($capacites as $capacite) {
            if (!is_string($capacite) || strlen($capacite) > 48 || !preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $capacite)) {
                throw new InvalidArgumentException('Capacité invalide');
            }
        }

        return [
            'produit_reference' => $reference,
            'identifiant' => $identifiant,
            'domaine_public' => $domaine === null ? null : self::domaine($domaine),
            'route_console' => $declaration['route_console'],
            'route_accueil' => $declaration['route_accueil'],
            'capacites' => array_values(array_unique($capacites)),
        ];
    }

    public static function verifierUnicite(array $declarations): void
    {
        $vus = ['identifiant' => [], 'domaine_public' => [], 'produit_reference' => []];
        foreach ($declarations as $declaration) {
            $application = self::valider($declaration);
            foreach ($vus as $cle => $_) {
                $valeur = $application[$cle];
                if ($valeur !== null && isset($vus[$cle][$valeur])) {
                    throw new InvalidArgumentException("{$cle} déjà déclaré");
                }
                if ($valeur !== null) {
                    $vus[$cle][$valeur] = true;
                }
            }
        }
    }
}
