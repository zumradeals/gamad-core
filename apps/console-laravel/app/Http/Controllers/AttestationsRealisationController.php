<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\AttestationsRealisationGamad;
use App\Application\Reseau\ContenusGamad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class AttestationsRealisationController
{
    public function index(Request $request, AttestationsRealisationGamad $attestations): JsonResponse
    {
        return response()->json($attestations->lister($this->sujet($request)))->header('Cache-Control', 'private, no-store');
    }

    public function demander(Request $request, AttestationsRealisationGamad $attestations, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'profil' => ['required', 'regex:/^[a-f0-9]{24}$/D'],
            'affirmation' => ['required', 'string', 'max:280'],
            'realise_le' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        $attestation = $attestations->demander($this->sujet($request), $reference, $donnees['profil'], $donnees['affirmation'], $donnees['realise_le']);

        return response()->json(['reference' => $attestation], 201)->header('Cache-Control', 'private, no-store');
    }

    public function decider(Request $request, AttestationsRealisationGamad $attestations, string $reference): Response
    {
        $donnees = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:confirmer,refuser,retirer'],
            'role' => ['nullable', Rule::in(AttestationsRealisationGamad::ROLES)],
            'consentement' => ['sometimes', 'accepted'],
        ]);
        $attestations->decider($this->sujet($request), $reference, (int) $donnees['revision'], $donnees['action'], $donnees['role'] ?? null, isset($donnees['consentement']));

        return response()->noContent();
    }

    public function publiques(Request $request, AttestationsRealisationGamad $attestations, ContenusGamad $contenus, string $reference): JsonResponse
    {
        return response()->json($attestations->publiques($reference, $request->session()->get('gamad_session'), $contenus))
            ->header('Cache-Control', 'private, no-store');
    }

    private function sujet(Request $request): string
    {
        return (string) $request->attributes->get('gamad_entite');
    }
}
