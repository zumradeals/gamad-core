<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Organisations\AccesOrganisations;
use Gamad\RegistreOrganisations\PolitiqueOrganisations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registre des organisations sous session (CAP-CORE-002).
 *
 * Le sujet vient exclusivement de la session ; les champs de gouvernance
 * (politique, producteur, source, preuve) ne sont jamais acceptés depuis la
 * requête — ils viennent de la décision CAP-CORE-004 et de la preuve
 * CAP-CORE-013 établies par `AccesOrganisations`.
 */
final class OrganisationController
{
    public function index(Request $request, AccesOrganisations $acces): JsonResponse
    {
        $execution = $acces->lister((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function structure(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreStructure($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function unites(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreStructure($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['unites' => $execution['corps']['structure']], 200);
    }

    public function relations(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreRelations($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function affiliations(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $filtres = array_filter([
            'type' => $request->query('type'), 'etat' => $request->query('etat'),
        ], static fn (mixed $v): bool => $v !== null);
        $execution = $acces->resoudreAffiliations($reference, (string) $request->attributes->get('gamad_entite'), $filtres);

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function fonctions(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreFonctions($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function affiliationsIdentite(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $filtres = array_filter([
            'type' => $request->query('type'), 'etat' => $request->query('etat'),
        ], static fn (mixed $v): bool => $v !== null);
        $execution = $acces->resoudreAffiliationsIdentite($reference, (string) $request->attributes->get('gamad_entite'), $filtres);

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function verifierAppartenance(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'identite_reference' => ['required', 'string', 'max:64'],
            'type' => ['nullable', 'string', 'max:64'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->verifierAppartenance($reference, $donnees, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function verifierRepresentation(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'identite_reference' => ['required', 'string', 'max:64'],
            'action' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->verifierRepresentation($reference, $donnees, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesOrganisations $acces): JsonResponse
    {
        $donnees = $request->validate([
            'identite_reference' => ['required', 'string', 'max:64'],
            'type_organisation_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_ORGANISATION)],
            'personnalite_juridique' => ['nullable', 'boolean'],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'denomination_officielle' => ['required', 'string', 'max:500'],
            'nom_court' => ['nullable', 'string', 'max:255'],
            'nom_commercial' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'forme_reference' => ['nullable', 'string', 'in:' . implode(',', PolitiqueOrganisations::FORMES_ORGANISATION)],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::CLASSIFICATIONS)],
        ]);

        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function update(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'denomination_officielle' => ['nullable', 'string', 'max:500'],
            'nom_court' => ['nullable', 'string', 'max:255'],
            'nom_commercial' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'forme_reference' => ['nullable', 'string', 'in:' . implode(',', PolitiqueOrganisations::FORMES_ORGANISATION)],
            'classification_reference' => ['nullable', 'string', 'in:' . implode(',', PolitiqueOrganisations::CLASSIFICATIONS)],
        ]);
        $execution = $acces->modifier($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activer(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->activer($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspendre(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->suspendre($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function dissoudre(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->dissoudre($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function retirer(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $execution = $acces->retirer($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerIdentifiant(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'systeme_reference' => ['required', 'string', 'max:128'],
            'type_identifiant_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_IDENTIFIANT_EXTERNE)],
            'valeur_normalisee' => ['required', 'string', 'max:255'],
            'valeur_affichage' => ['nullable', 'string', 'max:255'],
            'pays_ou_realm_reference' => ['nullable', 'string', 'max:64'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'verifie' => ['nullable', 'boolean'],
        ]);
        $execution = $acces->declarerIdentifiant($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function creerUnite(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'unite_parente_reference' => ['nullable', 'string', 'max:64'],
            'type_unite_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_UNITE)],
            'nom' => ['required', 'string', 'max:255'],
            'code_interne' => ['nullable', 'string', 'max:64'],
            'realm_reference' => ['nullable', 'string', 'max:64'],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::CLASSIFICATIONS)],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->creerUnite($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerRelation(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'organisation_cible_reference' => ['required', 'string', 'max:64'],
            'type_relation_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_RELATION)],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'pourcentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::CLASSIFICATIONS)],
        ]);
        $donnees['organisation_source_reference'] = $reference;
        $execution = $acces->declarerRelation($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function proposerAffiliation(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'identite_reference' => ['required', 'string', 'max:64'],
            'unite_reference' => ['nullable', 'string', 'max:64'],
            'type_affiliation_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_AFFILIATION)],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'date_fin_prevue' => ['nullable', 'date_format:Y-m-d'],
            'niveau_assurance_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::NIVEAUX_ASSURANCE)],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::CLASSIFICATIONS)],
            'producteur_reference' => ['required', 'string', 'max:64'],
        ]);
        $donnees['organisation_reference'] = $reference;
        $execution = $acces->proposerAffiliation($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activerAffiliation(Request $request, AccesOrganisations $acces, string $reference, string $affiliation): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->activerAffiliation($affiliation, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspendreAffiliation(Request $request, AccesOrganisations $acces, string $reference, string $affiliation): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->suspendreAffiliation($affiliation, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function fermerAffiliation(Request $request, AccesOrganisations $acces, string $reference, string $affiliation): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->fermerAffiliation($affiliation, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function creerFonction(Request $request, AccesOrganisations $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'unite_reference' => ['nullable', 'string', 'max:64'],
            'type_fonction_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_FONCTION)],
            'libelle' => ['required', 'string', 'max:500'],
            'mandat_fonction_reference' => ['nullable', 'string', 'max:64'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->creerFonction($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
