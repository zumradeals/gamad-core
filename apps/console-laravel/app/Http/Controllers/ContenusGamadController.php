<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\ContenusGamad;
use App\Application\Reseau\ModerationGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class ContenusGamadController
{
    public function fil(Request $request, ContenusGamad $contenus): Response
    {
        $page = (int) ($request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']])['page'] ?? 1);
        $fil = $contenus->fil($this->sujet($request), $page);
        abort_if($fil === null, 403);

        return response()->view('reseau.contenus', $fil + ['page' => $page, 'types' => ContenusGamad::TYPES])
            ->header('Cache-Control', 'private, no-store');
    }

    public function montrer(Request $request, ContenusGamad $contenus, string $reference): Response
    {
        $fiche = $contenus->publication($reference, $request->session()->get('gamad_session'));
        abort_if($fiche === null, 404);

        return response()->view('reseau.contenus', $fiche + ['types' => ContenusGamad::TYPES, 'motifs' => ModerationGamad::MOTIFS])
            ->header('Cache-Control', 'private, no-store');
    }

    public function publier(Request $request, ContenusGamad $contenus): RedirectResponse
    {
        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(ContenusGamad::TYPES))],
            'visibilite' => ['required', 'in:public,members'],
            'contenu' => ['required', 'string', 'max:500'],
        ]);
        $reference = $contenus->publier($this->sujet($request), $donnees['type'], $donnees['visibilite'], $donnees['contenu']);
        abort_if($reference === null, 403);

        return redirect()->route('publications.show', $reference);
    }

    public function commenter(Request $request, ContenusGamad $contenus, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['contenu' => ['required', 'string', 'max:500']]);
        abort_unless($contenus->commenter($this->sujet($request), $reference, $donnees['contenu']), 404);

        return redirect()->route('publications.show', $reference);
    }

    public function modifierCommentaire(Request $request, ContenusGamad $contenus, string $reference, string $commentaire): Response
    {
        $donnees = $request->validate(['contenu' => ['required', 'string', 'max:500']]);
        $contenus->modifierCommentaire($this->sujet($request), $reference, $commentaire, $donnees['contenu']);

        return response()->noContent();
    }

    private function sujet(Request $request): string
    {
        return (string) $request->attributes->get('gamad_entite');
    }
}
