<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\MessagerieGamad;
use App\Application\Reseau\ModerationGamad;
use App\Application\Reseau\NotificationsGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class InteractionsGamadController
{
    public function conversation(Request $request, MessagerieGamad $messagerie, string $reference): Response
    {
        $sujet = $this->sujet($request);

        return $this->page('conversation', $messagerie->lire($sujet, $reference) + compact('sujet', 'reference'));
    }

    public function envoyer(Request $request, MessagerieGamad $messagerie, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['texte' => ['required', 'string', 'max:1000']]);
        $messagerie->envoyer($this->sujet($request), $reference, $donnees['texte']);

        return redirect()->route('conversations.show', $reference);
    }

    public function notifications(Request $request, NotificationsGamad $notifications): Response
    {
        return $this->page('notifications', ['notifications' => $notifications->lister($this->sujet($request))]);
    }

    public function lireNotification(Request $request, NotificationsGamad $notifications, string $reference): RedirectResponse
    {
        $notifications->lire($this->sujet($request), $reference);

        return redirect()->route('notifications');
    }

    public function signaler(Request $request, ModerationGamad $moderation, string $reference, ?string $commentaire = null): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['required', Rule::in(array_keys(ModerationGamad::MOTIFS))]]);
        $moderation->signaler(
            $this->sujet($request), $request->session()->get('gamad_session'), $reference, $commentaire, $donnees['motif'],
        );

        return redirect()->route('publications.show', $reference)->with('signalement_envoye', true);
    }

    public function moderation(ModerationGamad $moderation): Response
    {
        return $this->page('moderation', ['signalements' => $moderation->lister(), 'motifs' => ModerationGamad::MOTIFS]);
    }

    public function traiter(Request $request, ModerationGamad $moderation, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['action' => ['required', 'in:masquer,classer']]);
        $moderation->traiter($this->sujet($request), $reference, $donnees['action'] === 'masquer');

        return redirect()->route('reseau.moderation');
    }

    private function sujet(Request $request): string
    {
        return (string) $request->attributes->get('gamad_entite');
    }

    private function page(string $ecran, array $donnees): Response
    {
        return response()->view('reseau.interactions', $donnees + compact('ecran'))->header('Cache-Control', 'private, no-store');
    }
}
