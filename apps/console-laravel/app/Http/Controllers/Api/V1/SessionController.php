<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Gamad\RegistreFederation\Federation;
use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController
{
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'entite' => ['nullable', 'string', 'max:64', 'required_without:identifiant'],
            'identifiant' => ['nullable', 'string', 'max:512', 'required_without:entite'],
            'type_identifiant' => ['nullable', 'string', 'in:EMAIL,TELEPHONE,USERNAME,EXTERNE'],
            'secret' => ['required', 'string', 'max:4096'],
        ]);

        $entite = isset($donnees['entite']) ? trim((string) $donnees['entite']) : '';
        $typeResolution = null;

        if ($entite === '' && isset($donnees['identifiant'])) {
            try {
                $resolution = (new IdentifiantsResolution(IdentiteMagasin::connecter()))->resoudrePourAuthentification(
                    (string) $donnees['identifiant'],
                    isset($donnees['type_identifiant']) ? (string) $donnees['type_identifiant'] : null,
                );
            } catch (\Throwable) {
                return response()->json([
                    'erreur' => 'REGISTRE_IDENTITES_INDISPONIBLE',
                    'message' => 'La session ne peut pas être établie.',
                ], 503);
            }

            if ($resolution !== null) {
                $entite = $resolution['identite'];
                $typeResolution = $resolution['type'];
            }
        }

        try {
            $ctr = new Ctr16(Magasin::connecter());
            $session = $entite !== ''
                ? $ctr->etablirSession($entite, $donnees['secret'])
                : null;
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
                'donnees' => [
                    'assurance' => $session['assurance'] ?? null,
                    'type_resolution' => $typeResolution,
                ],
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
            $magasin = Magasin::connecter();
            // Déconnexion globale : les jetons fédérés encore ouverts tombent
            // avec la session Core qui les a produits (CAP-CORE-022).
            $jetonsFederes = Federation::fermerJetonsDeSession(
                $magasin,
                Federation::empreinteSession($reference),
                'session Core fermée',
            );
            $revoquee = (new Ctr16($magasin))->revoquerSession($reference);
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
                'donnees' => ['jetons_federes_fermes' => $jetonsFederes],
            ]);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'La session a été révoquée, mais sa preuve n’a pas pu être produite.',
            ], 503);
        }

        return response()->json([
            'revoquee' => $revoquee,
            'jetons_federes_fermes' => $jetonsFederes,
            'preuve' => $preuve,
        ]);
    }
}
