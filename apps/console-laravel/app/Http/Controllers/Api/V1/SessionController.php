<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController
{
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'entite' => ['required', 'string', 'max:64'],
            'secret' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $ctr = new Ctr16(Magasin::connecter());
            $session = $ctr->etablirSession($donnees['entite'], $donnees['secret']);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'MAGASIN_ACCES_INDISPONIBLE',
                'message' => 'La session ne peut pas être établie.',
            ], 503);
        }

        try {
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'SECURITE',
                'type' => 'OUVERTURE_SESSION_API',
                'acteur' => $session['entite'] ?? null,
                'action' => 'ouvrir une session API',
                'decision' => $session === null ? 'REFUSEE' : 'ACCEPTEE',
                'motif' => $session === null ? 'identifiant ou secret refusé' : null,
                'correlation_id' => $request->header('X-Correlation-ID'),
                'donnees' => ['assurance' => $session['assurance'] ?? null],
            ]);
        } catch (\Throwable) {
            if ($session !== null) {
                $ctr->revoquerSession((string) $session['session']);
            }

            return response()->json([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'Aucune session n’est conservée sans preuve opérationnelle.',
            ], 503);
        }

        if ($session === null) {
            return response()->json([
                'erreur' => 'AUTHENTIFICATION_REFUSEE',
                'message' => 'Identifiant ou secret refusé.',
                'preuve' => $preuve,
            ], 401);
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

    public function destroy(Request $request): JsonResponse
    {
        $reference = (string) $request->attributes->get('gamad_session', '');
        $acteur = (string) $request->attributes->get('gamad_entite', '');
        try {
            $revoquee = (new Ctr16(Magasin::connecter()))->revoquerSession($reference);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'MAGASIN_ACCES_INDISPONIBLE',
                'message' => 'La session n’a pas pu être révoquée.',
            ], 503);
        }

        try {
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'SECURITE',
                'type' => 'FERMETURE_SESSION_API',
                'acteur' => $acteur,
                'action' => 'fermer la session API courante',
                'decision' => $revoquee ? 'EXECUTEE' : 'SANS_EFFET',
                'correlation_id' => $request->attributes->get('gamad_correlation'),
            ]);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'La session a été révoquée, mais sa preuve n’a pas pu être produite.',
            ], 503);
        }

        return response()->json(['revoquee' => $revoquee, 'preuve' => $preuve]);
    }
}
