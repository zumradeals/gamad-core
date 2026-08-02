<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Politiques\AccesPolitiques;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registre des politiques sous session (CAP-CORE-007).
 *
 * Le sujet vient exclusivement de la session ; les champs de gouvernance
 * (politique, producteur, source, preuve) ne sont jamais acceptés depuis la
 * requête — ils viennent de la décision CAP-CORE-004 et de la preuve
 * CAP-CORE-013 établies par `AccesPolitiques`.
 */
final class PolitiqueController
{
    public function index(Request $request, AccesPolitiques $acces): JsonResponse
    {
        $execution = $acces->lister((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesPolitiques $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function versions(Request $request, AccesPolitiques $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['versions' => $execution['corps']['versions']], 200);
    }

    public function version(Request $request, AccesPolitiques $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->resoudreVersion($reference, $version, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function historique(Request $request, AccesPolitiques $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['historique' => $execution['corps']['historique']], 200);
    }

    public function store(Request $request, AccesPolitiques $acces): JsonResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'libelle' => ['required', 'string', 'max:255'],
            'domaine' => ['nullable', 'string', 'max:128'],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'source_reference' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->inscrire(
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function creerVersion(Request $request, AccesPolitiques $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'version' => ['required', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:2000'],
            'date_effet_prevue' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $execution = $acces->creerVersion(
            $reference, $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function ajouterRegle(Request $request, AccesPolitiques $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'effet' => ['required', 'string', 'in:' . implode(',', PolitiqueAdministration::EFFETS)],
            'action_reference' => ['required', 'string', 'max:256'],
            'sujet_reference' => ['nullable', 'string', 'max:64'],
            'sujet_type' => ['nullable', 'string', 'max:64'],
            'ressource_reference' => ['nullable', 'string', 'max:256'],
            'ressource_type' => ['nullable', 'string', 'max:64'],
            'motif' => ['required', 'string', 'max:2000'],
            'ordre' => ['nullable', 'integer', 'min:1'],
        ]);

        $execution = $acces->ajouterRegle(
            $reference, $version, $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function modifierRegle(Request $request, AccesPolitiques $acces, string $reference, string $version, int $id): JsonResponse
    {
        $donnees = $request->validate([
            'effet' => ['sometimes', 'string', 'in:' . implode(',', PolitiqueAdministration::EFFETS)],
            'action_reference' => ['sometimes', 'string', 'max:256'],
            'sujet_reference' => ['sometimes', 'nullable', 'string', 'max:64'],
            'ressource_reference' => ['sometimes', 'nullable', 'string', 'max:256'],
            'motif' => ['sometimes', 'string', 'max:2000'],
        ]);

        $execution = $acces->modifierRegle(
            $reference, $version, $id, $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function soumettre(Request $request, AccesPolitiques $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->soumettreVersion(
            $reference, $version, [],
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function simuler(Request $request, AccesPolitiques $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'jeu_reference' => ['required', 'string', 'max:128'],
            'cas' => ['required', 'array', 'min:1'],
            'cas.*.sujet' => ['required', 'string', 'max:64'],
            'cas.*.action' => ['required', 'string', 'max:256'],
            'cas.*.ressource' => ['nullable', 'string', 'max:256'],
            'cas.*.attendu' => ['required', 'string', 'in:PERMIS,REFUSE'],
        ]);

        $execution = $acces->simulerVersion(
            $reference, $version, $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activer(Request $request, AccesPolitiques $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->activerVersion(
            $reference, $version, $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspendre(Request $request, AccesPolitiques $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->suspendreVersion(
            $reference, $version, $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function retirer(Request $request, AccesPolitiques $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->retirer(
            $reference, $donnees,
            (string) $request->attributes->get('gamad_entite'),
            $request->attributes->get('gamad_correlation'),
        );

        return response()->json($execution['corps'], $execution['statut']);
    }
}
