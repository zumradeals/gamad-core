<?php

declare(strict_types=1);

namespace App\Application\Comptes;

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

    /** @return array{livree:bool,canal:string,motif?:string} */
    public function executer(string $type, string $destination, string $code, string $expireLe): array
    {
        $type = strtoupper(trim($type));

        return match ($type) {
            'EMAIL' => $this->email($destination, $code, $expireLe),
            'TELEPHONE' => $this->sms($destination, $code, $expireLe),
            default => ['livree' => false, 'canal' => $type, 'motif' => 'CANAL_NON_SUPPORTE'],
        };
    }

    /** @return array{livree:bool,canal:string,motif?:string} */
    private function email(string $adresse, string $code, string $expireLe): array
    {
        $canaux = ($this->configuration ?? new ConfigurationCanauxVerification())->lire();
        $console = $canaux['email'] ?? [];
        $viaConsole = ($console['enabled'] ?? false) === true;

        if (!$viaConsole && config('gamad_verification.email.enabled') !== true) {
            return ['livree' => false, 'canal' => 'EMAIL', 'motif' => 'EMAIL_NON_CONFIGURE'];
        }

        if ($viaConsole) {
            $driver = (string) ($console['driver'] ?? 'smtp');
            if ($driver !== 'smtp') {
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
        } catch (\Throwable) {
            return ['livree' => false, 'canal' => 'EMAIL', 'motif' => 'ECHEC_LIVRAISON_EMAIL'];
        }

        return ['livree' => true, 'canal' => 'EMAIL'];
    }

    /** @return array{livree:bool,canal:string,motif?:string} */
    private function sms(string $telephone, string $code, string $expireLe): array
    {
        $canaux = ($this->configuration ?? new ConfigurationCanauxVerification())->lire();
        $console = $canaux['sms'] ?? [];
        $viaConsole = ($console['enabled'] ?? false) === true;

        if (!$viaConsole && config('gamad_verification.sms.enabled') !== true) {
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
            return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'TRANSPORT_SMS_NON_SUPPORTE'];
        }
        if ($url === '' || $token === '' || !str_starts_with($url, 'https://')) {
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
            return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'RELAIS_SMS_INDISPONIBLE'];
        }

        if (!$reponse->successful()) {
            return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'ECHEC_LIVRAISON_SMS'];
        }

        return ['livree' => true, 'canal' => 'TELEPHONE'];
    }
}
