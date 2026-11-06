<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\RelationsGamad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RelationsGamadController
{
    public function index(Request $request, RelationsGamad $relations): Response
    {
        $sujet = (string) $request->attributes->get('gamad_entite');

        return response()->view('reseau.relations', ['relations' => $relations->lister($sujet), 'sujet' => $sujet])
            ->header('Cache-Control', 'private, no-store');
    }

    public function suivis(Request $request, RelationsGamad $relations): JsonResponse
    {
        return response()->json($relations->suivis((string) $request->attributes->get('gamad_entite')))
            ->header('Cache-Control', 'private, no-store');
    }

    public function modifierSuivi(Request $request, RelationsGamad $relations, string $reference): Response
    {
        $relations->modifierSuivi((string) $request->attributes->get('gamad_entite'), $reference, $request->isMethod('PUT'));

        return response()->noContent();
    }

    public function demander(Request $request, RelationsGamad $relations, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['note' => ['required', 'string', 'max:280']]);
        $relations->demander((string) $request->attributes->get('gamad_entite'), $reference, $donnees['note']);

        return redirect()->route('relations.index');
    }

    public function decider(Request $request, RelationsGamad $relations, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'action' => ['required', 'in:accept,decline,withdraw,remove'],
            'revision' => ['required', 'integer', 'min:1'],
        ]);
        $relations->decider(
            (string) $request->attributes->get('gamad_entite'), $reference, $donnees['action'], (int) $donnees['revision'],
        );

        return redirect()->route('relations.index');
    }
}
