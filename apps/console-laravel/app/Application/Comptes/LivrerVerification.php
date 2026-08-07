<?php

declare(strict_types=1);

namespace App\Application\Comptes;

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
        if (config('gamad_verification.email.enabled') !== true) {
            return ['livree' => false, 'canal' => 'EMAIL', 'motif' => 'EMAIL_NON_CONFIGURE'];
        }

        $mailer = (string) config('gamad_verification.email.mailer', 'log');
        if (app()->environment('production') && in_array($mailer, ['log', 'array'], true)) {
            return ['livree' => false, 'canal' => 'EMAIL', 'motif' => 'TRANSPORT_EMAIL_NON_LIVRABLE'];
        }

        $sujet = (string) config('gamad_verification.email.subject', 'Votre code de verification GAMAD');
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
        // Le Core n'invente aucun fournisseur SMS. Tant qu'un pilote explicite
        // n'est pas raccorde, la creation par telephone reste fail-closed.
        if (config('gamad_verification.sms.enabled') !== true) {
            return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'SMS_NON_CONFIGURE'];
        }

        return ['livree' => false, 'canal' => 'TELEPHONE', 'motif' => 'TRANSPORT_SMS_NON_IMPLEMENTE'];
    }
}
