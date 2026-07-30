<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Page de connexion de la console (CAP-CORE-005, contrat CTR-05).
 *
 * Ouvrir une session établit QUI L'ON EST, non ce que l'on peut faire : les
 * droits relèvent de CAP-CORE-004. Une session seule ne confère qu'un accès en
 * lecture — toute écriture exige une permission distincte et vérifiée.
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
            'secret' => ['required', 'string', 'max:4096'],
        ]);

        $cleLimite = $this->cleLimite($request, $donnees['entite']);
        $correlation = 'REQ-' . Str::upper((string) Str::uuid());

        try {
            if (RateLimiter::tooManyAttempts($cleLimite, 5)) {
                $attente = max(1, RateLimiter::availableIn($cleLimite));
                $this->journaliserConnexion(
                    $request,
                    $donnees['entite'],
                    null,
                    'BLOQUEE',
                    'limite de tentatives atteinte',
                    $correlation,
                );

                return back()
                    ->withInput(['entite' => $donnees['entite']])
                    ->withErrors([
                        'entite' => "Trop de tentatives. Réessayez dans {$attente} seconde(s).",
                    ])
                    ->withHeaders(['Retry-After' => (string) $attente]);
            }

            RateLimiter::hit($cleLimite, 60);
        } catch (\Throwable) {
            return back()
                ->withInput(['entite' => $donnees['entite']])
                ->withErrors([
                    'entite' => 'Connexion temporairement indisponible. Réessayez dans un instant.',
                ]);
        }

        $ctr = null;
        $session = null;
        try {
            $ctr = new Ctr16(Magasin::connecter());
            $session = $ctr->etablirSession($donnees['entite'], $donnees['secret']);
        } catch (\Throwable) {
            try {
                $this->journaliserConnexion(
                    $request,
                    $donnees['entite'],
                    null,
                    'INDISPONIBLE',
                    'magasin d’accès indisponible',
                    $correlation,
                );
            } catch (\Throwable) {
                // Les deux magasins sont indisponibles : l'accès reste fermé.
            }

            return back()
                ->withInput(['entite' => $donnees['entite']])
                ->withErrors([
                    'entite' => 'Connexion temporairement indisponible. Réessayez dans un instant.',
                ]);
        }

        try {
            $this->journaliserConnexion(
                $request,
                $donnees['entite'],
                $session['entite'] ?? null,
                $session === null ? 'REFUSEE' : 'ACCEPTEE',
                $session === null ? 'identifiant ou secret refusé' : null,
                $correlation,
                $session['assurance'] ?? null,
            );
        } catch (\Throwable) {
            if ($session !== null && $ctr instanceof Ctr16) {
                $ctr->revoquerSession((string) $session['session']);
            }

            return back()
                ->withInput(['entite' => $donnees['entite']])
                ->withErrors([
                    'entite' => 'Connexion temporairement indisponible. Aucune session sans preuve d’audit.',
                ]);
        }

        if ($session === null) {
            // Aucune distinction entre entité inconnue et secret erroné.
            return back()
                ->withInput(['entite' => $donnees['entite']])
                ->withErrors(['entite' => 'Identifiant ou secret refusé.']);
        }

        try {
            RateLimiter::clear($cleLimite);
        } catch (\Throwable) {
            // La session prouvée reste valable ; la limite demeure plus stricte.
        }
        $request->session()->regenerate();
        $request->session()->put('gamad_session', $session['session']);

        return redirect()->intended('/');
    }

    public function deconnecter(Request $request): RedirectResponse
    {
        $reference = $request->session()->get('gamad_session');
        if (is_string($reference)) {
            (new Ctr16(Magasin::connecter()))->revoquerSession($reference);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/connexion');
    }

    private function cleLimite(Request $request, string $entite): string
    {
        return 'gamad-console-login:' . hash_hmac(
            'sha256',
            Str::lower(trim($entite)) . '|' . (string) $request->ip(),
            (string) config('app.key'),
        );
    }

    private function journaliserConnexion(
        Request $request,
        string $entitePresentee,
        ?string $acteur,
        string $decision,
        ?string $motif,
        string $correlation,
        ?string $assurance = null,
    ): void {
        (new Journal(JournalMagasin::connecter()))->enregistrer([
            'categorie' => 'SECURITE',
            'type' => 'AUTHENTIFICATION_CONSOLE',
            'acteur' => $acteur,
            'action' => 'ouvrir une session console',
            'ressource' => hash_hmac(
                'sha256',
                Str::lower(trim($entitePresentee)),
                (string) config('app.key'),
            ),
            'decision' => $decision,
            'motif' => $motif,
            'correlation_id' => $correlation,
            'donnees' => [
                'assurance' => $assurance,
                'adresse_ip_empreinte' => is_string($request->ip())
                    ? hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'))
                    : null,
            ],
        ]);
    }
}
