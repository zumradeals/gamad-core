<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Federation\AccesSatellites;
use Gamad\RegistreFederation\PolitiqueFederation;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fédération des satellites sous session (CAP-CORE-022).
 *
 * Le sujet vient exclusivement de la session. Ouvrir un accès pour autrui n'est
 * possible qu'à l'autorité d'inscription, et la vérification d'un jeton n'est
 * ouverte qu'au satellite auquel il est destiné.
 */
final class FederationController
{
    public function index(Request $request, AccesSatellites $acces): JsonResponse
    {
        $execution = $acces->inventaire((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function ouvrir(Request $request, AccesSatellites $acces, string $produit): JsonResponse
    {
        $donnees = $request->validate([
            'identite' => ['nullable', 'string', 'max:64'],
            'relation_type' => ['nullable', 'string', 'in:' . implode(',', PolitiqueInscription::RELATIONS_PRODUIT)],
            'sujet_local_opaque' => ['nullable', 'string', 'max:256'],
            'duree' => [
                'nullable',
                'integer',
                'min:' . PolitiqueFederation::DUREE_MINIMALE,
                'max:' . PolitiqueFederation::DUREE_MAXIMALE,
            ],
        ]);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->ouvrir(
            (string) ($donnees['identite'] ?? $acteur),
            $produit,
            $acteur,
            (string) $request->attributes->get('gamad_session'),
            $donnees,
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut'], [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }

    public function verifier(Request $request, AccesSatellites $acces, string $produit): JsonResponse
    {
        $donnees = $request->validate([
            'jeton' => ['required', 'string', 'max:256'],
        ]);

        $execution = $acces->verifier(
            $donnees['jeton'],
            $produit,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function revoquer(Request $request, AccesSatellites $acces, string $produit): JsonResponse
    {
        $donnees = $request->validate([
            'identite' => ['nullable', 'string', 'max:64'],
        ]);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->revoquer(
            (string) ($donnees['identite'] ?? $acteur),
            $produit,
            $acteur,
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }
}
