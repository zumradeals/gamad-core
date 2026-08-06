<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalEvenements\LivreurEvenements;
use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\RegistreEvenements;
use Gamad\MoteurMatching\AcquisitionSignaux;
use Gamad\MoteurMatching\Magasin as MatchingMagasin;
use Gamad\MoteurMatching\RegistreMatching;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\RegistreRealms;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\RegistreSources;
use Illuminate\Console\Command;

/**
 * Exploitation : tire un lot de livraisons PULL_API d'un abonnement
 * CAP-CORE-014 et matérialise chaque signal utilisable en `matching_signal`
 * (CAP-CORE-021, doc de chantier 01 §7, doc 02 §10). Voir la réserve
 * documentée en tête d'`AcquisitionSignaux` : aucun producteur réel
 * n'existe encore, cette commande est prête à l'emploi mais pas exercée en
 * conditions réelles.
 *
 * L'abonnement lui-même (type d'événement, producteur, realm) est configuré
 * une fois, par l'exploitant, via les commandes existantes de CAP-CORE-014 —
 * cette commande ne crée ni ne modifie jamais d'abonnement.
 */
final class AcquerirSignauxMatchingCommand extends Command
{
    protected $signature = 'core:matching:acquerir-signaux
        {abonnement : référence de l’abonnement CAP-CORE-014 (ABN-GAMAD-...)}
        {consommateur : référence du consommateur propriétaire de l’abonnement}
        {--limite=100 : nombre maximal de livraisons à tirer}';

    protected $description = 'Tire un lot de signaux depuis un abonnement CAP-CORE-014 et les matérialise dans le magasin du Matching.';

    public function handle(): int
    {
        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $contrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter());
            $sources = new RegistreSources($index, $registreIdentites, SourcesMagasin::connecter(), \Gamad\RegistreProduits\Magasin::connecter());
            $realms = new RegistreRealms($index, $registreIdentites, RealmsMagasin::connecter());
            $evenements = new RegistreEvenements(EvenementsMagasin::connecter(), $contrats, $sources, $realms);
            $livreur = new LivreurEvenements(EvenementsMagasin::connecter(), $evenements);
            $matching = new RegistreMatching(MatchingMagasin::connecter());
            $acquisition = new AcquisitionSignaux($livreur, $matching);
        } catch (\Throwable $e) {
            $this->error('Acquisition interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $abonnement = (string) $this->argument('abonnement');
        $consommateur = (string) $this->argument('consommateur');
        $limite = max(1, (int) $this->option('limite'));
        $correlation = 'ACQ-MATCHING-' . strtoupper(bin2hex(random_bytes(6)));

        $resultat = $acquisition->acquerir($abonnement, $consommateur, $limite, $correlation);
        if (isset($resultat['refus'])) {
            $this->error("Acquisition refusée — {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d livraison(s) reçue(s), %d signal(aux) matérialisé(s), %d refus.',
            $resultat['livraisons_recues'],
            count($resultat['signaux_materialises']),
            count($resultat['livraisons_refusees']),
        ));
        foreach ($resultat['livraisons_refusees'] as $refus) {
            $this->warn("  refusé : livraison {$refus['livraison']} — {$refus['motif']}");
        }

        return self::SUCCESS;
    }
}
