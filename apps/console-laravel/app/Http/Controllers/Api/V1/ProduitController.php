<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Produits\AccesProduits;
use Gamad\RegistreProduits\PolitiqueProduits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registre des produits sous session (CAP-CORE-011).
 *
 * Le sujet vient exclusivement de la session ; les champs de gouvernance
 * (politique, producteur, source, preuve) ne sont jamais acceptés depuis la
 * requête — ils viennent de la décision CAP-CORE-004 et de la preuve
 * CAP-CORE-013 établies par `AccesProduits`.
 */
final class ProduitController
{
    public function show(Request $request, AccesProduits $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesProduits $acces): JsonResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'identite_reference' => ['required', 'string', 'max:64'],
            'nom_canonique' => ['required', 'string', 'max:255'],
            'nom_affichage' => ['required', 'string', 'max:255'],
            'type_produit' => ['required', 'string', 'in:' . implode(',', PolitiqueProduits::TYPES_PRODUIT)],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
        ]);

        $execution = $acces->inscrire(
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function update(Request $request, AccesProduits $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'nom_canonique' => ['sometimes', 'string', 'max:255'],
            'nom_affichage' => ['sometimes', 'string', 'max:255'],
            'type_produit' => ['sometimes', 'string', 'in:' . implode(',', PolitiqueProduits::TYPES_PRODUIT)],
            'proprietaire_reference' => ['sometimes', 'string', 'max:64'],
            'federation_autorisee' => ['sometimes', 'boolean'],
        ]);

        $execution = $acces->modifier(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activer(Request $request, AccesProduits $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->activer(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspendre(Request $request, AccesProduits $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->suspendre(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function retirer(Request $request, AccesProduits $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->retirer(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function environnements(Request $request, AccesProduits $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['environnements' => $execution['corps']['environnements']], 200);
    }

    public function declarerEnvironnement(Request $request, AccesProduits $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'environnement' => ['required', 'string', 'in:' . implode(',', PolitiqueProduits::ENVIRONNEMENTS)],
            'api_base_url' => ['required', 'string', 'max:2048'],
            'health_url' => ['nullable', 'string', 'max:2048'],
            'audience_federation' => ['required', 'string', 'max:64'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $execution = $acces->declarerEnvironnement(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function fermerEnvironnement(
        Request $request,
        AccesProduits $acces,
        string $reference,
        int $id,
    ): JsonResponse {
        $donnees = $request->validate(['date_fin' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->fermerEnvironnement(
            $reference,
            $id,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }
}
