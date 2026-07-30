<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Security\PasskeyService;
use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PasskeySessionController
{
    public function options(Request $request, PasskeyService $passkeys): JsonResponse
    {
        $donnees = $request->validate([
            'entite' => ['required', 'string', 'max:64'],
        ]);

        try {
            return response()->json($passkeys->commencerAuthentification($donnees['entite']));
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'AUTHENTIFICATION_INDISPONIBLE',
                'message' => 'La cérémonie forte ne peut pas être préparée.',
            ], 503);
        }
    }

    public function store(Request $request, PasskeyService $passkeys): JsonResponse
    {
        $donnees = $request->validate([
            'entite' => ['required', 'string', 'max:64'],
            'ceremonie' => ['required', 'string', 'max:64'],
            'credential' => ['required', 'array'],
        ]);

        try {
            $session = $passkeys->terminerAuthentification(
                $donnees['ceremonie'],
                $donnees['credential'],
            );
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'AUTHENTIFICATION_REFUSEE',
                'message' => 'Passkey refusée.',
            ], 401);
        }

        if (! hash_equals($donnees['entite'], (string) $session['entite'])) {
            try {
                (new Ctr16(Magasin::connecter()))->revoquerSession((string) $session['session']);
            } catch (\Throwable) {
                return response()->json([
                    'erreur' => 'MAGASIN_ACCES_INDISPONIBLE',
                    'message' => 'La session discordante n’a pas pu être révoquée.',
                ], 503);
            }

            return response()->json([
                'erreur' => 'AUTHENTIFICATION_REFUSEE',
                'message' => 'Passkey refusée.',
            ], 401);
        }

        try {
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'SECURITE',
                'type' => 'OUVERTURE_SESSION_API_PASSKEY',
                'acteur' => $session['entite'],
                'action' => 'ouvrir une session API forte',
                'decision' => 'ACCEPTEE',
                'correlation_id' => $request->header('X-Correlation-ID'),
                'donnees' => [
                    'assurance' => $session['assurance'],
                    'passkey' => $session['passkey'],
                ],
            ]);
        } catch (\Throwable) {
            try {
                (new Ctr16(Magasin::connecter()))->revoquerSession((string) $session['session']);
            } catch (\Throwable) {
                // La readiness signalera les deux registres indisponibles.
            }

            return response()->json([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'Aucune session n’est conservée sans preuve opérationnelle.',
            ], 503);
        }

        return response()->json([
            'type' => 'Bearer',
            'jeton' => $session['session'],
            'entite' => $session['entite'],
            'assurance' => $session['assurance'],
            'expire_le' => $session['expire_le'],
            'preuve' => $preuve,
        ], 201, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
