<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Acces\MoyensAcces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Écran « Mon accès » (CAP-CORE-005).
 *
 * Chacun y voit et y gère ses propres moyens d'accès. Le sujet vient de la
 * session et n'est jamais accepté depuis la requête : on ne gère pas l'accès
 * d'autrui, même en le demandant poliment.
 */
final class AccesConsoleController
{
    public function index(Request $request, MoyensAcces $moyens): Response
    {
        $entite = (string) $request->attributes->get('gamad_entite');

        return response()->view('acces/moyens', [
            'inventaire' => $moyens->inventaire($entite),
            'entite' => $entite,
            'assurance' => (string) $request->attributes->get('gamad_assurance', ''),
        ], 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate', 'Pragma' => 'no-cache']);
    }

    public function engendrerCodes(Request $request, MoyensAcces $moyens): RedirectResponse
    {
        $entite = (string) $request->attributes->get('gamad_entite');
        $execution = $moyens->engendrerCodes($entite, $entite);

        if ($execution['statut'] !== 201) {
            return back()->withErrors(['acces' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.acces.index')
            ->with('succes', 'Codes de secours engendrés. Les précédents ne valent plus.')
            ->with('codes_secours', $execution['corps']['codes']);
    }

    public function autoriserPasskey(Request $request, MoyensAcces $moyens): RedirectResponse
    {
        $donnees = $request->validate([
            'code_secours' => ['nullable', 'string', 'max:64'],
        ]);

        $entite = (string) $request->attributes->get('gamad_entite');
        $execution = $moyens->autoriserFacteurFort(
            $entite,
            $entite,
            (string) $request->attributes->get('gamad_assurance', ''),
            (string) ($donnees['code_secours'] ?? ''),
        );

        if ($execution['statut'] !== 201) {
            return back()->withErrors(['acces' => $this->motif($execution['corps'])]);
        }

        // Le jeton n'est ni affiché ni conservé : il passe directement au
        // formulaire d'enrôlement, en flash, et disparaît après usage.
        return redirect()
            ->route('passkeys.enrolement')
            ->with('succes', 'Autorisation accordée. Ajoutez votre appareil ou votre clé.')
            ->with('passkey_entite', $entite)
            ->with('passkey_jeton', $execution['corps']['jeton']);
    }

    public function retirer(Request $request, MoyensAcces $moyens): RedirectResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
        ]);

        $entite = (string) $request->attributes->get('gamad_entite');
        $execution = $moyens->retirer($entite, $donnees['reference'], $entite);

        if ($execution['statut'] !== 200) {
            return back()->withErrors(['acces' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.acces.index')
            ->with('succes', 'Moyen d’accès retiré.');
    }

    /** @param array<string,mixed> $corps */
    private function motif(array $corps): string
    {
        return (string) ($corps['message']
            ?? $corps['decision']['motif']
            ?? 'Le Core a refusé cette opération.');
    }
}
