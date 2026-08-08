<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Livraison souveraine d'un code de verification.
 *
 * Le satellite ne recoit jamais le code. Il transmet uniquement la demande
 * de creation du compte ; le Core remet ensuite la preuve directement au
 * canal humain concerne.
 */
final class LivrerVerification
{
    public function __construct(
        private readonly ?ConfigurationCanauxVerification $configuration = null,
    ) {
    }

    /**
     * `$contexte` ne sert qu'à l'observabilité d'un échec (journal
     * opérationnel) : `produit`, `identifiant_reference`, `verification_reference`,
     * `correlation`. Aucune de ces clés n'est un secret ni une donnée
     * personnelle ; absentes, la journalisation se contente de moins de
     * contexte, elle ne bloque jamais la livraison.
     *
     * @param array{produit?:?string,identifiant_reference?:?string,verification_reference?:?string,correlation?:mixed} $contexte
     * @return array{livree:bool,canal:string,motif?:string}
     */
    public function executer(string $type, string $destination, string $code, string $expireLe, array $contexte = []): array
    {
        $type = strtoupper(trim($type));

        return match ($type) {
            'EMAIL' => $this->email($destination, $code, $expireLe, $contexte),
            'TELEPHONE' => $this->sms($destination, $code, $expireLe, $contexte),
            default => ['livree' => false, 'canal' => $type, 'motif' => 'CANAL_NON_SUPPORTE'],
        };
    }

    /** @return array{livree:bool,canal:string,motif?:string} */
    private function email(string $adresse, string $code, string $expireLe, array $contexte): array
    {
        $canaux = ($this->configuration ?? new ConfigurationCanauxVerification())->lire();
        $console = $canaux['email'] ?? [];
        $viaConsole = ($console['enabled'] ?? false) === true;

        if (!$viaConsole && config('gamad_verification.email.enabled') !== true) {
            $this->journaliserEchec('EMAIL', 'EMAIL_NON_CONFIGURE', null, $contexte);
            return ['livree' => false, 'canal' => 'EMAIL', 'motif' => 'EMAIL_NON_CONFIGURE'];
        }

        if ($viaConsole) {
            $driver = (string) ($console['driver'] ?? 'smtp');
            if ($driver !== 'smtp') {
                $this->journaliserEchec('EMAIL', 'TRANSPORT_EMAIL_NON_SUPPORTE', null, $contexte);
                return ['livree' => false, 'canal' => 'EMAIL', 'motif' => 'TRANSPORT_EMAIL_NON_SUPPORTE'];
            }

            config([
                'mail.mailers.gamad_console_smtp' => [
                    'transport' => 'smtp',
                    'scheme' => ($console['scheme'] ?? '') !== '' ? (string) $console['scheme'] : null,
                    'host' => (string) ($console['host'] ?? ''),
                    'port' => (int) ($console['port'] ?? 587),
                    'username' => ($console['username'] ?? '') !== '' ? (string) $console['username'] : null,
                    'password' => ($console['password'] ?? '') !== '' ? (string) $console['password'] : null,
                    'timeout' => 10,
                    'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
                ],
                'mail.from.address' => (string) ($console['from_address'] ?? ''),
                'mail.from.name' => (string) ($console['from_name'] ?? 'GAMAD'),
            ]);
            $mailer = 'gamad_console_smtp';
            $sujet = (string) ($console['subject'] ?? 'Votre code de vérification GAMAD');
        } else {
            $mailer = (string) config('gamad_verification.email.mailer', 'log');
            if (app()->environment('production') && in_array($mailer, ['log', 'array'], true)) {
                $this->journaliserEchec('EMAIL', 'TRANSPORT_EMAIL_NON_LIVRABLE', null, $contexte);
                return ['livree' => false, 'canal' => 'EMAIL', 'motif' => 'TRANSPORT_EMAIL_NON_LIVRABLE'];
            }
            $sujet = (string) config('gamad_verification.email.subject', 'Votre code de verification GAMAD');
        }

        $message = "Votre code de verification GAMAD est : {$code}\n\n"
            . "Ce code expire le {$expireLe}.\n"
            . "Ne transmettez ce code a personne.";

        try {
            Mail::mailer($mailer)->raw($message, static function ($mail) use ($adresse, $sujet): void {
                $mail->to($adresse)->subject($sujet);
            });
        } catch (\Throwable $e) {
            $this->journaliserEchec('EMAIL', 'ECHEC_LIVRAISON_EMAIL', $this->extraireCodeSmtp($e), $contexte);
            return ['livree' => false, 'canal' => 'EMAIL', 'motif' => 'ECHEC_LIVRAISON_EMAIL'];
        }

        return ['livree' => true, 'canal' => 'EMAIL'];
    }

