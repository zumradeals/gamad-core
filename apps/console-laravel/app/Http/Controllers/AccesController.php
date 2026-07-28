<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Gamad\RegistreAcces\Ctr05;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page de connexion de la console (CAP-CORE-005, contrat CTR-05).
 *
 * Ouvrir une session établit QUI L'ON EST, non ce que l'on peut faire : les
 * droits relèvent de CAP-CORE-004, à établir. Tant qu'elle n'existe pas, une
 * session ne confère qu'un accès en lecture — la capacité FERME une porte,
 * elle n'en ouvre aucune.
 */
final class AccesController
{
    public function formulaire(): View
    {
        return view('acces.connexion');
    }

    public function connecter(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'entite' => ['required', 'string', 'max:64'],
            'secret' => ['required', 'string'],
        ]);

        $session = (new Ctr05(Magasin::connecter()))
            ->etablirSession($donnees['entite'], $donnees['secret']);

        if ($session === null) {
            // Aucune distinction entre entité inconnue et secret erroné.
            return back()->withErrors(['entite' => 'Identifiant ou secret refusé.']);
        }

        $request->session()->regenerate();
        $request->session()->put('gamad_session', $session['session']);

        return redirect()->intended('/');
    }

    public function deconnecter(Request $request): RedirectResponse
    {
        $reference = $request->session()->get('gamad_session');
        if (is_string($reference)) {
            (new Ctr05(Magasin::connecter()))->revoquerSession($reference);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/connexion');
    }
}
