<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Secrets\AccesSecrets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Validation et exécution d'un plan de rotation (CAP-CORE-016, partie 4 §3). */
final class RotationSecretController
{
    public function validation(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $execution = $acces->validerRotation($reference, [], (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function execution(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'etape_reference' => ['required', 'string', 'max:128'],
            'reussie' => ['required', 'boolean'],
            'resultat_code' => ['nullable', 'string', 'max:64'],
            'resume' => ['nullable', 'array'],
            'cloturer_en_echec' => ['nullable', 'boolean'],
        ]);
        $etape = (string) $donnees['etape_reference'];
        unset($donnees['etape_reference']);
        $execution = $acces->executerEtapeRotation($reference, $etape, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
