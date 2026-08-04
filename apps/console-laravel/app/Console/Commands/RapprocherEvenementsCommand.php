<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\RapprochementEvenements;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Illuminate\Console\Command;

/**
 * Rapprochement en lecture seule entre le journal central et les magasins
 * producteurs raccordés (CAP-CORE-014, fiche partie 5 §8).
 *
 * Ne répare rien : ce chantier ne livre aucune commande de réparation.
 * Toute correction reste une action manuelle et explicite, hors de cette
 * commande, avec sa propre trace d'audit.
 */
final class RapprocherEvenementsCommand extends Command
{
    protected $signature = 'core:evenements:rapprocher {--limite=1000 : nombre maximal d’anomalies par catégorie}';

    protected $description = 'Rapproche le journal central et les magasins producteurs raccordés ; produit un rapport, ne répare rien.';

    public function handle(): int
    {
        $limite = max(1, (int) $this->option('limite'));

        try {
            $rapprochement = new RapprochementEvenements(EvenementsMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Rapprochement interrompu : dépendance indisponible — ' . $e->getMessage());

            return self::FAILURE;
        }

        $rapport = $rapprochement->rapprocher($this->magasinsProducteurs(), $limite);

        $total = 0;
        foreach ($rapport as $categorie => $anomalies) {
            $total += count($anomalies);
            if ($anomalies === []) {
                $this->line("[OK] {$categorie} : aucune anomalie.");

                continue;
            }
            $this->error("[ANOMALIE] {$categorie} : " . count($anomalies) . ' ligne(s).');
            foreach (array_slice($anomalies, 0, 10) as $anomalie) {
                $this->line('    ' . json_encode($anomalie, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
            if (count($anomalies) > 10) {
                $this->line('    … ' . (count($anomalies) - 10) . ' de plus.');
            }
        }

        $this->newLine();
        $this->line($total === 0
            ? 'Rapprochement : aucune anomalie.'
            : "Rapprochement : {$total} anomalie(s) au total. Cette commande ne répare rien ; toute correction reste manuelle et explicite.");

        return self::SUCCESS;
    }

    /** @return array<string,\PDO> */
    private function magasinsProducteurs(): array
    {
        return [
            'produits (CAP-CORE-011)' => ProduitsMagasin::connecter(),
        ];
    }
}
