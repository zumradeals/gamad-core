<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Matching\AccesMatching;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Matching (CAP-CORE-021, doc de chantier 04). Périmètre exposé : voir la
 * réserve documentée en tête d'`AccesMatching` — les faits qui dépendent de
 * CAP-CORE-004/006/008/011/012/017/018 (statut de produit, de contrat, de
 * source, risque et incident bloquants) sont acceptés dans le corps de la
 * requête, jamais résolus par ce contrôleur lui-même ; réserve documentée,
 * pas une omission silencieuse.
 */
final class MatchingController
{
    public function contextes(Request $request, AccesMatching $acces): JsonResponse
    {
        $execution = $acces->listerContextes((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function contexte(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreContexte($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function profil(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreProfil($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function compilerProfil(Request $request, AccesMatching $acces): JsonResponse
    {
        $specification = $request->validate([
            'politique_reference' => ['required', 'string', 'max:64'],
            'politique_version' => ['required', 'string', 'max:32'],
            'contexte_reference' => ['required', 'string', 'max:64'],
            'contrat_reference' => ['required', 'string', 'max:64'],
            'contrat_version' => ['required', 'string', 'max:32'],
            'algorithme_code' => ['nullable', 'string', 'max:64'],
            'algorithme_version' => ['nullable', 'string', 'max:32'],
            'criteres' => ['nullable', 'array'],
            'seuils_classe' => ['nullable', 'array'],
            'precision_arrondi' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
        $execution = $acces->compilerProfil(
            $specification,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function demandes(Request $request, AccesMatching $acces): JsonResponse
    {
        $filtres = $request->only(['contexte_reference', 'consommateur_produit', 'etat']);
        $execution = $acces->listerDemandes($filtres, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function demande(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreDemande($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function historiqueDemande(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $execution = $acces->historiqueDemande($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function soumettreDemande(Request $request, AccesMatching $acces): JsonResponse
    {
        $donnees = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:128'],
            'consommateur_produit' => ['required', 'string', 'max:64'],
            'consommateur_organisation' => ['nullable', 'string', 'max:64'],
            'contexte_reference' => ['required', 'string', 'max:64'],
            'finalite_reference' => ['required', 'string', 'max:255'],
            'realm_reference' => ['required', 'string', 'max:64'],
            'environnement' => ['required', 'string', 'max:32'],
            'mode_resultat' => ['required', 'string', 'max:32'],
            'classification' => ['required', 'string', 'max:32'],
            'contrat_reference' => ['required', 'string', 'max:64'],
            'contrat_version' => ['required', 'string', 'max:32'],
            'correlation_id' => ['required', 'string', 'max:128'],
            'objets' => ['required', 'array', 'min:1'],
            'criteres' => ['nullable', 'array'],
            'limite_resultats' => ['nullable', 'integer', 'min:1'],
            'expire_le' => ['nullable', 'string'],
        ]);
        $execution = $acces->soumettreDemande(
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function annulerDemande(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:255']]);
        $execution = $acces->annulerDemande(
            $reference,
            (string) $donnees['motif'],
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function executerDemande(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $faits = $request->validate(['candidats_exclus' => ['nullable', 'array']]);
        $execution = $acces->executer(
            $reference,
            $faits,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function resultat(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreResultat($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function explicationResultat(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $execution = $acces->expliquerResultat($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function construireSegment(Request $request, AccesMatching $acces): JsonResponse
    {
        $donnees = $request->validate([
            'demande_reference' => ['required', 'string', 'max:64'],
            'classes_incluses' => ['nullable', 'array'],
        ]);
        $execution = $acces->construireSegment(
            (string) $donnees['demande_reference'],
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function segment(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreSegment($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function appartenanceSegment(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['token' => ['required', 'string', 'max:128']]);
        $execution = $acces->verifierAppartenanceSegment(
            $reference,
            (string) $donnees['token'],
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activerSegment(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'consommateur_produit' => ['required', 'string', 'max:64'],
            'consommateur_organisation' => ['nullable', 'string', 'max:64'],
            'finalite_reference' => ['required', 'string', 'max:255'],
            'realm_reference' => ['required', 'string', 'max:64'],
            'environnement' => ['required', 'string', 'max:32'],
            'contrat_reference' => ['required', 'string', 'max:64'],
            'contrat_version' => ['required', 'string', 'max:32'],
            'autorisation_reference' => ['required', 'string', 'max:128'],
            'usage_autorise' => ['required', 'string', 'max:255'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'duree_secondes' => ['nullable', 'integer', 'min:1'],
            'obligations_supplementaires' => ['nullable', 'array'],
            'decision_reference' => ['nullable', 'string', 'max:64'],
            'faits' => ['nullable', 'array'],
        ]);
        $faits = $donnees['faits'] ?? [];
        unset($donnees['faits']);
        $execution = $acces->demanderActivation(
            $reference,
            $donnees,
            $faits,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspensionSegment(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $execution = $acces->suspendreSegment(
            $reference,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function revocationSegment(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $execution = $acces->revoquerSegment(
            $reference,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function mesureActivation(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'mesure_code' => ['required', 'string', 'max:64'],
            'valeur_numerique' => ['nullable', 'numeric'],
            'valeur_categorielle' => ['nullable', 'string', 'max:64'],
            'population_reference' => ['nullable', 'string', 'max:64'],
            'fenetre_debut' => ['required', 'string'],
            'fenetre_fin' => ['required', 'string'],
            'source_reference' => ['required', 'string', 'max:64'],
            'contrat_reference' => ['required', 'string', 'max:64'],
            'contrat_version' => ['nullable', 'string', 'max:32'],
            'classification' => ['required', 'string', 'max:32'],
            'correlation_id' => ['nullable', 'string', 'max:128'],
            'faits' => ['nullable', 'array'],
        ]);
        $faits = $donnees['faits'] ?? [];
        unset($donnees['faits']);
        $execution = $acces->enregistrerMesure(
            $reference,
            $donnees,
            $faits,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function ouvrirContestation(Request $request, AccesMatching $acces): JsonResponse
    {
        $donnees = $request->validate([
            'resultat_reference' => ['nullable', 'string', 'max:64'],
            'segment_reference' => ['nullable', 'string', 'max:64'],
            'activation_reference' => ['nullable', 'string', 'max:64'],
            'contestant_reference' => ['required', 'string', 'max:64'],
            'motif_code' => ['required', 'string', 'max:64'],
            'description_minimale' => ['nullable', 'string', 'max:1000'],
            'source_contestee' => ['nullable', 'string', 'max:255'],
            'realm_reference' => ['required', 'string', 'max:64'],
            'classification' => ['required', 'string', 'max:32'],
            'faits' => ['nullable', 'array'],
        ]);
        $faits = $donnees['faits'] ?? [];
        unset($donnees['faits']);
        $execution = $acces->ouvrirContestation(
            $donnees,
            $faits,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function reexecutionContestation(Request $request, AccesMatching $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'nouvelle_execution_reference' => ['nullable', 'string', 'max:64'],
            'sources_corrigees' => ['nullable', 'array'],
        ]);
        $execution = $acces->reexaminer(
            $reference,
            $donnees['nouvelle_execution_reference'] ?? null,
            $donnees['sources_corrigees'] ?? [],
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }
}
