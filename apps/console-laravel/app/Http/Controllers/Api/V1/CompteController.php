<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Comptes\ParcoursInscriptionGamad;
use App\Application\Comptes\CreerCompteGamad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CompteController
{
    public function store(Request $request, ParcoursInscriptionGamad $parcours): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:256'],
            'type_identifiant' => ['required', 'string', 'in:EMAIL,TELEPHONE,USERNAME'],
            'identifiant' => ['required', 'string', 'max:256'],
            'mot_de_passe' => ['required', 'string', 'min:'.CreerCompteGamad::LONGUEUR_MINIMALE_MOT_DE_PASSE, 'max:4096'],
        ]);

        $resultat = $parcours->inscrire(
            $donnees,
            trim((string) $request->attributes->get('gamad_entite', '')),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($resultat['corps'], $resultat['statut'], $resultat['entetes']);
    }
}
