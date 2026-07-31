<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Compatibilité temporaire.
 *
 * L'ancienne commande reconstruisait un index à partir du corpus documentaire
 * Genesis II. Ce corpus n'est plus une source active du produit. La commande
 * reste enregistrée afin qu'une automatisation historique échoue explicitement
 * plutôt que d'écrire un index vide ou incohérent.
 */
final class ReindexerCommand extends Command
{
    protected $signature = 'registre:reindexer';

    protected $description = 'Commande héritée désactivée après le retrait du corpus Genesis II.';

    public function handle(): int
    {
        $this->error('Réindexation documentaire désactivée.');
        $this->line('Le corpus Genesis II a été retiré de la documentation active.');
        $this->line('Les registres utiles doivent être alimentés par des données et contrats techniques réels.');
        $this->line('Consulter docs/05-exploitation-continuite-et-preuves.md et les fiches CAP-CORE-006 à 020.');

        return self::FAILURE;
    }
}
