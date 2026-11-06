<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\EspacesGamad;
use App\Application\Reseau\EtapesProjetGamad;
use App\Application\Reseau\MembresEspacesGamad;
use App\Application\Reseau\ParticipationsGamad;
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

        return $this->page('projet', compact('projet') + ['etatsEtape' => EtapesProjetGamad::ETATS]);
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

    public function modifierProjet(Request $request, EspacesGamad $espaces, string $reference): Response
    {
        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:120'],
            'dossier' => ['required', 'string', 'max:4000'],
        ]);
        $espaces->modifierProjet($this->sujet($request), $reference, $donnees['titre'], $donnees['dossier']);

        return response()->noContent();
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

    public function adhesion(Request $request, MembresEspacesGamad $membres, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['action' => ['required', 'in:rejoindre,quitter']]);
        $membres->adhesion($this->sujet($request), $reference, $donnees['action'] === 'rejoindre');

        return redirect()->route('zumras.show', $reference);
    }

    public function inviter(Request $request, MembresEspacesGamad $membres, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['profil' => ['required', 'regex:/^[a-f0-9]{24}$/D']]);
        $membres->inviter($this->sujet($request), $reference, $donnees['profil']);

        return redirect()->route('projets.show', $reference);
    }

    public function membreZumra(Request $request, MembresEspacesGamad $membres, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'cible' => ['required', 'string', 'max:64'],
            'action' => ['required', 'in:accepter,refuser,retirer'],
        ]);
        $membres->decider($this->sujet($request), $reference, $donnees['cible'], true, $donnees['action']);

        return redirect()->route('zumras.show', $reference);
    }

    public function membreProjet(Request $request, MembresEspacesGamad $membres, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'cible' => ['nullable', 'string', 'max:64'],
            'action' => ['required', 'in:accepter,refuser,retirer'],
        ]);
        $membres->decider($this->sujet($request), $reference, $donnees['cible'] ?? null, false, $donnees['action']);

        return redirect()->route('projets.index');
    }

    public function deleguer(Request $request, MembresEspacesGamad $membres, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['cible' => ['required', 'string', 'max:64'], 'action' => ['required', 'in:nommer,revoquer']]);
        $membres->deleguer($this->sujet($request), $reference, $donnees['cible'], $donnees['action'] === 'nommer');

        return redirect()->route('zumras.show', $reference);
    }

    public function etape(Request $request, EtapesProjetGamad $etapes, string $reference, ?string $etape = null): RedirectResponse
    {
        $donnees = $etape === null
            ? $request->validate(['titre' => ['required', 'string', 'max:80']])
            : $request->validate([
                'action' => ['required', 'in:enregistrer,monter,descendre,retirer'],
                'titre' => ['required_if:action,enregistrer', 'nullable', 'string', 'max:80'],
                'etat' => ['required_if:action,enregistrer', 'nullable', 'in:todo,in_progress,done'],
            ]);
        $etapes->modifier($this->sujet($request), $reference, $etape, $donnees);

        return redirect()->route('projets.show', $reference);
    }

    public function modeAdhesion(Request $request, MembresEspacesGamad $membres, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['mode' => ['required', 'in:open,approval']]);
        $membres->configurerAdhesion($this->sujet($request), $reference, $donnees['mode']);

        return redirect()->route('zumras.show', $reference);
    }

    public function participation(Request $request, ParticipationsGamad $participations, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'action' => ['required', 'in:proposer,retirer,accepter,refuser'],
            'cible' => ['required_if:action,accepter,refuser', 'nullable', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:280'],
        ]);
        $participations->agir($this->sujet($request), $reference, $donnees['action'], $donnees['cible'] ?? null, $donnees['message'] ?? null);

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
