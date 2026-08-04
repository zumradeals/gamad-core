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
 * Vérification en lecture seule de la chaîne d'empreintes du journal
 * central (CAP-CORE-014, partie 5 §1).
 *
 * Ne répare rien : une chaîne invalide doit être traitée comme un incident,
 * jamais corrigée silencieusement.
 */
final class VerifierEvenementsCommand extends Command
{
    protected $signature = 'core:evenements:verifier';

    protected $description = 'Vérifie en lecture seule le chaînage et les empreintes du journal des événements.';

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
            $this->error('Vérification interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $chaine = $registre->verifierChaine();
        $this->info("Événements : {$chaine['evenements']}");
        $this->info('Tête : ' . ($chaine['tete'] ?? '(vide)'));
        if ($chaine['valide']) {
            $this->info('Chaîne valide.');

            return self::SUCCESS;
        }

        $this->error('Chaîne invalide :');
        foreach ($chaine['ecarts'] as $ecart) {
            $this->error("  - {$ecart}");
        }

        return self::FAILURE;
    }
}
