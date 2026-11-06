<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Comptes\ParcoursInscriptionGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class InscriptionGamadController
{
    private const ATTENTE = 'gamad_inscription_en_attente';

    public function formulaire(Request $request): Response|RedirectResponse
    {
        if ($request->session()->has(self::ATTENTE)) {
            return redirect()->route('inscription.verification');
        }

        return response()->view('acces.inscription')->header('Cache-Control', 'no-store');
    }

    public function inscrire(Request $request, ParcoursInscriptionGamad $parcours): RedirectResponse
    {
        if ($request->session()->has(self::ATTENTE)) {
            return redirect()->route('inscription.verification');
        }

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:256'],
            'identifiant' => ['required', 'email', 'max:256'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'max:4096', 'confirmed'],
        ]);

        $resultat = $parcours->inscrire(
            $donnees + ['type_identifiant' => 'EMAIL'],
            ParcoursInscriptionGamad::PRODUIT_WEB,
            null,
        );
        $compte = $resultat['corps']['compte'] ?? [];
        $verification = $resultat['corps']['verification']['reference'] ?? null;
        if (!is_string($verification) || !isset($compte['identite'], $compte['identifiant_reference'])) {
            return back()->withErrors(['inscription' => 'Impossible de commencer l’inscription avec ces informations.'])
                ->withInput($request->only('nom', 'identifiant'));
        }

        $request->session()->regenerate();
        $request->session()->put(self::ATTENTE, [
            'identite' => $compte['identite'],
            'identifiant_reference' => $compte['identifiant_reference'],
            'verification_reference' => $verification,
        ]);

        return redirect()->route('inscription.verification')->with(
            'inscription_statut',
            $resultat['statut'] === 201
                ? 'Un code a été envoyé à votre adresse e-mail.'
                : 'L’envoi du code a échoué. Vous pouvez demander un renvoi sur cette page.',
        );
    }

    public function verification(Request $request): Response|RedirectResponse
    {
        if (!$request->session()->has(self::ATTENTE)) {
            return redirect()->route('inscription.formulaire');
        }

        return response()->view('acces.verification-inscription')->header('Cache-Control', 'no-store');
    }

    public function verifier(Request $request, ParcoursInscriptionGamad $parcours): RedirectResponse
    {
        $attente = $request->session()->get(self::ATTENTE);
        if (!is_array($attente)) {
            return redirect()->route('inscription.formulaire');
        }

        $donnees = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D']]);
        $resultat = $parcours->verifier($attente + $donnees, ParcoursInscriptionGamad::PRODUIT_WEB, null);
        if ($resultat['statut'] !== 200) {
            return back()->withErrors(['verification' => $resultat['corps']['message']]);
        }

        $request->session()->forget(self::ATTENTE);
        $request->session()->put('reseau.url.intended', route('reseau.espace'));

        return redirect()->route('reseau.connexion')->with('inscription_statut', 'Adresse vérifiée. Connectez-vous pour accéder au Réseau.');
    }

    public function renvoyer(Request $request, ParcoursInscriptionGamad $parcours): RedirectResponse
    {
        $attente = $request->session()->get(self::ATTENTE);
        if (!is_array($attente)) {
            return redirect()->route('inscription.formulaire');
        }

        $donnees = $request->validate(['destination' => ['required', 'email', 'max:256']]);
        $resultat = $parcours->renvoyer($attente + $donnees, ParcoursInscriptionGamad::PRODUIT_WEB, null);
        if ($resultat['statut'] !== 201) {
            return back()->withErrors(['verification' => $resultat['corps']['message']]);
        }

        $request->session()->put(self::ATTENTE.'.verification_reference', $resultat['corps']['verification']['reference']);

        return redirect()->route('inscription.verification')->with('inscription_statut', 'Un nouveau code a été envoyé.');
    }
}
