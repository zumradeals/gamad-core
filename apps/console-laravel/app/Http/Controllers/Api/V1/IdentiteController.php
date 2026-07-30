<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Identites\InscrireIdentite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inscription verticale protégée.
 *
 * Le sujet vient exclusivement de la session. CAP-CORE-004 est interrogée
 * avant toute écriture et son refus par défaut est appliqué physiquement.
 */
final class IdentiteController
{
    public function store(Request $request, InscrireIdentite $inscrire): JsonResponse
    {
        $donnees = $request->validate([
            'canal' => ['required', 'string', 'in:PRODUIT_RECONNU,ORGANISATION_RECONNUE,AUTORITE,CREATION_TECHNIQUE'],
            'type' => ['required', 'string', 'in:personne,organisation,produit,realm,agent,service'],
            'libelle' => ['required', 'string', 'max:256'],
            'classification' => ['nullable', 'string', 'in:PUBLIC_ECOSYSTEME,INTERNE,CONFIDENTIEL,RESTREINT,SECRET_CORE'],
            'provisoire' => ['nullable', 'boolean'],
        ]);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $inscrire->executer(
            $donnees,
            $acteur,
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }
}
