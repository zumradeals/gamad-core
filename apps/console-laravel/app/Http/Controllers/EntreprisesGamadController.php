<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\EntreprisesGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class EntreprisesGamadController
{
    public function index(Request $request, EntreprisesGamad $service): Response
    {
        return $this->page('entreprises', ['entreprises' => $service->lister($request->session()->get('gamad_session'))]);
    }

    public function fiche(Request $request, EntreprisesGamad $service, string $reference): Response
    {
        $fiche = $service->fiche($reference, $request->session()->get('gamad_session'));
        abort_if($fiche === null, 404);

        return $this->page('entreprise', $fiche);
    }

    public function formulaire(): Response
    {
        return $this->page('formulaire');
    }

    public function soumettre(Request $request, EntreprisesGamad $service): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:160'],
            'pays' => ['required', 'regex:/^[A-Za-z]{2}$/D'],
            'immatriculation' => ['required', 'string', 'min:3', 'max:100'],
            'secteur' => ['required', 'string', 'min:2', 'max:120'],
            'rccm' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $reference = $service->soumettre($this->sujet($request), $donnees, $donnees['rccm']->get());

        return redirect()->route('entreprises.show', $reference);
    }

    public function revue(EntreprisesGamad $service): Response
    {
        return $this->page('revue', ['entreprises' => $service->revue()]);
    }

    public function justificatif(EntreprisesGamad $service, string $reference): Response
    {
        return response($service->justificatif($reference), 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="justificatif-rccm"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function decider(Request $request, EntreprisesGamad $service, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['action' => ['required', 'in:verified,declined,suspended']]);
        $service->decider($this->sujet($request), $reference, $donnees['action']);

        return redirect()->route('entreprises.revue');
    }

    public function opportunites(EntreprisesGamad $service): Response
    {
        return $this->page('opportunites', ['opportunites' => $service->opportunites()]);
    }

    public function opportunite(Request $request, EntreprisesGamad $service, string $reference): Response
    {
        $fiche = $service->opportunite($reference, $request->session()->get('gamad_session'));
        abort_if($fiche === null, 404);

        return $this->page('opportunite', $fiche);
    }

    public function publier(Request $request, EntreprisesGamad $service, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:1800'],
        ]);
        $opportunite = $service->publier($this->sujet($request), $reference, $donnees['titre'], $donnees['description']);

        return redirect()->route('opportunites.show', $opportunite);
    }

    public function candidater(Request $request, EntreprisesGamad $service, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['message' => ['required', 'string', 'max:1000']]);
        $service->candidater($this->sujet($request), $reference, $donnees['message']);

        return redirect()->route('opportunites.show', $reference);
    }

    public function cloturer(Request $request, EntreprisesGamad $service, string $reference): RedirectResponse
    {
        $service->cloturer($this->sujet($request), $reference);

        return redirect()->route('opportunites.show', $reference);
    }

    private function sujet(Request $request): string
    {
        return (string) $request->attributes->get('gamad_entite');
    }

    private function page(string $ecran, array $donnees = []): Response
    {
        return response()->view('reseau.entreprises', $donnees + compact('ecran'))->header('Cache-Control', 'private, no-store');
    }
}
