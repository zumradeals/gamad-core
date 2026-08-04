<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
 * Diagnostic en lecture seule du journal des événements (CAP-CORE-014,
 * partie 3 §17).
 *
 * Aucune réparation implicite : ce diagnostic rapporte, il ne corrige rien.
 */
final class DiagnostiquerEvenementsCommand extends Command
{
    protected $signature = 'core:evenements:diagnostiquer';

    protected $description = 'Diagnostique en lecture seule la cohérence du journal des événements.';

    public function handle(): int
    {
        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);

            $contrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);
            $sources = new RegistreSources($index, $registreIdentites, SourcesMagasin::connecter(), ProduitsMagasin::connecter(), $ctr01);
            $realms = new RegistreRealms($index, $registreIdentites, RealmsMagasin::connecter(), $ctr01);
            $registre = new RegistreEvenements(EvenementsMagasin::connecter(), $contrats, $sources, $realms);
        } catch (\Throwable $e) {
            $this->error('Diagnostic interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $diagnostic = $registre->diagnostiquerJournal();
        $this->info('Chaîne valide : ' . ($diagnostic['chaine']['valide'] ? 'oui' : 'NON'));
        $this->info("Événements sans charge inattendus : {$diagnostic['evenements_sans_charge_inattendus']}");
        $this->info("Baux expirés non libérés : {$diagnostic['baux_expires_non_liberes']}");
        $this->info("Lettres mortes : {$diagnostic['lettres_mortes']}");
        $this->info("Abonnements actifs sans type : {$diagnostic['abonnements_actifs_sans_type']}");
        $this->info('Cohérent : ' . ($diagnostic['coherent'] ? 'oui' : 'NON'));

        return $diagnostic['coherent'] ? self::SUCCESS : self::FAILURE;
    }
}
