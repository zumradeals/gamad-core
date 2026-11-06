<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\ContactsGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ContactGamadController
{
    public function formulaire(): Response
    {
        return $this->page('formulaire');
    }

    public function envoyer(Request $request, ContactsGamad $contacts): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:254'],
            'sujet' => ['required', Rule::in(array_keys(ContactsGamad::SUJETS))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        if (!$request->filled('website')) {
            $email = hash('sha256', mb_strtolower($donnees['email']));
            $limites = ['heure-ip:'.$request->ip() => [3, 3600], 'heure-email:'.$email => [3, 3600], 'jour-ip:'.$request->ip() => [10, 86400]];
            foreach ($limites as $cle => [$maximum, $delai]) {
                if (RateLimiter::tooManyAttempts('contact:'.$cle, $maximum)) {
                    throw ValidationException::withMessages(['message' => 'Vous avez envoyé plusieurs messages. Réessayez plus tard.']);
                }
            }
            foreach ($limites as $cle => [$maximum, $delai]) {
                RateLimiter::hit('contact:'.$cle, $delai);
            }
            $contacts->envoyer($donnees);
        }

        return redirect()->route('reseau.contact')->with('contact_envoye', true);
    }

    public function liste(ContactsGamad $contacts): Response
    {
        return $this->page('liste', ['messages' => $contacts->lister()]);
    }

    public function ouvrir(Request $request, ContactsGamad $contacts, string $reference): Response
    {
        return $this->page('message', ['message' => $contacts->ouvrir($this->acteur($request), $reference)]);
    }

    public function traiter(Request $request, ContactsGamad $contacts, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['action' => ['required', 'in:traiter,supprimer']]);
        $contacts->traiter($this->acteur($request), $reference, $donnees['action'] === 'supprimer');

        return redirect()->route('reseau.contact.console');
    }

    private function acteur(Request $request): string
    {
        return (string) $request->attributes->get('gamad_entite');
    }

    private function page(string $ecran, array $donnees = []): Response
    {
        return response()->view('reseau.contact', $donnees + compact('ecran') + ['sujets' => ContactsGamad::SUJETS])
            ->header('Cache-Control', 'private, no-store');
    }
}
