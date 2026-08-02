<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Contrats\AccesContrats;
use Gamad\RegistreContrats\PolitiqueContrats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registre des contrats sous session (CAP-CORE-009).
 *
 * Le sujet vient exclusivement de la session ; les champs de gouvernance
 * (politique, producteur, source, preuve) ne sont jamais acceptés depuis la
 * requête — ils viennent de la décision CAP-CORE-004 et de la preuve
 * CAP-CORE-013 établies par `AccesContrats`.
 */
final class ContratController
{
    public function index(Request $request, AccesContrats $acces): JsonResponse
    {
        $execution = $acces->lister((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesContrats $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function versions(Request $request, AccesContrats $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['versions' => $execution['corps']['versions']], 200);
    }

    public function version(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->resoudreVersion($reference, $version, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function historique(Request $request, AccesContrats $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['historique' => $execution['corps']['historique']], 200);
    }

    public function compatibilite(Request $request, AccesContrats $acces, string $reference): JsonResponse
    {
        $version = (string) $request->query('version', '');
        if ($version === '') {
            return response()->json(['erreur' => 'VERSION_REQUISE', 'message' => 'le paramètre `version` est obligatoire'], 422);
        }
        $execution = $acces->resoudreCompatibilite($reference, $version, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function conformite(Request $request, AccesContrats $acces, string $reference): JsonResponse
    {
        $version = (string) $request->query('version', '');
        if ($version === '') {
            return response()->json(['erreur' => 'VERSION_REQUISE', 'message' => 'le paramètre `version` est obligatoire'], 422);
        }
        $execution = $acces->resoudreConformite($reference, $version, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function consommateurs(Request $request, AccesContrats $acces, string $reference): JsonResponse
    {
        $version = $request->query('version');
        $execution = $acces->resoudreConsommateurs($reference, $version === null ? null : (string) $version, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesContrats $acces): JsonResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'nom' => ['required', 'string', 'max:255'],
            'type_contrat' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::TYPES_CONTRAT)],
            'finalite_reference' => ['required', 'string', 'max:500'],
            'producteur_capacite_reference' => ['nullable', 'string', 'max:64'],
            'producteur_produit_reference' => ['nullable', 'string', 'max:64'],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'source_reference' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function creerVersion(Request $request, AccesContrats $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'version' => ['required', 'string', 'max:32'],
            'compatibilite_annoncee' => ['nullable', 'string', 'in:' . implode(',', PolitiqueContrats::COMPATIBILITES_ANNONCEES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'date_effet_prevue' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $execution = $acces->creerVersion($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerPartie(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::ROLES_PARTIE)],
            'partie_type' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::TYPES_PARTIE)],
            'partie_reference' => ['required', 'string', 'max:64'],
        ]);

        $execution = $acces->declarerPartie($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerOperation(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'reference_operation' => ['required', 'string', 'max:128'],
            'type_operation' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::TYPES_OPERATION)],
            'methode_http' => ['nullable', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
            'chemin_http' => ['nullable', 'string', 'max:256'],
            'action_autorisation' => ['nullable', 'string', 'max:256'],
            'duree_secondes' => ['nullable', 'integer', 'min:0'],
            'idempotente' => ['nullable', 'boolean'],
            'audit_obligatoire' => ['nullable', 'boolean'],
            'ordre' => ['nullable', 'integer', 'min:1'],
        ]);

        $execution = $acces->declarerOperation($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerSchema(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'operation_reference' => ['nullable', 'string', 'max:128'],
            'sens' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::SENS_SCHEMA)],
            'format' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::FORMATS_SCHEMA)],
            'contenu' => ['nullable', 'string', 'max:20000'],
        ]);

        $execution = $acces->declarerSchema($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerErreur(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'operation_reference' => ['nullable', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:128'],
            'statut_http' => ['nullable', 'integer', 'min:100', 'max:599'],
            'retentable' => ['nullable', 'boolean'],
            'detail_exposable' => ['nullable', 'boolean'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $execution = $acces->declarerErreur($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function soumettre(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->soumettreVersion($reference, $version, [], (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function analyser(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->analyserCompatibilite($reference, $version, [], (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activer(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'motif' => ['nullable', 'string', 'max:500'],
            'plan_migration' => ['nullable', 'string', 'max:2000'],
            'date_limite_migration' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->activerVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function deprecier(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500'], 'date_limite_migration' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->deprecierVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspendre(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->suspendreVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function retirer(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->retirerVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function enregistrerConformite(Request $request, AccesContrats $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'resultat' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::RESULTATS_CONFORMITE)],
            'partie_reference' => ['nullable', 'string', 'max:64'],
            'artefact_reference' => ['required', 'string', 'max:256'],
            'resume' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->enregistrerConformite($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
