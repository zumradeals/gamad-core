<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Realms\AccesRealms;
use Gamad\RegistreRealms\PolitiqueRealms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registre des realms sous session (CAP-CORE-012).
 *
 * Le sujet vient exclusivement de la session ; les champs de gouvernance
 * (politique, producteur, source, preuve) ne sont jamais acceptés depuis la
 * requête — ils viennent de la décision CAP-CORE-004 et de la preuve
 * CAP-CORE-013 établies par `AccesRealms`.
 */
final class RealmController
{
    public function index(Request $request, AccesRealms $acces): JsonResponse
    {
        $filtres = array_filter([
            'type' => $request->query('type'), 'etat' => $request->query('etat'),
            'organisation' => $request->query('organisation'), 'produit' => $request->query('produit'),
            'parent' => $request->query('parent'), 'classification' => $request->query('classification'),
        ], static fn (mixed $v): bool => $v !== null);
        $execution = $acces->lister((string) $request->attributes->get('gamad_entite'), $filtres);

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function historique(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreHistorique($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function relations(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreRelations($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function parents(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreParents($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function enfants(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreEnfants($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function perimetres(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudrePerimetres($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function identifiantsExternes(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreIdentifiantsExternes($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function organisations(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreOrganisations($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function produits(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreProduits($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function contrats(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreContrats($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function franchissements(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreFranchissements($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function verification(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreVerification($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function verifierPortee(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'organisation' => ['nullable', 'string', 'max:64'],
            'produit' => ['nullable', 'string', 'max:64'],
            'contrat' => ['nullable', 'string', 'max:64'],
            'operation' => ['nullable', 'string', 'max:255'],
            'finalite' => ['nullable', 'string', 'max:255'],
            'realm_source' => ['nullable', 'string', 'max:64'],
            'realm_cible' => ['nullable', 'string', 'max:64'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->verifierPortee($reference, $donnees, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesRealms $acces): JsonResponse
    {
        $donnees = $request->validate([
            'identite_reference' => ['required', 'string', 'max:64'],
            'code_canonique' => ['required', 'string', 'max:128'],
            'type_realm_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::TYPES_REALM)],
            'nom_affichage' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'organisation_responsable_reference' => ['nullable', 'string', 'max:64'],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::CLASSIFICATIONS)],
        ]);
        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function update(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'nom_affichage' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'organisation_responsable_reference' => ['nullable', 'string', 'max:64'],
            'classification_reference' => ['nullable', 'string', 'in:' . implode(',', PolitiqueRealms::CLASSIFICATIONS)],
        ]);
        $execution = $acces->modifier($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activer(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->activer($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspendre(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif_reference' => ['nullable', 'string', 'max:64'], 'motif_detail' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->suspendre($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function fermer(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif_reference' => ['nullable', 'string', 'max:64'], 'motif_detail' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->fermer($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function retirer(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif_reference' => ['required', 'string', 'max:64'], 'motif_detail' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->retirer($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerRelation(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'realm_cible_reference' => ['required', 'string', 'max:64'],
            'type_relation_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::TYPES_RELATION)],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $donnees['realm_source_reference'] = $reference;
        $execution = $acces->declarerRelation($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function fermerRelation(Request $request, AccesRealms $acces, string $reference, string $relation): JsonResponse
    {
        $donnees = $request->validate(['date_fin' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->fermerRelation($relation, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerPerimetre(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'dimension_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::DIMENSIONS_PERIMETRE)],
            'valeur_reference' => ['required', 'string', 'max:255'],
            'valeur_externe' => ['nullable', 'string', 'max:255'],
            'systeme_externe_reference' => ['nullable', 'string', 'max:128'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->declarerPerimetre($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function fermerPerimetre(Request $request, AccesRealms $acces, string $reference, int $perimetre): JsonResponse
    {
        $donnees = $request->validate(['date_fin' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->fermerPerimetre($reference, $perimetre, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerIdentifiant(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'systeme_reference' => ['required', 'string', 'max:128'],
            'valeur' => ['required', 'string', 'max:255'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->declarerIdentifiant($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function rattacherOrganisation(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'organisation_reference' => ['required', 'string', 'max:64'],
            'role_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::ROLES_ORGANISATION)],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::CLASSIFICATIONS)],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->rattacherOrganisation($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function detacherOrganisation(Request $request, AccesRealms $acces, string $reference, string $rattachement): JsonResponse
    {
        $donnees = $request->validate(['date_fin' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->detacherOrganisation($rattachement, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function rattacherProduit(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'produit_reference' => ['required', 'string', 'max:64'],
            'role_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::ROLES_PRODUIT)],
            'environnement_reference' => ['nullable', 'string', 'max:64'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->rattacherProduit($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function detacherProduit(Request $request, AccesRealms $acces, string $reference, string $rattachement): JsonResponse
    {
        $donnees = $request->validate(['date_fin' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->detacherProduit($rattachement, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function rattacherContrat(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'contrat_reference' => ['required', 'string', 'max:64'],
            'version_reference' => ['nullable', 'string', 'max:32'],
            'role_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::ROLES_CONTRAT)],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->rattacherContrat($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function detacherContrat(Request $request, AccesRealms $acces, string $reference, int $rattachement): JsonResponse
    {
        $donnees = $request->validate(['date_fin' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->detacherContrat($rattachement, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerFranchissement(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'realm_cible_reference' => ['required', 'string', 'max:64'],
            'objet_reference' => ['required', 'string', 'max:255'],
            'type_objet_reference' => ['required', 'string', 'max:64'],
            'effet_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::EFFETS_FRANCHISSEMENT)],
            'finalite_reference' => ['required', 'string', 'max:255'],
            'contrat_reference' => ['nullable', 'string', 'max:64'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $donnees['realm_source_reference'] = $reference;
        $execution = $acces->declarerFranchissement($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function fermerFranchissement(Request $request, AccesRealms $acces, string $reference, int $franchissement): JsonResponse
    {
        $donnees = $request->validate(['date_fin' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->fermerFranchissement($franchissement, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function enregistrerVerification(Request $request, AccesRealms $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'type_verification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::TYPES_VERIFICATION)],
            'resultat_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::RESULTATS_VERIFICATION)],
            'verifie_par_reference' => ['required', 'string', 'max:64'],
            'verifie_le' => ['nullable', 'date_format:Y-m-d'],
            'expire_le' => ['nullable', 'date_format:Y-m-d'],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);
        $execution = $acces->enregistrerVerification($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
