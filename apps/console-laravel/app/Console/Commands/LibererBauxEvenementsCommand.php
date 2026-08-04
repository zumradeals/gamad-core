<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalEvenements\LivreurEvenements;
use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\RegistreEvenements;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\RegistreRealms;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\RegistreSources;
use Illuminate\Console\Command;

/**
 * Libère les baux expirés (CAP-CORE-014, partie 3 §11).
 *
 * Idempotente : un crash consommateur ne bloque jamais définitivement une
 * livraison, et rejouer cette commande sans bail expiré ne fait rien.
 */
final class LibererBauxEvenementsCommand extends Command
{
    protected $signature = 'core:evenements:liberer-baux';

    protected $description = 'Libère les baux de livraison expirés et fait progresser les livraisons concernées.';

    public function handle(): int
    {
        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);

            $contrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);
            $sources = new RegistreSources($index, $registreIdentites, SourcesMagasin::connecter(), ProduitsMagasin::connecter(), $ctr01);
            $realms = new RegistreRealms($index, $registreIdentites, RealmsMagasin::connecter(), $ctr01);
            $registreCentral = new RegistreEvenements(EvenementsMagasin::connecter(), $contrats, $sources, $realms);
            $livreur = new LivreurEvenements(EvenementsMagasin::connecter(), $registreCentral);
        } catch (\Throwable $e) {
            $this->error('Libération interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $resultat = $livreur->libererBauxExpires();
        $this->info("Baux libérés : {$resultat['liberes']}.");

        return self::SUCCESS;
    }
}
