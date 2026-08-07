<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use Illuminate\Support\Facades\Crypt;

/**
 * Configuration opérationnelle des canaux de vérification Compte GAMAD.
 *
 * Le dirigeant peut la modifier depuis la console. Le fichier local est
 * chiffré par APP_KEY et protégé en 0600. Les secrets ne sont jamais remis à
 * la vue : `lirePourConsole()` ne retourne que leur présence.
 */
final class ConfigurationCanauxVerification
{
    private const VERSION = 1;

    public function chemin(): string
    {
        return storage_path('app/private/gamad-verification/canaux.enc');
    }

    /** @return array<string,mixed> */
    public function lire(): array
    {
        $defaut = $this->defaut();
        $chemin = $this->chemin();
        if (!is_file($chemin)) {
            return $defaut;
        }

        try {
            $chiffre = file_get_contents($chemin);
            if (!is_string($chiffre) || $chiffre === '') {
                return $defaut;
            }
            $json = Crypt::decryptString($chiffre);
            $donnees = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($donnees)) {
                return $defaut;
            }

            return array_replace_recursive($defaut, $donnees);
        } catch (\Throwable) {
            return $defaut;
        }
    }

    /** @return array<string,mixed> */
    public function lirePourConsole(): array
    {
        $configuration = $this->lire();
        $configuration['email']['password_present'] = trim((string) ($configuration['email']['password'] ?? '')) !== '';
        $configuration['sms']['token_present'] = trim((string) ($configuration['sms']['relay_token'] ?? '')) !== '';
        unset($configuration['email']['password'], $configuration['sms']['relay_token']);

        return $configuration;
    }

    /** @param array<string,mixed> $entree */
    public function enregistrer(array $entree): array
    {
        $actuelle = $this->lire();

        $emailPassword = trim((string) ($entree['email_password'] ?? ''));
        $smsToken = trim((string) ($entree['sms_relay_token'] ?? ''));
        $nouvelle = [
            'version' => self::VERSION,
            'email' => [
                'enabled' => (bool) ($entree['email_enabled'] ?? false),
                'driver' => (string) ($entree['email_driver'] ?? 'smtp'),
                'host' => trim((string) ($entree['email_host'] ?? '')),
                'port' => (int) ($entree['email_port'] ?? 587),
                'scheme' => trim((string) ($entree['email_scheme'] ?? 'tls')),
                'username' => trim((string) ($entree['email_username'] ?? '')),
                'password' => $emailPassword !== '' ? $emailPassword : (string) ($actuelle['email']['password'] ?? ''),
                'from_address' => trim((string) ($entree['email_from_address'] ?? '')),
                'from_name' => trim((string) ($entree['email_from_name'] ?? 'GAMAD')),
                'subject' => trim((string) ($entree['email_subject'] ?? 'Votre code de vérification GAMAD')),
            ],
            'sms' => [
                'enabled' => (bool) ($entree['sms_enabled'] ?? false),
                'driver' => 'relay',
                'relay_url' => trim((string) ($entree['sms_relay_url'] ?? '')),
                'relay_token' => $smsToken !== '' ? $smsToken : (string) ($actuelle['sms']['relay_token'] ?? ''),
                'sender' => trim((string) ($entree['sms_sender'] ?? 'GAMAD')),
                'timeout' => max(2, min(15, (int) ($entree['sms_timeout'] ?? 8))),
            ],
            'updated_at' => gmdate('c'),
        ];

        $this->valider($nouvelle);
        $repertoire = dirname($this->chemin());
        if (!is_dir($repertoire) && !mkdir($repertoire, 0700, true) && !is_dir($repertoire)) {
            throw new \RuntimeException('Impossible de créer le répertoire de configuration des canaux.');
        }
        @chmod($repertoire, 0700);

        $json = json_encode($nouvelle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $temporaire = $this->chemin() . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporaire, Crypt::encryptString($json), LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d’écrire la configuration des canaux.');
        }
        @chmod($temporaire, 0600);
        if (!rename($temporaire, $this->chemin())) {
            @unlink($temporaire);
            throw new \RuntimeException('Impossible d’activer la configuration des canaux.');
        }
        @chmod($this->chemin(), 0600);

        return $this->lirePourConsole();
    }

    /** @param array<string,mixed> $configuration */
    private function valider(array $configuration): void
    {
        $email = $configuration['email'];
        if (($email['enabled'] ?? false) === true) {
            if (($email['driver'] ?? null) !== 'smtp') {
                throw new \InvalidArgumentException('Le pilote email doit être SMTP pour cette première version console.');
            }
            if ($email['host'] === '' || $email['from_address'] === '' || !filter_var($email['from_address'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Renseignez un serveur SMTP et une adresse expéditeur valide.');
            }
            if ((int) $email['port'] < 1 || (int) $email['port'] > 65535) {
                throw new \InvalidArgumentException('Le port SMTP est invalide.');
            }
        }

        $sms = $configuration['sms'];
        if (($sms['enabled'] ?? false) === true) {
            $url = (string) ($sms['relay_url'] ?? '');
            if (!str_starts_with($url, 'https://')) {
                throw new \InvalidArgumentException('Le relais SMS doit utiliser une adresse HTTPS.');
            }
            if (trim((string) ($sms['relay_token'] ?? '')) === '') {
                throw new \InvalidArgumentException('Le jeton du relais SMS est requis pour activer le SMS.');
            }
        }
    }

    /** @return array<string,mixed> */
    private function defaut(): array
    {
        return [
            'version' => self::VERSION,
            'email' => [
                'enabled' => false,
                'driver' => 'smtp',
                'host' => '',
                'port' => 587,
                'scheme' => 'tls',
                'username' => '',
                'password' => '',
                'from_address' => '',
                'from_name' => 'GAMAD',
                'subject' => 'Votre code de vérification GAMAD',
            ],
            'sms' => [
                'enabled' => false,
                'driver' => 'relay',
                'relay_url' => '',
                'relay_token' => '',
                'sender' => 'GAMAD',
                'timeout' => 8,
            ],
            'updated_at' => null,
        ];
    }
}
