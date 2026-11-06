<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Comptes\ParcoursInscriptionGamad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RenvoiVerificationCompteController
{
    public function store(Request $request, ParcoursInscriptionGamad $parcours): JsonResponse
    {
        $donnees = $request->validate([
            'identifiant_reference' => ['required', 'string', 'max:64'],
            'destination' => ['required', 'string', 'max:256'],
        ]);

        $resultat = $parcours->renvoyer(
            $donnees,
            trim((string) $request->attributes->get('gamad_entite', '')),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($resultat['corps'], $resultat['statut'], $resultat['entetes']);
    }
}
