<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Unification\ResoudreCompteReseauCore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Projection de compte Réseau : la session CTR-16 est l'unique autorité.
 * Aucun accès à la console administrative n'est conféré.
 */
final class CompteReseauCoreController
{
    public function montrer(Request $request): JsonResponse
    {
        $compte = ResoudreCompteReseauCore::resoudre($request);

        if ($compte === null) {
            return response()->json(['message' => 'Compte Réseau indisponible ou non autorisé.'], 403);
        }

        return response()->json([
            'compte' => [
                'user_id' => $compte->userId,
                'identite_reference' => $compte->identiteReference,
            ],
        ]);
    }
}
