<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Comptes\ConfigurationCanauxVerification;
use App\Application\Comptes\LivrerVerification;
use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VerificationChannelConsoleController
{
    public function index(Request $request, ConfigurationCanauxVerification $configuration): View
    {
        $this->autoriser($request);

        return view('parametres.verification-comptes', [
            'configuration' => $configuration->lirePourConsole(),
        ]);
    }

    public function update(Request $request, ConfigurationCanauxVerification $configuration): RedirectResponse
    {
        $acteur = $this->autoriser($request);
        $donnees = $request->validate([
            'email_enabled' => ['nullable', 'boolean'],
            'email_driver' => ['required', 'in:smtp'],
            'email_host' => ['nullable', 'string', 'max:255'],
            'email_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'email_scheme' => ['required', 'in:smtp,smtps'],
            'email_username' => ['nullable', 'string', 'max:255'],
            'email_password' => ['nullable', 'string', 'max:2048'],
            'email_from_address' => ['nullable', 'email', 'max:255'],
            'email_from_name' => ['nullable', 'string', 'max:120'],
            'email_subject' => ['nullable', 'string', 'max:255'],
            'sms_enabled' => ['nullable', 'boolean'],
            'sms_relay_url' => ['nullable', 'url:https', 'max:1000'],
            'sms_relay_token' => ['nullable', 'string', 'max:4096'],
            'sms_sender' => ['nullable', 'string', 'max:32'],
            'sms_timeout' => ['nullable', 'integer', 'min:2', 'max:15'],
        ]);
        $donnees['email_enabled'] = $request->boolean('email_enabled');
        $donnees['sms_enabled'] = $request->boolean('sms_enabled');

        try {
            $configuration->enregistrer($donnees);
            $this->journaliser($acteur, $donnees);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['configuration' => $e->getMessage()]);
        } catch (\Throwable) {
            return back()->withInput()->withErrors(['configuration' => 'La configuration n’a pas pu être enregistrée.']);
        }

        return redirect()
            ->route('console.parametres.verification.index')
            ->with('succes', 'Configuration email et SMS enregistrée.');
    }

    public function testEmail(Request $request, LivrerVerification $livraison): RedirectResponse
    {
        $this->autoriser($request);
        $donnees = $request->validate(['destination_email' => ['required', 'email', 'max:255']]);
        $resultat = $livraison->executer(
            'EMAIL',
            (string) $donnees['destination_email'],
            (string) random_int(100000, 999999),
            gmdate('c', time() + 600),
        );

        if (($resultat['livree'] ?? false) !== true) {
            return back()->withErrors(['test_email' => $this->messageErreur((string) ($resultat['motif'] ?? 'ECHEC_LIVRAISON_EMAIL'))]);
        }

        return back()->with('succes', 'Email de test envoyé. Vérifiez la boîte de réception.');
    }

    public function testSms(Request $request, LivrerVerification $livraison): RedirectResponse
    {
        $this->autoriser($request);
        $donnees = $request->validate(['destination_sms' => ['required', 'string', 'max:32']]);
        $resultat = $livraison->executer(
            'TELEPHONE',
            (string) $donnees['destination_sms'],
            (string) random_int(100000, 999999),
            gmdate('c', time() + 600),
        );

        if (($resultat['livree'] ?? false) !== true) {
            return back()->withErrors(['test_sms' => $this->messageErreur((string) ($resultat['motif'] ?? 'ECHEC_LIVRAISON_SMS'))]);
        }

        return back()->with('succes', 'SMS de test remis au relais.');
    }

    private function autoriser(Request $request): string
    {
        $acteur = trim((string) $request->attributes->get('gamad_entite', ''));
        abort_unless(hash_equals('AUT-GAMAD-001', $acteur), 403, 'Réservé à l’autorité fondatrice.');

        return $acteur;
    }

    /** @param array<string,mixed> $donnees */
    private function journaliser(string $acteur, array $donnees): void
    {
        try {
            (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'CONFIGURATION',
                'type' => 'CANAUX_VERIFICATION_CONFIGURES',
                'acteur' => $acteur,
                'action' => 'configurer les canaux de vérification des Comptes GAMAD',
                'ressource' => 'COMPTE_GAMAD',
                'decision' => 'EXECUTEE',
                'donnees' => [
                    'email_active' => (bool) ($donnees['email_enabled'] ?? false),
                    'email_driver' => (string) ($donnees['email_driver'] ?? 'smtp'),
                    'sms_actif' => (bool) ($donnees['sms_enabled'] ?? false),
                    'sms_driver' => 'relay',
                ],
            ]);
        } catch (\Throwable) {
            // La valeur secrète n'est jamais journalisée. Un échec du journal
            // ne doit pas provoquer une seconde écriture de configuration.
        }
    }

    private function messageErreur(string $motif): string
    {
        return match ($motif) {
            'EMAIL_NON_CONFIGURE' => 'Activez et renseignez d’abord le canal email.',
            'ECHEC_LIVRAISON_EMAIL' => 'Le serveur email a refusé ou interrompu l’envoi. Vérifiez les paramètres SMTP.',
            'SMS_NON_CONFIGURE', 'RELAIS_SMS_NON_CONFIGURE' => 'Activez et renseignez d’abord le relais SMS.',
            'RELAIS_SMS_INDISPONIBLE' => 'Le relais SMS ne répond pas.',
            'ECHEC_LIVRAISON_SMS' => 'Le relais SMS a refusé l’envoi.',
            default => 'Le test d’envoi a échoué.',
        };
    }
}
