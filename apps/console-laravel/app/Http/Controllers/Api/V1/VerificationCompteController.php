<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Validation de la preuve de possession d'un email ou d'un téléphone.
 *
 * La route reste derrière gamad.api : seul le produit qui a créé le défi peut
 * le consommer. Le navigateur public passe donc toujours par son produit.
 */
final class VerificationCompteController
{
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'identite' => ['required', 'string', 'max:64'],
            'identifiant_reference' => ['required', 'string', 'max:64'],
            'verification_reference' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $produit = trim((string) $request->attributes->get('gamad_entite', ''));
        try {
            $pdo = IdentiteMagasin::connecter();
            $st = $pdo->prepare(
                'SELECT producteur FROM verification_identifiant WHERE reference = ? AND identifiant_reference = ? LIMIT 1'
            );
            $st->execute([
                (string) $donnees['verification_reference'],
                (string) $donnees['identifiant_reference'],
            ]);
            $producteur = $st->fetchColumn();
            if (!is_string($producteur) || $producteur === '' || !hash_equals($producteur, $produit)) {
                return response()->json([
                    'erreur' => 'VERIFICATION_NON_AUTORISEE',
                    'message' => 'Ce produit ne peut pas consommer cette vérification.',
                ], 403);
            }

            $resultat = (new IdentifiantsResolution($pdo))->verifierPossession(
                (string) $donnees['identite'],
                (string) $donnees['identifiant_reference'],
                (string) $donnees['verification_reference'],
                (string) $donnees['code'],
            );
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'VERIFICATION_INDISPONIBLE',
                'message' => 'La vérification ne peut pas être traitée.',
            ], 503);
        }

        $acceptee = !isset($resultat['refus']);
        try {
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'IDENTITE',
                'type' => 'VERIFICATION_IDENTIFIANT_COMPTE',
                'acteur' => $produit,
                'action' => 'vérifier la possession d’un identifiant de Compte GAMAD',
                'ressource' => (string) $donnees['identifiant_reference'],
                'decision' => $acceptee ? 'EXECUTEE' : 'REFUSEE',
                'motif' => $resultat['refus'] ?? null,
                'correlation_id' => $request->attributes->get('gamad_correlation'),
                'donnees' => [
                    'verification_reference' => (string) $donnees['verification_reference'],
                ],
            ]);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'La preuve opérationnelle de vérification n’a pas pu être produite.',
            ], 503);
        }

        if (!$acceptee) {
            return response()->json([
                'erreur' => (string) $resultat['refus'],
                'message' => 'Le code de vérification a été refusé.',
                'preuve' => $preuve,
            ], 422);
        }

        return response()->json([
            'identifiant' => $resultat,
            'preuve' => $preuve,
        ], 200, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
