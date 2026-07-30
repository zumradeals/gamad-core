<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Console\Command;

final class RevoquerPasskeyCommand extends Command
{
    protected $signature = 'identite:revoquer-passkey
                            {entite : référence de l’entité}
                            {passkey : référence PASS- de la passkey}';

    protected $description = 'Révoque une passkey et toutes les sessions qu’elle a ouvertes.';

    public function handle(): int
    {
        $entite = (string) $this->argument('entite');
        $reference = (string) $this->argument('passkey');

        try {
            $ctr = new Ctr16(Magasin::connecter());
            $appartient = false;
            foreach ($ctr->passkeysActives($entite) as $passkey) {
                if (hash_equals((string) $passkey['reference'], $reference)) {
                    $appartient = true;
                    break;
                }
            }
            if (! $appartient) {
                $this->error('Passkey active inconnue pour cette entité.');

                return self::FAILURE;
            }
            if (! $ctr->revoquerPasskey($reference)) {
                $this->error('La passkey n’a pas été révoquée.');

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Registre d’accès indisponible : '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'SECURITE',
                'type' => 'REVOCATION_PASSKEY',
                'acteur' => $entite,
                'action' => 'révoquer une passkey',
                'decision' => 'EXECUTEE',
                'donnees' => ['passkey' => $reference, 'canal' => 'CLI'],
            ]);
        } catch (\Throwable $e) {
            $this->warn('Passkey révoquée, mais journal indisponible : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Passkey {$reference} révoquée pour {$entite}.");

        return self::SUCCESS;
    }
}
