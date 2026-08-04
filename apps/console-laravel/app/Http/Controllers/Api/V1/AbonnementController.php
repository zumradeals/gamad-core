<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Evenements\AccesEvenements;
use Gamad\JournalEvenements\PolitiqueEvenements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abonnements et livraisons PULL de CAP-CORE-014 (partie 4 §5-6).
 *
 * Le consommateur est toujours résolu depuis la session (`gamad_entite`),
 * jamais depuis le corps de la requête — un abonnement ne peut pas être créé
 * ou lu au nom d'un tiers.
 */
final class AbonnementController
{
    public function index(Request $request, AccesEvenements $acces): JsonResponse
    {
        $execution = $acces->listerAbonnements((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesEvenements $acces): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'consommateur_capacite_reference' => ['nullable', 'string', 'max:64'],
            'consommateur_produit_reference' => ['nullable', 'string', 'max:64'],
            'organisation_reference' => ['nullable', 'string', 'max:64'],
            'realm_reference' => ['required', 'string', 'max:64'],
            'finalite_reference' => ['required', 'string', 'max:255'],
            'mode_livraison' => ['required', 'string', 'in:' . implode(',', PolitiqueEvenements::MODES_LIVRAISON)],
            'taille_lot_max' => ['nullable', 'integer', 'min:1'],
            'duree_bail_secondes' => ['nullable', 'integer', 'min:1'],
            'tentatives_max' => ['nullable', 'integer', 'min:1'],
        ]);
        $execution = $acces->creerAbonnement($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreAbonnement($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function update(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['nullable', 'string', 'max:255'],
            'organisation_reference' => ['nullable', 'string', 'max:64'],
            'taille_lot_max' => ['nullable', 'integer', 'min:1'],
            'duree_bail_secondes' => ['nullable', 'integer', 'min:1'],
            'tentatives_max' => ['nullable', 'integer', 'min:1'],
        ]);
        $execution = $acces->modifierAbonnement($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function ajouterType(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'contrat_reference' => ['required', 'string', 'max:64'],
            'type_evenement' => ['required', 'string', 'max:128'],
            'version_contrainte' => ['nullable', 'string', 'max:32'],
        ]);
        $execution = $acces->ajouterType($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function ajouterProducteur(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['producteur_reference' => ['required', 'string', 'max:64']]);
        $execution = $acces->ajouterProducteur($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function ajouterRealm(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'realm_reference' => ['required', 'string', 'max:64'],
            'portee' => ['nullable', 'string', 'in:' . implode(',', PolitiqueEvenements::PORTEES_REALM)],
        ]);
        $execution = $acces->ajouterRealm($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activer(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->activerAbonnement($reference, [], (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspendre(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->suspendreAbonnement($reference, [], (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function retirer(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->retirerAbonnement($reference, [], (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function retard(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreRetard($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function curseur(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreCurseur($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    // ------------------------------------------------------------------
    // Livraisons PULL (partie 4 §6)

    public function livraisons(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $limite = max(1, min((int) $request->query('limite', 20), PolitiqueEvenements::TAILLE_LOT_MAX));
        $bailSecondes = $request->query('bail_secondes') !== null ? (int) $request->query('bail_secondes') : null;
        $correlation = (string) ($request->attributes->get('gamad_correlation') ?? '');
        $execution = $acces->obtenirLivraisons($reference, $limite, $bailSecondes, (string) $request->attributes->get('gamad_entite'), $correlation);

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function accuser(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'bail' => ['required', 'string', 'max:64'],
            'livraisons' => ['required', 'array', 'min:1'],
            'livraisons.*' => ['string', 'max:64'],
        ]);
        $correlation = (string) ($request->attributes->get('gamad_correlation') ?? '');
        $execution = $acces->accuserLivraisons($reference, $donnees['bail'], $donnees['livraisons'], (string) $request->attributes->get('gamad_entite'), $correlation);

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function refusTemporaire(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'bail' => ['required', 'string', 'max:64'],
            'livraisons' => ['required', 'array', 'min:1'],
            'livraisons.*' => ['string', 'max:64'],
            'code_erreur' => ['required', 'string', 'in:' . implode(',', PolitiqueEvenements::CODES_ERREUR_RETENTABLES)],
            'delai_secondes' => ['nullable', 'integer', 'min:1'],
        ]);
        $correlation = (string) ($request->attributes->get('gamad_correlation') ?? '');
        $execution = $acces->refuserTemporairement(
            $reference, $donnees['bail'], $donnees['livraisons'], $donnees['code_erreur'],
            $donnees['delai_secondes'] ?? null, (string) $request->attributes->get('gamad_entite'), $correlation,
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function refusDefinitif(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'bail' => ['required', 'string', 'max:64'],
            'livraisons' => ['required', 'array', 'min:1'],
            'livraisons.*' => ['string', 'max:64'],
            'code_erreur' => ['required', 'string', 'in:' . implode(',', PolitiqueEvenements::CODES_ERREUR_DEFINITIFS)],
            'motif' => ['required', 'string', 'max:500'],
        ]);
        $correlation = (string) ($request->attributes->get('gamad_correlation') ?? '');
        $execution = $acces->refuserDefinitivement(
            $reference, $donnees['bail'], $donnees['livraisons'], $donnees['code_erreur'],
            $donnees['motif'], (string) $request->attributes->get('gamad_entite'), $correlation,
        );

        return response()->json($execution['corps'], $execution['statut']);
    }
}