    /** @return array{livree:bool,canal:string,motif?:string} */
    private function sms(string $telephone, string $code, string $expireLe, array $contexte): array
    {
        $canaux = ($this->configuration ?? new ConfigurationCanauxVerification())->lire();
        $console = $canaux['sms'] ?? [];
        $viaConsole = ($console['enabled'] ?? false) === true;

        if (!$viaConsole && config('gamad_verification.sms.enabled') !== true) {
            $this->journaliserEchec('TELEPHONE', 'SMS_NON_CONFIGURE', null, $contexte);
            return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'SMS_NON_CONFIGURE'];
        }

        if ($viaConsole) {
            $driver = (string) ($console['driver'] ?? 'relay');
            $url = trim((string) ($console['relay_url'] ?? ''));
            $token = trim((string) ($console['relay_token'] ?? ''));
            $timeout = max(2, (int) ($console['timeout'] ?? 8));
            $sender = (string) ($console['sender'] ?? 'GAMAD');
        } else {
            $driver = (string) config('gamad_verification.sms.driver', '');
            $url = trim((string) config('gamad_verification.sms.relay_url', ''));
            $token = trim((string) config('gamad_verification.sms.relay_token', ''));
            $timeout = max(1, (int) config('gamad_verification.sms.timeout_seconds', 5));
            $sender = (string) config('gamad_verification.sms.sender', 'GAMAD');
        }

        if ($driver === 'array' && app()->environment('testing')) {
            return ['livree' => true, 'canal' => 'TELEPHONE'];
        }
        if ($driver !== 'relay') {
            $this->journaliserEchec('TELEPHONE', 'TRANSPORT_SMS_NON_SUPPORTE', null, $contexte);
            return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'TRANSPORT_SMS_NON_SUPPORTE'];
        }
        if ($url === '' || $token === '' || !str_starts_with($url, 'https://')) {
            $this->journaliserEchec('TELEPHONE', 'RELAIS_SMS_NON_CONFIGURE', null, $contexte);
            return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'RELAIS_SMS_NON_CONFIGURE'];
        }

        $message = "GAMAD : votre code de verification est {$code}. "
            . "Il expire le {$expireLe}. Ne le partagez avec personne.";

        try {
            $reponse = Http::asJson()
                ->acceptJson()
                ->withToken($token)
                ->timeout($timeout)
                ->post($url, [
                    'destination' => $telephone,
                    'sender' => $sender,
                    'message' => $message,
                    'purpose' => 'GAMAD_IDENTITY_VERIFICATION',
                ]);
        } catch (\Throwable) {
            $this->journaliserEchec('TELEPHONE', 'RELAIS_SMS_INDISPONIBLE', null, $contexte);
            return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'RELAIS_SMS_INDISPONIBLE'];
        }

        if (!$reponse->successful()) {
            $this->journaliserEchec('TELEPHONE', 'ECHEC_LIVRAISON_SMS', (string) $reponse->status(), $contexte);
            return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'ECHEC_LIVRAISON_SMS'];
        }

        return ['livree' => true, 'canal' => 'TELEPHONE'];
    }

    /**
     * Un code de réponse SMTP à trois chiffres, jamais le message complet du
     * fournisseur : certains serveurs y recopient la commande refusée (donc
     * potentiellement une adresse), et rien ne garantit qu'un message
     * arbitraire ne contienne jamais de fragment sensible.
     */
    private function extraireCodeSmtp(\Throwable $e): ?string
    {
        return preg_match('/^(\d{3})\b/', $e->getMessage(), $m) === 1 ? $m[1] : null;
    }

    /**
     * Best-effort : un journal indisponible ne doit jamais faire échouer une
     * livraison qui aurait autrement réussi, ni masquer un échec réel derrière
     * une exception de journalisation.
     *
     * @param array{produit?:?string,identifiant_reference?:?string,verification_reference?:?string,correlation?:mixed} $contexte
     */
    private function journaliserEchec(string $canal, string $motif, ?string $codeReponse, array $contexte): void
    {
        try {
            $correlation = $contexte['correlation'] ?? null;
            (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'LIVRAISON_VERIFICATION',
                'type' => 'ECHEC_LIVRAISON_VERIFICATION',
                'acteur' => $contexte['produit'] ?? null,
                'action' => 'livrer un code de vérification',
                'ressource' => $canal,
                'decision' => 'ECHEC',
                'motif' => $motif,
                'correlation_id' => is_string($correlation) ? $correlation : null,
                'donnees' => array_filter(
                    [
                        'identifiant_reference' => $contexte['identifiant_reference'] ?? null,
                        'verification_reference' => $contexte['verification_reference'] ?? null,
                        'code_reponse' => $codeReponse,
                    ],
                    static fn (mixed $v): bool => $v !== null,
                ),
            ]);
        } catch (\Throwable) {
        }
    }
}
