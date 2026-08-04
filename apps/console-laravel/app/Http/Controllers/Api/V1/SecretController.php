<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Secrets\AccesSecrets;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gouvernance des secrets et clés — métadonnées seules (CAP-CORE-016,
 * partie 4 §2-3).
 *
 * Aucune route ici ne retourne, n'accepte ni ne journalise une valeur
 * secrète : voir `PolitiqueSecretsCles::CHAMPS_INTERDITS`, appliqué par le
 * registre avant toute écriture.
 */
final class SecretController
{
    public function index(Request $request, AccesSecrets $acces): JsonResponse
    {
        $filtres = $request->only(['type_secret', 'environnement_reference', 'realm_reference']);
        $execution = $acces->lister($filtres, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesSecrets $acces): JsonResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'nom' => ['required', 'string', 'max:255'],
            'type_secret' => ['required', 'string', 'in:' . implode(',', PolitiqueSecretsCles::TYPES_SECRET)],
            'finalite_reference' => ['required', 'string', 'max:255'],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'source_reference' => ['required', 'string', 'max:64'],
            'realm_reference' => ['nullable', 'string', 'max:64'],
            'environnement_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueSecretsCles::ENVIRONNEMENTS)],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueSecretsCles::CLASSIFICATIONS)],
            'description' => ['nullable', 'string', 'max:1000'],
            'rotation_requise' => ['nullable', 'boolean'],
            'duree_rotation_jours' => ['nullable', 'integer', 'min:1'],
        ]);
        $execution = $acces->inscrireSecret($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function versions(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $execution = $acces->listerVersions($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function storeVersion(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'version' => ['required', 'string', 'max:64'],
            'fournisseur_reference' => ['required', 'string', 'max:64'],
            'handle_fournisseur' => ['required', 'string', 'max:512'],
            'algorithme_reference' => ['nullable', 'string', 'max:64'],
            'taille_bits' => ['nullable', 'integer'],
            'empreinte_publique' => ['nullable', 'string', 'max:255'],
            'identifiant_public' => ['nullable', 'string', 'max:255'],
            'cle_publique' => ['nullable', 'string', 'max:8192'],
            'date_debut_prevue' => ['nullable', 'date'],
            'date_fin_prevue' => ['nullable', 'date'],
        ]);
        $execution = $acces->declarerVersion($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function showVersion(Request $request, AccesSecrets $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->resoudreVersion($reference, $version, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function verifierVersion(Request $request, AccesSecrets $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->verifierVersion($reference, $version, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activerVersion(Request $request, AccesSecrets $acces, string $reference, int $id): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->activerVersion($reference, $id, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspendreVersion(Request $request, AccesSecrets $acces, string $reference, int $id): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $execution = $acces->suspendreVersion($reference, $id, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function revoquerVersion(Request $request, AccesSecrets $acces, string $reference, int $id): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $execution = $acces->revoquerVersion($reference, $id, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function compromettreVersion(Request $request, AccesSecrets $acces, string $reference, int $id): JsonResponse
    {
        $donnees = $request->validate([
            'niveau' => ['required', 'string', 'in:' . implode(',', PolitiqueSecretsCles::NIVEAUX_COMPROMISSION)],
            'source_reference' => ['required', 'string', 'max:64'],
            'motif' => ['required', 'string', 'max:1000'],
            'portee_presumee' => ['nullable', 'string', 'max:1000'],
        ]);
        $donnees['secret_version_id'] = $id;
        $execution = $acces->declarerCompromission($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function detruireVersion(Request $request, AccesSecrets $acces, string $reference, int $id): JsonResponse
    {
        $donnees = $request->validate(['confirmation_renforcee' => ['required', 'accepted'], 'motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->detruireVersion($reference, $id, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function usages(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $execution = $acces->listerUsages($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function storeUsage(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'capacite_reference' => ['nullable', 'string', 'max:64'],
            'produit_reference' => ['nullable', 'string', 'max:64'],
            'organisation_reference' => ['nullable', 'string', 'max:64'],
            'realm_reference' => ['nullable', 'string', 'max:64'],
            'environnement_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueSecretsCles::ENVIRONNEMENTS)],
            'operation_reference' => ['required', 'string', 'max:128'],
            'finalite_reference' => ['required', 'string', 'max:255'],
            'mode_usage' => ['required', 'string', 'in:' . implode(',', PolitiqueSecretsCles::MODES_USAGE)],
            'secret_version_id' => ['nullable', 'integer'],
            'date_fin' => ['nullable', 'date'],
        ]);
        $donnees['secret_reference'] = $reference;
        $execution = $acces->declarerUsage($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function dependances(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $execution = $acces->listerDependances($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function rotations(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $execution = $acces->listerRotations($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function storeRotation(Request $request, AccesSecrets $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'strategie' => ['required', 'string', 'in:' . implode(',', PolitiqueSecretsCles::STRATEGIES_ROTATION)],
            'date_prevue' => ['required', 'date'],
            'fenetre_fin' => ['nullable', 'date'],
            'ancienne_version_id' => ['nullable', 'integer'],
            'nouvelle_version_id' => ['nullable', 'integer'],
            'retour_arriere_autorise' => ['required', 'boolean'],
            'impact' => ['required', 'array', 'min:1'],
            'etapes' => ['nullable', 'array'],
        ]);
        $donnees['secret_reference'] = $reference;
        $execution = $acces->planifierRotation($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function compromissions(Request $request, AccesSecrets $acces): JsonResponse
    {
        $filtres = $request->only(['etat']);
        $execution = $acces->listerCompromissions($filtres, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function diagnostic(Request $request, AccesSecrets $acces): JsonResponse
    {
        $execution = $acces->diagnostiquer((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
