<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\RejoueurEvenements;
use Illuminate\Console\Command;

/**
 * Traite les demandes de rejeu VALIDEE/EN_COURS jusqu'à épuisement du lot
 * courant (CAP-CORE-014, fiche partie 5 §1).
 *
 * Reprenable : `RejoueurEvenements::executerRejeu()` avance par un curseur
 * persistant (`demande_rejeu.curseur_sequence`) — un crash à mi-parcours ne
 * fait perdre que le lot en cours, jamais la progression déjà actée.
 */
final class TraiterRejeuxEvenementsCommand extends Command
{
    protected $signature = 'core:evenements:traiter-rejeux
        {--limite=100 : événements traités par lot}
        {--max-lots=50 : lots maximum par demande, par exécution (garde-fou)}';

    protected $description = 'Exécute les demandes de rejeu VALIDEE ou EN_COURS jusqu’à TERMINEE ou jusqu’au garde-fou de lots.';

    public function handle(): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $maxLots = max(1, (int) $this->option('max-lots'));

        try {
            $rejoueur = new RejoueurEvenements(EvenementsMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Traitement interrompu : dépendance indisponible — ' . $e->getMessage());

            return self::FAILURE;
        }

        $demandes = array_filter(
            $rejoueur->listerDemandes(),
            static fn (array $d): bool => in_array($d['etat'], ['VALIDEE', 'EN_COURS'], true),
        );

        if ($demandes === []) {
            $this->info('Aucune demande de rejeu VALIDEE ou EN_COURS.');

            return self::SUCCESS;
        }

        foreach ($demandes as $demande) {
            $reference = (string) $demande['reference'];
            $traites = 0;
            $lots = 0;
            $etat = $demande['etat'];
            while ($etat === 'VALIDEE' || $etat === 'EN_COURS') {
                $resultat = $rejoueur->executerRejeu($reference, $limite);
                if (isset($resultat['refus'])) {
                    $this->error("{$reference} : {$resultat['refus']} — {$resultat['detail']}");

                    break;
                }
                $traites += (int) $resultat['traites'];
                $etat = (string) $resultat['etat'];
                $lots++;
                if ($etat === 'TERMINEE' || (int) $resultat['traites'] === 0) {
                    break;
                }
                if ($lots >= $maxLots) {
                    $this->line("{$reference} : garde-fou de {$maxLots} lots atteint pour cette exécution, reste EN_COURS.");

                    break;
                }
            }
            $this->info("{$reference} : état={$etat} lots={$lots} traités={$traites}");
        }

        return self::SUCCESS;
    }
}
