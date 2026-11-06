<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\ContenusGamad;
use App\Application\Reseau\ModerationGamad;
use App\Application\Reseau\PropositionsAideGamad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class ContenusGamadController
{
    public function fil(Request $request, ContenusGamad $contenus): Response
    {
        $filtres = $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'type' => ['nullable', Rule::in(array_keys(ContenusGamad::TYPES))],
        ]);
        $page = (int) ($filtres['page'] ?? 1);
        $fil = $contenus->fil($this->sujet($request), $page);
        abort_if($fil === null, 403);

        return response()->view('reseau.contenus', $fil + [
            'page' => $page,
            'types' => ContenusGamad::TYPES,
            'typeInitial' => $filtres['type'] ?? null,
        ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function montrer(Request $request, ContenusGamad $contenus, string $reference): Response
    {
        $fiche = $contenus->publication($reference, $request->session()->get('gamad_session'));
        abort_if($fiche === null, 404);

        return response()->view('reseau.contenus', $fiche + ['types' => ContenusGamad::TYPES + [ContenusGamad::TYPE_BESOIN => 'J’ai besoin'], 'motifs' => ModerationGamad::MOTIFS])
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

    public function besoin(Request $request, ContenusGamad $contenus, string $reference): JsonResponse
    {
        $besoin = $contenus->besoin($reference, $request->session()->get('gamad_session'));
        abort_if($besoin === null, 404);

        return response()->json($besoin)->header('Cache-Control', 'private, no-store');
    }

    public function publierBesoin(Request $request, ContenusGamad $contenus): JsonResponse
    {
        $donnees = $request->validate([
            'visibilite' => ['required', 'in:public,members'],
            'contenu' => ['required', 'string', 'max:500'],
        ]);
        $reference = $contenus->publier($this->sujet($request), ContenusGamad::TYPE_BESOIN, $donnees['visibilite'], $donnees['contenu']);
        abort_if($reference === null, 403);

        return response()->json(['reference' => $reference, 'etat_besoin' => 'open'], 201)
            ->header('Location', route('besoins.show', $reference))
            ->header('Cache-Control', 'private, no-store');
    }

    public function terminerBesoin(Request $request, ContenusGamad $contenus, string $reference): Response
    {
        $donnees = $request->validate(['action' => ['required', 'in:resoudre,fermer']]);
        abort_unless($contenus->terminerBesoin($this->sujet($request), $reference, $donnees['action'] === 'resoudre'), 404);

        return response()->noContent();
    }

    public function propositionsAide(Request $request, PropositionsAideGamad $aide, string $reference): JsonResponse
    {
        return response()->json($aide->lister($this->sujet($request), $reference))
            ->header('Cache-Control', 'private, no-store');
    }

    public function proposerAide(Request $request, PropositionsAideGamad $aide, string $reference): JsonResponse
    {
        $donnees = $request->validate(['message' => ['nullable', 'string', 'max:280']]);
        $proposition = $aide->proposer($this->sujet($request), $reference, $donnees['message'] ?? '');

        return response()->json(['reference' => $proposition], 201)->header('Cache-Control', 'private, no-store');
    }

    public function traiterAide(Request $request, PropositionsAideGamad $aide, string $reference, string $proposition): Response
    {
        $donnees = $request->validate(['action' => ['required', 'in:retirer,accepter,refuser']]);
        $aide->traiter($this->sujet($request), $reference, $proposition, $donnees['action']);

        return response()->noContent();
    }

    private function sujet(Request $request): string
    {
        return (string) $request->attributes->get('gamad_entite');
    }
}
