<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Sources\AccesSources;
use Gamad\RegistreSources\PolitiqueSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registre des sources sous session (CAP-CORE-006).
 *
 * Le sujet vient exclusivement de la session ; les champs de gouvernance
 * (politique, producteur, source, preuve) ne sont jamais acceptés depuis la
 * requête — ils viennent de la décision CAP-CORE-004 et de la preuve
 * CAP-CORE-013 établies par `AccesSources`.
 */
final class SourceController
{
    public function index(Request $request, AccesSources $acces): JsonResponse
    {
        $execution = $acces->lister(
            (string) $request->attributes->get('gamad_entite'),
            $request->query('etat'),
            $request->query('type'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesSources $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function lignee(Request $request, AccesSources $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['lignee' => $execution['corps']['lignee']], 200);
    }

    public function finalites(Request $request, AccesSources $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['finalites' => $execution['corps']['finalites']], 200);
    }

    public function verification(Request $request, AccesSources $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['verification' => $execution['corps']['verification']], 200);
    }

    public function utilisabilite(Request $request, AccesSources $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'consommateur' => ['nullable', 'string', 'max:64'],
            'finalite' => ['required', 'string', 'max:128'],
        ]);

        $execution = $acces->verifierUtilisabilite(
            $reference,
            $donnees['consommateur'] ?? null,
            $donnees['finalite'],
            (string) $request->attributes->get('gamad_entite'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesSources $acces): JsonResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'nom_canonique' => ['required', 'string', 'max:255'],
            'nom_affichage' => ['required', 'string', 'max:255'],
            'type_source' => ['required', 'string', 'in:' . implode(',', PolitiqueSources::TYPES_SOURCE)],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'categorie' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'produit_producteur_reference' => ['nullable', 'string', 'max:64'],
            'reserve' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->inscrire(
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function update(Request $request, AccesSources $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'nom_affichage' => ['sometimes', 'string', 'max:255'],
            'categorie' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'proprietaire_reference' => ['sometimes', 'string', 'max:64'],
            'produit_producteur_reference' => ['sometimes', 'nullable', 'string', 'max:64'],
            'reserve' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->modifier(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activer(Request $request, AccesSources $acces, string $reference): JsonResponse
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

    public function suspendre(Request $request, AccesSources $acces, string $reference): JsonResponse
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

    public function retirer(Request $request, AccesSources $acces, string $reference): JsonResponse
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

    public function declarerFinalite(Request $request, AccesSources $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'finalite_reference' => ['required', 'string', 'max:128'],
            'produit_consommateur_reference' => ['nullable', 'string', 'max:64'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'date_fin' => ['nullable', 'date_format:Y-m-d'],
            'restriction' => ['nullable', 'string', 'max:1000'],
        ]);

        $execution = $acces->declarerFinalite(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function fermerFinalite(Request $request, AccesSources $acces, string $reference, int $id): JsonResponse
    {
        $donnees = $request->validate(['date_fin' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->fermerFinalite(
            $reference,
            $id,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function enregistrerVerification(Request $request, AccesSources $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'niveau' => ['required', 'string', 'in:' . implode(',', PolitiqueSources::NIVEAUX_VERIFICATION)],
            'resultat' => ['required', 'string', 'in:' . implode(',', PolitiqueSources::RESULTATS_VERIFICATION)],
            'verifie_par_reference' => ['required', 'string', 'max:64'],
            'verifie_le' => ['nullable', 'date_format:Y-m-d'],
            'expire_le' => ['nullable', 'date_format:Y-m-d'],
            'motif' => ['nullable', 'string', 'max:1000'],
        ]);

        $execution = $acces->enregistrerVerification(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerLignee(Request $request, AccesSources $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'source_parente_reference' => ['required', 'string', 'max:64'],
            'type_relation' => ['required', 'string', 'in:' . implode(',', PolitiqueSources::TYPES_LIGNEE)],
        ]);

        $execution = $acces->declarerLignee(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }
}
