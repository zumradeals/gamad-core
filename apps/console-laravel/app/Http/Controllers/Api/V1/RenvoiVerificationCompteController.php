<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Comptes\LivrerVerification;
use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RenvoiVerificationCompteController
{
    public function store(Request $request, LivrerVerification $livrer): JsonResponse
    {
        $donnees = $request->validate([
            'identifiant_reference' => ['required', 'string', 'max:64'],
            'destination' => ['required', 'string', 'max:256'],
        ]);

        $produit = trim((string) $request->attributes->get('gamad_entite', ''));
        $correlation = $request->attributes->get('gamad_correlation');

        try {
            $registre = new IdentifiantsResolution(IdentiteMagasin::connecter());
            $nouveau = $registre->renvoyerVerification(
                (string) $donnees['identifiant_reference'],
                (string) $donnees['destination'],
                $produit,
                [
                    'source' => 'CAP-CORE — renvoi vérification Compte GAMAD',
                    'preuve' => is_string($correlation) && $correlation !== '' ? $correlation : 'RENVOI-COMPTE-GAMAD',
                ],
            );
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'RENVOI_INDISPONIBLE',
                'message' => 'Un nouveau code ne peut pas être émis pour le moment.',
            ], 503);
        }

        if (isset($nouveau['refus'])) {
            $statut = in_array((string) $nouveau['refus'], ['RENVOI_TROP_RAPIDE', 'LIMITE_RENVOI_ATTEINTE'], true) ? 429 : 422;
            return response()->json([
                'erreur' => (string) $nouveau['refus'],
                'message' => 'Le renvoi du code a été refusé.',
                'reessayer_dans' => $nouveau['reessayer_dans'] ?? null,
            ], $statut);
        }

        $livraison = $livrer->executer(
            (string) $nouveau['type'],
            (string) $donnees['destination'],
            (string) $nouveau['code'],
            (string) $nouveau['expire_le'],
            [
                'produit' => $produit,
                'identifiant_reference' => (string) $donnees['identifiant_reference'],
                'verification_reference' => (string) $nouveau['reference'],
                'correlation' => $correlation,
            ],
        );

        if (($livraison['livree'] ?? false) !== true) {
            try {
                $registre->annulerVerification((string) $nouveau['reference']);
            } catch (\Throwable) {
            }
            return response()->json([
                'erreur' => 'LIVRAISON_VERIFICATION_ECHOUEE',
                'message' => 'Le nouveau code n’a pas pu être livré.',
                'canal' => $nouveau['type'],
            ], 503);
        }

        try {
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'IDENTITE',
                'type' => 'RENVOI_VERIFICATION_COMPTE',
                'acteur' => $produit,
                'action' => 'renvoyer un code de vérification de Compte GAMAD',
                'ressource' => (string) $donnees['identifiant_reference'],
                'decision' => 'EXECUTEE',
                'correlation_id' => $correlation,
                'donnees' => [
                    'verification_reference' => (string) $nouveau['reference'],
                    'canal' => (string) $nouveau['type'],
                ],
            ]);
        } catch (\Throwable) {
            try {
                $registre->annulerVerification((string) $nouveau['reference']);
            } catch (\Throwable) {
            }
            return response()->json([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'Le renvoi n’est pas conservé sans preuve opérationnelle.',
            ], 503);
        }

        return response()->json([
            'verification' => [
                'reference' => (string) $nouveau['reference'],
                'expire_le' => (string) $nouveau['expire_le'],
                'canal' => (string) $nouveau['type'],
                'livree' => true,
            ],
            'preuve' => $preuve,
        ], 201, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
