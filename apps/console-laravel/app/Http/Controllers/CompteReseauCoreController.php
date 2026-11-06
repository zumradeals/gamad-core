<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Comptes\LireCompteGamad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CompteReseauCoreController
{
    public function montrer(Request $request, LireCompteGamad $lecture): JsonResponse
    {
        $sujet = $request->attributes->get('gamad_entite');
        abort_unless(is_string($sujet) && $sujet !== '', 403);

        try {
            $compte = $lecture->executer($sujet);
        } catch (\PDOException $exception) {
            report($exception);

            return response()->json(['message' => 'Registre des comptes indisponible.'], 503);
        }

        if ($compte === null) {
            return response()->json(['message' => 'Compte GAMAD indisponible ou non autorisé.'], 403);
        }

        return response()->json(['compte' => $compte], headers: ['Cache-Control' => 'no-store']);
    }
}
