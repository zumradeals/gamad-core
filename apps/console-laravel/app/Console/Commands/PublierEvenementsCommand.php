<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\EvenementsSortants\RelaisOutbox;
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
 * Relais des outbox producteur vers le journal central (CAP-CORE-014,
 * partie 3 §4).
 *
 * Énumère explicitement les magasins producteurs raccordés — aujourd'hui
 * uniquement CAP-CORE-011 (registre des produits), seul pilote livré dans ce
 * chantier. Ajouter un producteur ultérieurement se fait en ajoutant une
 * entrée à `magasinsProducteurs()`, jamais par un compteur ou une découverte
 * implicite.
 */
final class PublierEvenementsCommand extends Command
{
    protected $signature = 'core:evenements:publier {--limite=100 : nombre maximal de lignes par magasin producteur}';

    protected $description = 'Publie les lignes d’outbox EN_ATTENTE (ou en nouvelle tentative) des magasins producteurs raccordés vers le journal central.';

    public function handle(): int
    {
        $limite = max(1, (int) $this->option('limite'));

        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);

            $contrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);
            $sources = new RegistreSources($index, $registreIdentites, SourcesMagasin::connecter(), ProduitsMagasin::connecter(), $ctr01);
            $realms = new RegistreRealms($index, $registreIdentites, RealmsMagasin::connecter(), $ctr01);
            $registreCentral = new RegistreEvenements(EvenementsMagasin::connecter(), $contrats, $sources, $realms);
        } catch (\Throwable $e) {
            $this->error('Relais interrompu : dépendance indisponible — ' . $e->getMessage());

            return self::FAILURE;
        }

        $dossier = [
            'politique' => PolitiqueEvenements::POLITIQUE,
            'producteur' => PolitiqueEvenements::CAPACITE,
            'source' => PolitiqueEvenements::SOURCE,
            'preuve' => 'RELAIS-CAP-CORE-014-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)),
        ];

        $total = ['lot' => 0, 'publies' => 0, 'echecs_temporaires' => 0, 'echecs_definitifs' => 0];
        foreach ($this->magasinsProducteurs() as $nom => $magasin) {
            try {
                $relais = new RelaisOutbox($magasin, $registreCentral);
                $resultat = $relais->publierOutbox($dossier, $limite);
            } catch (\Throwable $e) {
                $this->error("{$nom} : relais interrompu — {$e->getMessage()}");

                continue;
            }
            $this->info(sprintf(
                '%s : lot=%d publiés=%d échecs_temporaires=%d échecs_définitifs=%d',
                $nom, $resultat['lot'], $resultat['publies'], $resultat['echecs_temporaires'], $resultat['echecs_definitifs'],
            ));
            foreach ($total as $cle => $valeur) {
                $total[$cle] += $resultat[$cle];
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Total : lot=%d publiés=%d échecs_temporaires=%d échecs_définitifs=%d',
            $total['lot'], $total['publies'], $total['echecs_temporaires'], $total['echecs_definitifs'],
        ));

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
