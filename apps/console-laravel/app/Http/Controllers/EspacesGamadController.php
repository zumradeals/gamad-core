<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\EspacesGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class EspacesGamadController
{
    public function zumras(EspacesGamad $espaces): Response
    {
        return $this->page('zumras', ['zumras' => $espaces->zumras()]);
    }

    public function zumra(Request $request, EspacesGamad $espaces, string $reference): Response
    {
        $fiche = $espaces->zumra($reference, $request->session()->get('gamad_session'));
        abort_if($fiche === null, 404);

        return $this->page('zumra', $fiche);
    }

    public function projets(Request $request, EspacesGamad $espaces): Response
    {
        return $this->page('projets', ['projets' => $espaces->projets($request->session()->get('gamad_session'))]);
    }

    public function projet(Request $request, EspacesGamad $espaces, string $reference): Response
    {
        $projet = $espaces->projet($reference, $request->session()->get('gamad_session'));
        abort_if($projet === null, 404);

        return $this->page('projet', compact('projet'));
    }

    public function nouveauProjet(EspacesGamad $espaces): Response
    {
        return $this->page('creer-projet', ['zumras' => $espaces->zumras()]);
    }

    public function nouvelleZumra(): Response
    {
        return $this->page('creer-zumra');
    }

    public function creerZumra(Request $request, EspacesGamad $espaces): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'presentation' => ['required', 'string', 'max:500'],
        ]);
        $reference = $espaces->creerZumra($this->sujet($request), $donnees['nom'], $donnees['presentation']);

        return redirect()->route('zumras.show', $reference);
    }

    public function creerProjet(Request $request, EspacesGamad $espaces): RedirectResponse
    {
        $donnees = $request->validate([
            'zumra' => ['required', 'string', 'regex:/^[a-f0-9]{24}$/D'],
            'titre' => ['required', 'string', 'max:120'],
            'dossier' => ['required', 'string', 'max:4000'],
        ]);
        $reference = $espaces->creerProjet($this->sujet($request), $donnees['zumra'], $donnees['titre'], $donnees['dossier']);

        return redirect()->route('projets.show', $reference);
    }

    public function accueil(Request $request, EspacesGamad $espaces, string $reference, string $projet): RedirectResponse
    {
        $donnees = $request->validate(['decision' => ['required', 'in:accepter,refuser']]);
        $espaces->deciderAccueil($this->sujet($request), $reference, $projet, $donnees['decision'] === 'accepter');

        return redirect()->route('zumras.show', $reference);
    }

    public function vitrine(Request $request, EspacesGamad $espaces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'action' => ['required', 'in:publier,retirer'],
            'vitrine' => ['required_if:action,publier', 'nullable', 'string', 'max:500'],
        ]);
        $espaces->publierVitrine($this->sujet($request), $reference, $donnees['action'] === 'publier' ? $donnees['vitrine'] : null);

        return redirect()->route('projets.show', $reference);
    }

    private function sujet(Request $request): string
    {
        return (string) $request->attributes->get('gamad_entite');
    }

    private function page(string $ecran, array $donnees = []): Response
    {
        return response()->view('reseau.espaces', $donnees + compact('ecran'))->header('Cache-Control', 'private, no-store');
    }
}
