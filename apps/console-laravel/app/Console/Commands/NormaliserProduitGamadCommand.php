<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;
use Illuminate\Console\Command;

/**
 * Applique la décision DEC-2026-09-08-GAMAD-PRODUIT-005 sans réinscrire le
 * produit et sans modifier ses identifiants persistants.
 */
final class NormaliserProduitGamadCommand extends Command
{
    protected $signature = 'core:produit-gamad:normaliser
        {--force : autorise explicitement la mutation en environnement production}';

    protected $description = 'Normalise PRD-GAMAD-005 sous le nom GAMAD, sans modifier sa référence, son identité liée ni son cycle.';

    private const PRODUIT = 'PRD-GAMAD-005';
    private const NOM = 'GAMAD';
    private const SOURCE = 'docs/decisions/DEC-2026-09-08-GAMAD-PRODUIT-005.md';
    private const PREUVE = 'MIG-PRD-GAMAD-005-NOM-2026-09-08';

    public function handle(): int
    {
        if ($this->laravel->environment('production') && !$this->option('force')) {
            $this->error('Mutation refusée en production sans --force explicite.');
            return self::FAILURE;
        }

        try {
            $index = Db::connect();
            $identites = IdentiteMagasin::connecter();
            $produits = ProduitsMagasin::connecter();
            $ctr01 = new Ctr01($index, $identites, produits: $produits);
            $registre = new RegistreProduits($index, $identites, $produits, $ctr01);
        } catch (\Throwable $e) {
            $this->error('Normalisation interrompue : '.$e->getMessage());
            return self::FAILURE;
        }

        $avant = $registre->resoudreProduit(self::PRODUIT);
        if (!is_array($avant)) {
            $this->error(self::PRODUIT.' est absent. La décision interdit de créer un doublon pour matérialiser le renommage.');
            return self::FAILURE;
        }

        $etatAvant = $registre->resoudreEtat(self::PRODUIT);
        if (!is_array($etatAvant)) {
            $this->error(self::PRODUIT.' ne possède aucun cycle CAP-CORE-011 exploitable.');
            return self::FAILURE;
        }

        if (
            ($avant['nom_canonique'] ?? null) === self::NOM
            && ($avant['nom_affichage'] ?? null) === self::NOM
        ) {
            $this->info(self::PRODUIT.' : déjà normalisé sous GAMAD, aucune mutation.');
            return self::SUCCESS;
        }

        $dossier = [
            'nom_canonique' => self::NOM,
            'nom_affichage' => self::NOM,
            'politique' => PolitiqueProduits::POLITIQUE,
            'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
            'source' => self::SOURCE,
            'preuve' => self::PREUVE,
        ];

        $resultat = $registre->modifierProduit(self::PRODUIT, $dossier);
        if (isset($resultat['refus'])) {
            $this->error('Normalisation refusée : '.($resultat['refus'] ?? 'REFUS').' — '.($resultat['detail'] ?? 'sans détail'));
            return self::FAILURE;
        }

        $apres = $registre->resoudreProduit(self::PRODUIT);
        $etatApres = $registre->resoudreEtat(self::PRODUIT);
        if (!is_array($apres) || !is_array($etatApres)) {
            $this->error('Le produit ne peut pas être relu après la mutation.');
            return self::FAILURE;
        }

        $invariants = [
            'reference' => $avant['reference'] ?? null,
            'identite_reference' => $avant['identite_reference'] ?? null,
            'type_produit' => $avant['type_produit'] ?? null,
            'proprietaire_reference' => $avant['proprietaire_reference'] ?? null,
            'federation_autorisee' => $avant['federation_autorisee'] ?? null,
        ];
        foreach ($invariants as $champ => $valeur) {
            if (($apres[$champ] ?? null) !== $valeur) {
                $this->error("Invariant rompu après renommage : {$champ} a dérivé.");
                return self::FAILURE;
            }
        }

        if (($etatApres['etat'] ?? null) !== ($etatAvant['etat'] ?? null)) {
            $this->error('Invariant rompu après renommage : le cycle produit a dérivé.');
            return self::FAILURE;
        }

        if (($apres['nom_canonique'] ?? null) !== self::NOM || ($apres['nom_affichage'] ?? null) !== self::NOM) {
            $this->error('La fiche produit n’expose pas GAMAD après la mutation.');
            return self::FAILURE;
        }

        $this->info(self::PRODUIT.' : nom canonique et nom affiché → GAMAD ; identifiants, cycle et permissions inchangés.');
        return self::SUCCESS;
    }
}
