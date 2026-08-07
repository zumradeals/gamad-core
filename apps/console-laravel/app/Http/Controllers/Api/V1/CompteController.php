<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Comptes\CreerCompteGamad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compte GAMAD — façade d'inscription pour produits reconnus.
 *
 * Cette route est derrière gamad.api : le navigateur public ne peut donc pas
 * créer directement une identité souveraine. Le produit authentifié porte la
 * demande et reste soumis aux politiques d'autorisation du Core.
 */
final class CompteController
{
    public function store(Request $request, CreerCompteGamad $creer): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:256'],
            'type_identifiant' => ['required', 'string', 'in:EMAIL,TELEPHONE,USERNAME'],
            'identifiant' => ['required', 'string', 'max:256'],
            'mot_de_passe' => ['required', 'string', 'min:12', 'max:4096'],
        ]);

        $produit = (string) $request->attributes->get('gamad_entite', '');
        $execution = $creer->executer(
            $donnees,
            $produit,
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json(
            $execution['corps'],
            $execution['statut'],
            [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
