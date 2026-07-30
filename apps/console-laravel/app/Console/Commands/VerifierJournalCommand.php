<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin;
use Illuminate\Console\Command;

final class VerifierJournalCommand extends Command
{
    protected $signature = 'core:journal:verifier';

    protected $description = 'Vérifie le chaînage et les empreintes du journal opérationnel.';

    public function handle(): int
    {
        try {
            $diagnostic = (new Journal(Magasin::ouvrir()))->verifierIntegrite();
        } catch (\Throwable $e) {
            $this->error('Journal indisponible : ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line("Événements : {$diagnostic['evenements']}");
        $this->line('Tête : ' . ($diagnostic['tete'] ?? 'aucune'));
        foreach ($diagnostic['ecarts'] as $ecart) {
            $this->error($ecart);
        }

        if (!$diagnostic['valide']) {
            return self::FAILURE;
        }

        $this->info('Chaîne du journal valide.');

        return self::SUCCESS;
    }
}
