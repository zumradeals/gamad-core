<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;
use Illuminate\Console\Command;

/**
 * Bootstrap idempotent des quatre produits déjà connus de l'index (CAP-CORE-011).
 *
 * Cette commande ne crée aucun produit nouveau : elle reprend les quatre
 * entités de type `produit` déjà présentes dans la baseline opérationnelle
 * (`PRD-GAMAD-001` à `004`) et reproduit fidèlement leur état réel actuel,
 * déjà lisible dans `etat_entite` avant ce chantier :
 *
 *   - PRD-GAMAD-001 « GAMAD ID »   : dissoute, son identité rendue au Core ;
 *     inscrite puis retirée (RETIRE) ;
 *   - PRD-GAMAD-002 « GamaDrive »  : seul produit dont l'état portait déjà
 *     `RECONNU` ; inscrite, sa fédération est explicitement autorisée puis
 *     elle est activée (ACTIF) — c'est la reproduction d'une reconnaissance
 *     déjà actée, pas une nouvelle décision ;
 *   - PRD-GAMAD-003 « Wasplex » et PRD-GAMAD-004 « IKOMA » : partenaires
 *     externes non entérinés ; inscrits et laissés en PREPARATION, non
 *     fédérables, jusqu'à une activation explicite distincte.
 *
 * Rejouer cette commande ne crée aucun doublon : chaque étape (inscription,
 * autorisation de fédération, transition de cycle) est individuellement
 * gouvernée et idempotente dans `RegistreProduits`.
 */
final class BootstrapProduitsCommand extends Command
{
    protected $signature = 'core:produits:bootstrap';

    protected $description = 'Reprend les produits déjà connus de l’index dans le registre persistant CAP-CORE-011, sans en inventer de nouveaux.';

    /** @var list<array{reference:string,nom_canonique:string,nom_affichage:string,type_produit:string,cible:string,federable:bool}> */
    private const PRODUITS = [
        [
            'reference' => 'PRD-GAMAD-001',
            'nom_canonique' => 'GAMAD ID',
            'nom_affichage' => 'GAMAD ID',
            'type_produit' => 'SERVICE_CORE',
            'cible' => 'RETIRE',
            'federable' => false,
        ],
        [
            'reference' => 'PRD-GAMAD-002',
            'nom_canonique' => 'GAMAD Drive',
            'nom_affichage' => 'GamaDrive',
            'type_produit' => 'SATELLITE',
            'cible' => 'ACTIF',
            'federable' => true,
        ],
        [
            'reference' => 'PRD-GAMAD-003',
            'nom_canonique' => 'Wasplex',
            'nom_affichage' => 'Wasplex',
            'type_produit' => 'PARTENAIRE',
            'cible' => 'PREPARATION',
            'federable' => false,
        ],
        [
            'reference' => 'PRD-GAMAD-004',
            'nom_canonique' => 'IKOMA',
            'nom_affichage' => 'IKOMA',
            'type_produit' => 'PARTENAIRE',
            'cible' => 'PREPARATION',
            'federable' => false,
        ],
    ];

    private const SOURCE = 'core/registre-normes/resources/index-baseline-v1.json — bootstrap CAP-CORE-011';

    public function handle(): int
    {
        try {
            $index = Db::connect();
            try {
                $vide = ((int) $index->query('SELECT count(*) FROM entite')->fetchColumn()) === 0;
            } catch (\Throwable) {
                $vide = true;
            }
            if ($vide) {
                BaselineOperationnelle::standard()->reconstruire($index);
            }
            $registreIdentites = IdentiteMagasin::connecter();
            $magasinProduits = ProduitsMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $registre = new RegistreProduits($index, $registreIdentites, $magasinProduits, $ctr01);
        } catch (\Throwable $e) {
            $this->error('Bootstrap interrompu : '.$e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $politique = PolitiqueProduits::POLITIQUE;
        $source = self::SOURCE;

        foreach (self::PRODUITS as $definition) {
            $reference = $definition['reference'];
            $existant = $registre->resoudreProduit($reference);

            if ($existant === null) {
                $identite = $ctr01->resoudreIdentite($reference);
                if ($identite === null) {
                    $this->warn("{$reference} : aucune identité canonique dans l’index, ignoré (rien à reprendre).");

                    continue;
                }
                $inscription = $registre->inscrireProduit([
                    'reference' => $reference,
                    'identite_reference' => $reference,
                    'nom_canonique' => $definition['nom_canonique'],
                    'nom_affichage' => $definition['nom_affichage'],
                    'type_produit' => $definition['type_produit'],
                    'proprietaire_reference' => $acteur,
                    'source' => $source,
                    'producteur' => $acteur,
                    'politique' => $politique,
                    'preuve' => "BOOT-CAP-CORE-011-{$reference}-INSCRIPTION",
                ]);
                if (isset($inscription['refus'])) {
                    $this->error("{$reference} : inscription refusée — {$inscription['refus']} ({$inscription['detail']})");

                    return self::FAILURE;
                }
                $this->info("{$reference} : inscrit en PREPARATION.");
            } else {
                $this->line("{$reference} : déjà inscrit, aucun doublon créé.");
            }

            if ($definition['federable']) {
                $autorisation = $registre->modifierProduit($reference, [
                    'federation_autorisee' => true,
                    'politique' => $politique,
                    'producteur' => $acteur,
                    'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-011-{$reference}-FEDERATION",
                ]);
                if (isset($autorisation['refus'])) {
                    $this->error("{$reference} : autorisation de fédération refusée — {$autorisation['refus']}");

                    return self::FAILURE;
                }
            }

            $etat = $registre->resoudreEtat($reference);
            if (($etat['etat'] ?? null) === $definition['cible']) {
                $this->line("{$reference} : déjà au cycle cible ({$definition['cible']}).");

                continue;
            }

            $transition = match ($definition['cible']) {
                'ACTIF' => $registre->activerProduit($reference, [
                    'politique' => $politique, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-011-{$reference}-ACTIVATION",
                    'motif' => 'reprise de l’état RECONNU déjà porté par la baseline documentaire',
                ]),
                'RETIRE' => $registre->retirerProduit($reference, [
                    'politique' => $politique, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-011-{$reference}-RETRAIT",
                    'motif' => 'reprise de la dissolution déjà actée dans CAP-CORE-001',
                ]),
                default => ['idempotent' => true],
            };
            if (isset($transition['refus'])) {
                $this->error("{$reference} : transition vers {$definition['cible']} refusée — {$transition['refus']}");

                return self::FAILURE;
            }
            $this->info("{$reference} : cycle → {$definition['cible']}.");
        }

        $this->newLine();
        $this->info('Bootstrap CAP-CORE-011 terminé. Aucun produit n’a été inventé ; aucun doublon créé.');

        return self::SUCCESS;
    }
}
