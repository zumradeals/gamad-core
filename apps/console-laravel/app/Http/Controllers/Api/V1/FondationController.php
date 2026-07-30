<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\EtatFondation;
use Illuminate\Http\JsonResponse;

final class FondationController
{
    public function live(): JsonResponse
    {
        return response()->json([
            'statut' => 'VIVANT',
            'service' => 'gamad-core',
            'version_api' => 'v1',
            'observe_le' => gmdate('c'),
        ]);
    }

    public function ready(EtatFondation $etat): JsonResponse
    {
        $resultat = $etat->inspecter();

        return response()->json($resultat, $resultat['pret'] ? 200 : 503);
    }
}
