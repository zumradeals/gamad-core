<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Comptes\ParcoursInscriptionGamad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerificationCompteController
{
    public function store(Request $request, ParcoursInscriptionGamad $parcours): JsonResponse
    {
        $donnees = $request->validate([
            'identite' => ['required', 'string', 'max:64'],
            'identifiant_reference' => ['required', 'string', 'max:64'],
            'verification_reference' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $resultat = $parcours->verifier(
            $donnees,
            trim((string) $request->attributes->get('gamad_entite', '')),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($resultat['corps'], $resultat['statut'], $resultat['entetes']);
    }
}
