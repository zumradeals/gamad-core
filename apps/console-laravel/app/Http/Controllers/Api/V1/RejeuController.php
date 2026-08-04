<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Evenements\AccesEvenements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rejeu borné et gouverné de CAP-CORE-014 (partie 4 §7).
 *
 * L'exécution effective d'un rejeu validé reste une opération de fond, hors
 * de cette API (comme la publication le fait via `core:evenements:publier`) :
 * elle n'est pas listée dans la fiche partie 4 §7, dont les routes exposées
 * sont demande / lecture / validation / annulation.
 */
final class RejeuController
{
    public function index(Request $request, AccesEvenements $acces): JsonResponse
    {
        $abonnement = $request->query('abonnement');
        $execution = $acces->listerRejeux($abonnement === null ? null : (string) $abonnement, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesEvenements $acces): JsonResponse
    {
        $donnees = $request->validate([
            'abonnement_reference' => ['required', 'string', 'max:64'],
            'motif' => ['required', 'string', 'max:500'],
            'sequence_debut' => ['nullable', 'integer', 'min:0'],
            'sequence_fin' => ['nullable', 'integer', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'max:128'],
        ]);
        $abonnement = $donnees['abonnement_reference'];
        unset($donnees['abonnement_reference']);
        $execution = $acces->demanderRejeu($abonnement, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreRejeu($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function validation(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->validerRejeu($reference, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function annulation(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->annulerRejeu($reference, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
