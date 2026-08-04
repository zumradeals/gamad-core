<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\PolitiqueEvenements;
use Gamad\JournalEvenements\RegistreEvenements;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\RegistreRealms;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\RegistreSources;
use Illuminate\Console\Command;

/**
 * Purge gouvernée des charges expirées (CAP-CORE-014, fiche partie 5 §1).
 *
 * En simulation par défaut : liste les candidats sans écrire. `--force`
 * exécute réellement la purge, mais chaque ligne repasse quand même par
 * `RegistreEvenements::purgerCharge()`, qui refuse toute charge non encore
 * expirée contractuellement ou encore utilisée par une livraison active —
 * `--force` ne contourne aucune de ces garanties, il autorise seulement
 * l'écriture.
 */
final class PurgerChargesEvenementsCommand extends Command
{
    protected $signature = 'core:evenements:purger-charges
        {--avant= : ne considérer que les charges expirant avant cette date (YYYY-MM-DD), en plus de leur expiration réelle}
        {--limite=500 : nombre maximal de charges par exécution}
        {--force : exécute réellement la purge ; sans cette option, simulation seule}';

    protected $description = 'Purge les charges d’événements contractuellement expirées et non utilisées ; simulation par défaut.';

    public function handle(): int
    {
        $avantOption = $this->option('avant');
        $avant = null;
        if (is_string($avantOption) && trim($avantOption) !== '') {
            $timestamp = strtotime($avantOption);
            if ($timestamp === false) {
                $this->error("--avant invalide : « {$avantOption} » n’est pas une date reconnue.");

                return self::FAILURE;
            }
            $avant = gmdate('c', $timestamp);
        }
        $limite = max(1, (int) $this->option('limite'));
        $force = (bool) $this->option('force');

        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $contrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);
            $sources = new RegistreSources($index, $registreIdentites, SourcesMagasin::connecter(), ProduitsMagasin::connecter(), $ctr01);
            $realms = new RegistreRealms($index, $registreIdentites, RealmsMagasin::connecter(), $ctr01);
            $registre = new RegistreEvenements(EvenementsMagasin::connecter(), $contrats, $sources, $realms);
        } catch (\Throwable $e) {
            $this->error('Purge interrompue : dépendance indisponible — ' . $e->getMessage());

            return self::FAILURE;
        }

        $candidats = $registre->listerChargesPurgeables($avant, $limite);
        if ($candidats === []) {
            $this->info('Aucune charge purgeable' . ($avant !== null ? " avant {$avant}" : '') . '.');

            return self::SUCCESS;
        }

        if (!$force) {
            $this->line(sprintf('Simulation — %d charge(s) purgeable(s) :', count($candidats)));
            foreach ($candidats as $candidat) {
                $this->line("  {$candidat['reference']} (expirée le {$candidat['charge_expire_le']})");
            }
            $this->line('Relancer avec --force pour purger réellement.');

            return self::SUCCESS;
        }

        $dossier = [
            'politique' => PolitiqueEvenements::POLITIQUE,
            'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
            'source' => PolitiqueEvenements::SOURCE,
            'preuve' => 'PURGE-CAP-CORE-014-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)),
        ];

        $purgees = 0;
        $refusees = 0;
        foreach ($candidats as $candidat) {
            $resultat = $registre->purgerCharge((string) $candidat['reference'], $dossier);
            if (isset($resultat['refus'])) {
                $this->error("{$candidat['reference']} : {$resultat['refus']} — {$resultat['detail']}");
                $refusees++;

                continue;
            }
            $purgees++;
        }

        $this->info("Purge terminée : {$purgees} charge(s) purgée(s), {$refusees} refus.");

        return self::SUCCESS;
    }
}
