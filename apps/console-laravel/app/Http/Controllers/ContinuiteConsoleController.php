<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Continuite\Continuite;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Écran de continuité (CAP-CORE-019).
 *
 * Voir, configurer, déclencher — sans ligne de commande. Le contrôleur
 * n'exécute rien : il délègue au cas d'usage, qui écrit des réglages et dépose
 * des demandes qu'une unité systemd sert avec ses propres droits.
 */
final class ContinuiteConsoleController
{
    public function index(Request $request, Continuite $continuite): Response
    {
        $acteur = (string) $request->attributes->get('gamad_entite');

        // La page porte une destination et l'état des secrets : elle ne doit
        // être conservée ni par le navigateur ni par un intermédiaire.
        return response()->view('continuite.index', [
            'etat' => $continuite->etat(),
            'peutAdministrer' => $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION,
            'acteur' => $acteur,
            'modesTls' => Continuite::MODES_TLS,
        ], 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate', 'Pragma' => 'no-cache']);
    }

    public function configurer(Request $request, Continuite $continuite): RedirectResponse
    {
        $donnees = $request->validate([
            'hote' => ['required', 'string', 'max:255'],
            'chemin' => ['nullable', 'string', 'max:255'],
            'utilisateur' => ['required', 'string', 'max:128'],
            'secret' => ['nullable', 'string', 'max:512'],
            'tls' => ['required', 'string', 'in:'.implode(',', Continuite::MODES_TLS)],
            'retention' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $execution = $continuite->configurer(
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
        );

        if ($execution['statut'] !== 200) {
            return back()->withInput($request->except('secret'))->withErrors([
                'continuite' => (string) ($execution['corps']['message']
                    ?? $execution['corps']['decision']['motif']
                    ?? 'Le Core a refusé cette configuration.'),
            ]);
        }

        $redirection = redirect()
            ->route('console.continuite.index')
            ->with('succes', 'Destination de sauvegarde enregistrée.');

        // La phrase de chiffrement n'existe qu'une fois : sans elle, les copies
        // sont illisibles le jour où le serveur est perdu.
        if (($execution['corps']['phrase_chiffrement'] ?? null) !== null) {
            $redirection->with('phrase_chiffrement', $execution['corps']['phrase_chiffrement']);
        }

        return $redirection;
    }

    public function declencher(Request $request, Continuite $continuite, string $operation): RedirectResponse
    {
        $execution = $continuite->demander(
            $operation,
            (string) $request->attributes->get('gamad_entite'),
        );

        if ($execution['statut'] !== 202) {
            return back()->withErrors([
                'continuite' => (string) ($execution['corps']['message']
                    ?? $execution['corps']['decision']['motif']
                    ?? 'Le Core a refusé cette opération.'),
            ]);
        }

        return redirect()
            ->route('console.continuite.index')
            ->with('succes', $operation === 'sauvegarde'
                ? 'Sauvegarde demandée. Elle démarre dans quelques secondes ; rechargez la page pour suivre.'
                : 'Exercice de restauration demandé. Rechargez la page dans une minute pour voir le résultat.');
    }
}
