<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Comptes\CreerCompteGamad;
use App\Application\Comptes\LivrerVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compte GAMAD — façade d'inscription pour produits reconnus.
 *
 * Cette route est derrière gamad.api : le navigateur public ne peut donc pas
 * créer directement une identité souveraine. Le produit authentifié porte la
 * demande. Lorsqu'une vérification email/téléphone est requise, son code est
 * livré par le Core au canal humain et n'est jamais retourné au produit.
 */
final class CompteController
{
    public function store(
        Request $request,
        CreerCompteGamad $creer,
        LivrerVerification $livrer,
    ): JsonResponse {
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

        if ($execution['statut'] === 201
            && isset($execution['corps']['verification'])
            && is_array($execution['corps']['verification'])) {
            $verification = $execution['corps']['verification'];
            $code = (string) ($verification['code'] ?? '');
            $expireLe = (string) ($verification['expire_le'] ?? '');

            if ($code === '' || $expireLe === '') {
                return response()->json([
                    'erreur' => 'VERIFICATION_INCOMPLETE',
                    'message' => 'Le compte existe mais sa vérification n’a pas pu être livrée.',
                ], 503, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
            }

            $livraison = $livrer->executer(
                (string) $donnees['type_identifiant'],
                (string) $donnees['identifiant'],
                $code,
                $expireLe,
            );

            // Le code brut ne franchit jamais la frontière Core -> produit.
            unset($execution['corps']['verification']['code']);
            $execution['corps']['verification']['livraison'] = [
                'canal' => $livraison['canal'],
                'livree' => $livraison['livree'],
            ];

            if ($livraison['livree'] !== true) {
                return response()->json([
                    'erreur' => 'VERIFICATION_NON_LIVREE',
                    'message' => 'Le compte a été créé mais le code de vérification n’a pas pu être livré.',
                    'compte' => $execution['corps']['compte'] ?? null,
                    'verification' => $execution['corps']['verification'],
                    'motif' => $livraison['motif'] ?? 'LIVRAISON_INDISPONIBLE',
                ], 503, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
            }
        }

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
