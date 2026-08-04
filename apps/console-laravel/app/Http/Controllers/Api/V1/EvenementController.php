<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Evenements\AccesEvenements;
use Gamad\JournalEvenements\PolitiqueEvenements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Publication et lecture du journal central des événements (CAP-CORE-014,
 * partie 4 §3-4).
 *
 * Le sujet vient exclusivement de la session ; les champs de gouvernance
 * (politique, producteur au sens de l'écriture, preuve) viennent de la
 * décision CAP-CORE-004 et de la preuve CAP-CORE-013 établies par
 * `AccesEvenements`, jamais du corps de la requête.
 */
final class EvenementController
{
    public function publier(Request $request, AccesEvenements $acces): JsonResponse
    {
        $donnees = $request->validate([
            'type_evenement' => ['required', 'string', 'max:128'],
            'contrat_reference' => ['required', 'string', 'max:64'],
            'contrat_version' => ['required', 'string', 'max:32'],
            'producteur_capacite_reference' => ['nullable', 'string', 'max:64'],
            'producteur_produit_reference' => ['nullable', 'string', 'max:64'],
            'source_reference' => ['required', 'string', 'max:64'],
            'realm_reference' => ['required', 'string', 'max:64'],
            'finalite_reference' => ['required', 'string', 'max:255'],
            'sujet_type' => ['nullable', 'string', 'max:64'],
            'sujet_reference' => ['nullable', 'string', 'max:64'],
            'correlation_id' => ['required', 'string', 'max:64'],
            'causation_reference' => ['nullable', 'string', 'max:64'],
            'idempotence_reference' => ['required', 'string', 'max:128'],
            'survenu_le' => ['required', 'date'],
            'classification' => ['required', 'string', 'in:' . implode(',', PolitiqueEvenements::CLASSIFICATIONS)],
            'charge' => ['nullable', 'array'],
            'charge_empreinte' => ['nullable', 'string'],
        ]);
        $execution = $acces->publier($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function resoudrePublication(Request $request, AccesEvenements $acces, string $producteur, string $idempotence): JsonResponse
    {
        $execution = $acces->resoudrePublication($producteur, $idempotence, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function index(Request $request, AccesEvenements $acces): JsonResponse
    {
        $filtres = array_filter([
            'type_evenement' => $request->query('type'),
            'contrat_reference' => $request->query('contrat'),
            'producteur_reference' => $request->query('producteur'),
            'realm_reference' => $request->query('realm'),
            'sujet_reference' => $request->query('sujet'),
            'correlation_id' => $request->query('correlation'),
            'sequence_debut' => $request->query('sequence_debut'),
            'sequence_fin' => $request->query('sequence_fin'),
        ], static fn (mixed $v): bool => $v !== null);
        $limite = max(1, min((int) $request->query('limite', 50), PolitiqueEvenements::TAILLE_LOT_MAX));
        $decalage = max(0, (int) $request->query('decalage', 0));
        $execution = $acces->lister($filtres, $limite, $decalage, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function charge(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreCharge($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
