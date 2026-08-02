<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\PolitiqueSources;
use Gamad\RegistreSources\RegistreSources;
use Illuminate\Console\Command;

/**
 * Bootstrap idempotent des sources déjà connues de l'index (CAP-CORE-006).
 *
 * Cette commande n'invente aucune source : elle relit la table `source` de
 * l'ancien index reconstructible — la photographie technique versionnée de
 * `index-baseline-v1.json` — et reprend fidèlement, pour chacune de ses
 * lignes, la référence, le titre (devenu `nom_affichage`), la catégorie, la
 * valeur historique d'authenticité (conservée telle quelle dans
 * `authenticite_legacy`, jamais réinterprétée) et la réserve.
 *
 * Le nombre de sources reprises n'est pas fixé dans le code : il se compte à
 * l'exécution, sur les données réelles de l'index.
 *
 * Choix documentés, faute de données historiques plus précises :
 *   - propriétaire : l'autorité d'inscription (`AUT-GAMAD-001`), seule
 *     identité canonique disponible pour l'ensemble du lot importé ;
 *   - type : `IMPORT_GOUVERNE`, parce que ces 26 fiches proviennent d'un
 *     canal d'import versionné et contrôlé, non d'un produit ou d'un service
 *     qui les produirait aujourd'hui ;
 *   - cycle cible : `ACTIVE`, pour que les lectures déjà servies par CTR-04
 *     (compatibilité) continuent de trouver une source active — fiche
 *     CAP-CORE-006 §10 ;
 *   - niveau de vérification : laissé absent, donc `NON_VERIFIEE` par
 *     défaut ; aucune vérification n'est inventée pour un lot qui n'en a
 *     jamais reçu.
 *
 * Rejouer cette commande ne crée aucun doublon : l'inscription et
 * l'activation sont chacune individuellement gouvernées et idempotentes dans
 * `RegistreSources`.
 */
final class BootstrapSourcesCommand extends Command
{
    protected $signature = 'core:sources:bootstrap';

    protected $description = 'Reprend les sources déjà connues de l’index dans le registre persistant CAP-CORE-006, sans en inventer de nouvelles.';

    private const SOURCE = 'core/registre-normes/resources/index-baseline-v1.json — bootstrap CAP-CORE-006';

    private const TYPE_IMPORT = 'IMPORT_GOUVERNE';

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
            $ctr01 = new Ctr01($index, $registreIdentites);
            $registre = new RegistreSources(
                $index,
                $registreIdentites,
                SourcesMagasin::connecter(),
                ProduitsMagasin::connecter(),
                $ctr01,
            );

            $legacy = $index->query(
                'SELECT reference, titre, categorie, authenticite, reserve FROM source ORDER BY reference'
            )->fetchAll();
        } catch (\Throwable $e) {
            $this->error('Bootstrap interrompu : '.$e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $politique = PolitiqueSources::POLITIQUE;
        $source = self::SOURCE;
        $repris = 0;

        foreach ($legacy as $ligne) {
            $reference = (string) $ligne['reference'];
            $existante = $registre->resoudreSource($reference);

            if ($existante === null) {
                $inscription = $registre->inscrireSource([
                    'reference' => $reference,
                    'nom_canonique' => (string) $ligne['titre'],
                    'nom_affichage' => (string) $ligne['titre'],
                    'type_source' => self::TYPE_IMPORT,
                    'proprietaire_reference' => $acteur,
                    'categorie' => $ligne['categorie'],
                    'authenticite_legacy' => $ligne['authenticite'],
                    'reserve' => $ligne['reserve'],
                    'source' => $source,
                    'producteur' => $acteur,
                    'politique' => $politique,
                    'preuve' => "BOOT-CAP-CORE-006-{$reference}-INSCRIPTION",
                ]);
                if (isset($inscription['refus'])) {
                    $this->error("{$reference} : inscription refusée — {$inscription['refus']} ({$inscription['detail']})");

                    return self::FAILURE;
                }
                $this->info("{$reference} : inscrite en PREPARATION.");
            } else {
                $this->line("{$reference} : déjà inscrite, aucun doublon créé.");
            }

            $etat = $registre->resoudreEtat($reference);
            if (($etat['etat'] ?? null) === 'ACTIVE') {
                $this->line("{$reference} : déjà au cycle cible (ACTIVE).");
                $repris++;

                continue;
            }

            $activation = $registre->activerSource($reference, [
                'politique' => $politique, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-006-{$reference}-ACTIVATION",
                'motif' => 'reprise d’une source déjà en lecture active via CTR-04 avant ce chantier',
            ]);
            if (isset($activation['refus'])) {
                $this->error("{$reference} : activation refusée — {$activation['refus']}");

                return self::FAILURE;
            }
            $this->info("{$reference} : cycle → ACTIVE.");
            $repris++;
        }

        $this->newLine();
        $this->info("Bootstrap CAP-CORE-006 terminé. {$repris} source(s) reprise(s) depuis l’index. Aucune source n’a été inventée ; aucun doublon créé.");

        return self::SUCCESS;
    }
}
