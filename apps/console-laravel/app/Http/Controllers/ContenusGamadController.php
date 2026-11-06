<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\ContenusGamad;
use App\Application\Reseau\ConversationAideGamad;
use App\Application\Reseau\ModerationGamad;
use App\Application\Reseau\PropositionsAideGamad;
use App\Application\Reseau\ReferentielBesoinsGamad;
use App\Support\Normalizer;
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

        $racines = array_filter($fiche['commentaires'], static fn (array $commentaire): bool => $commentaire['parent_reference'] === null);
        $fiche['reponses'] = collect($fiche['commentaires'])->whereNotNull('parent_reference')->groupBy('parent_reference');
        $fiche['commentaires'] = $racines;

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

    public function commentaires(Request $request, ContenusGamad $contenus, string $reference): JsonResponse
    {
        $fiche = $contenus->publication($reference, $request->session()->get('gamad_session'));
        abort_if($fiche === null, 404);

        return response()->json($fiche['commentaires'])->header('Cache-Control', 'private, no-store');
    }

    public function commenter(Request $request, ContenusGamad $contenus, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'contenu' => ['required', 'string', 'max:500'],
            'parent' => ['nullable', 'regex:/^[a-f0-9]{24}$/D'],
        ]);
        abort_unless($contenus->commenter($this->sujet($request), $reference, $donnees['contenu'], $donnees['parent'] ?? null), 404);

        return redirect()->route('publications.show', $reference);
    }

    public function modifierCommentaire(Request $request, ContenusGamad $contenus, string $reference, string $commentaire): Response
    {
        $donnees = $request->validate(['contenu' => ['required', 'string', 'max:500']]);
        $contenus->modifierCommentaire($this->sujet($request), $reference, $commentaire, $donnees['contenu']);

        return response()->noContent();
    }

    public function retirerCommentaire(Request $request, ContenusGamad $contenus, string $reference, string $commentaire): Response
    {
        $contenus->retirerCommentaire($this->sujet($request), $reference, $commentaire);

        return response()->noContent();
    }

    public function besoins(Request $request, ContenusGamad $contenus): JsonResponse
    {
        $filtres = $request->validate([
            'etat' => ['nullable', 'in:open,in_progress,resolved,closed,tous'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'emetteur' => ['nullable', 'in:personne,zumra,projet'],
            'domaine' => ['nullable', 'string', Rule::in(array_keys(ReferentielBesoinsGamad::DOMAINES))],
            'territoire' => ['nullable', 'string', Rule::in(array_keys(ReferentielBesoinsGamad::TERRITOIRES))],
        ]);
        $page = (int) ($filtres['page'] ?? 1);
        $besoins = $contenus->besoins(
            $request->session()->get('gamad_session'),
            $filtres['etat'] ?? 'open',
            Normalizer::key($filtres['q'] ?? ''),
            $page,
            $filtres['domaine'] ?? null,
            $filtres['territoire'] ?? null,
            $filtres['emetteur'] ?? null,
        );

        return response()->json($besoins + ['page' => $page])->header('Cache-Control', 'private, no-store');
    }

    public function referentielsBesoins(): JsonResponse
    {
        return response()->json([
            'domaines' => ReferentielBesoinsGamad::libelles('domaines'),
            'territoires' => ReferentielBesoinsGamad::libelles('territoires'),
        ]);
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
            'zumra' => ['nullable', 'prohibits:projet', 'regex:/^[a-f0-9]{24}$/D'],
            'projet' => ['nullable', 'regex:/^[a-f0-9]{24}$/D'],
            'visibilite' => ['required', 'in:public,members,zumra'],
            'contenu' => ['required', 'string', 'max:500'],
            'domaine' => ['nullable', 'string', Rule::in(array_keys(ReferentielBesoinsGamad::DOMAINES))],
            'territoire' => ['nullable', 'string', Rule::in(array_keys(ReferentielBesoinsGamad::TERRITOIRES))],
        ]);
        $reference = $contenus->publierBesoin(
            $this->sujet($request),
            $donnees['visibilite'],
            $donnees['contenu'],
            $donnees['domaine'] ?? null,
            $donnees['territoire'] ?? null,
            $donnees['zumra'] ?? null,
            $donnees['projet'] ?? null,
        );
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

    public function enregistrerBilanBesoin(Request $request, ContenusGamad $contenus, string $reference): Response
    {
        $donnees = $request->validate(['bilan' => ['present', 'nullable', 'string', 'max:280']]);
        $contenus->enregistrerBilan($this->sujet($request), $reference, $donnees['bilan']);

        return response()->noContent();
    }

    public function propositionsAide(Request $request, PropositionsAideGamad $aide, string $reference): JsonResponse
    {
        return response()->json($aide->lister($this->sujet($request), $reference, $request->routeIs('publications.reponses.*')))
            ->header('Cache-Control', 'private, no-store');
    }

    public function proposerAide(Request $request, PropositionsAideGamad $aide, string $reference): JsonResponse
    {
        $donnees = $request->validate(['message' => ['nullable', 'string', 'max:280']]);
        $proposition = $aide->proposer($this->sujet($request), $reference, $donnees['message'] ?? '', $request->routeIs('publications.reponses.*'));

        return response()->json(['reference' => $proposition], 201)->header('Cache-Control', 'private, no-store');
    }

    public function traiterAide(Request $request, PropositionsAideGamad $aide, string $reference, string $proposition): Response
    {
        $donnees = $request->validate(['action' => ['required', 'in:retirer,accepter,refuser']]);
        $aide->traiter($this->sujet($request), $reference, $proposition, $donnees['action'], $request->routeIs('publications.reponses.*'));

        return response()->noContent();
    }

    public function conversationAide(Request $request, ConversationAideGamad $conversation, string $reference, string $proposition): JsonResponse
    {
        return response()->json($conversation->lire($this->sujet($request), $reference, $proposition, $request->routeIs('publications.reponses.*')))
            ->header('Cache-Control', 'private, no-store');
    }

    public function envoyerMessageAide(Request $request, ConversationAideGamad $conversation, string $reference, string $proposition): Response
    {
        $donnees = $request->validate(['texte' => ['required', 'string', 'max:1000']]);
        $conversation->envoyer($this->sujet($request), $reference, $proposition, $donnees['texte'], $request->routeIs('publications.reponses.*'));

        return response()->noContent();
    }

    public function favoris(Request $request, ContenusGamad $contenus): JsonResponse
    {
        return response()->json($contenus->favoris($this->sujet($request)))->header('Cache-Control', 'private, no-store');
    }

    public function ajouterFavori(Request $request, ContenusGamad $contenus, string $reference): Response
    {
        $contenus->modifierFavori($this->sujet($request), $reference, true);

        return response()->noContent();
    }

    public function retirerFavori(Request $request, ContenusGamad $contenus, string $reference): Response
    {
        $contenus->modifierFavori($this->sujet($request), $reference, false);

        return response()->noContent();
    }

    private function sujet(Request $request): string
    {
        return (string) $request->attributes->get('gamad_entite');
    }
}
