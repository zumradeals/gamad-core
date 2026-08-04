<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalEvenements\PolitiqueEvenements;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\PolitiqueContrats;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Illuminate\Console\Command;

/**
 * Bootstrap idempotent de l'auto-gouvernance de CAP-CORE-014.
 *
 * Établit uniquement :
 *
 * 1. `POL-EVENEMENTS-V1` (CAP-CORE-007) — sans laquelle toute commande
 *    gouvernée du journal d'événements resterait bloquée en refus par
 *    défaut, comme documenté pour CAP-CORE-012 (fiche §28).
 * 2. Les huit contrats techniques `CTR-GAMAD-EVENEMENT-*` (CAP-CORE-009,
 *    partie 4 §1) décrivant les opérations de CAP-CORE-014 elles-mêmes —
 *    pas les contrats `EVENEMENT` métier par famille, qui restent créés
 *    séparément à mesure que des producteurs réels sont raccordés (voir
 *    `EVT-GAMAD-PRODUIT-*`, déjà établis pour le pilote CAP-CORE-011).
 *
 * N'établit ni bootstrap-evenements-v1.json, ni vocabulaire CAP-CORE-010, ni
 * abonnement pilote : chantiers restants, documentés dans le rapport de PR.
 *
 * Idempotent : rejouer cette commande ne crée aucun doublon.
 */
final class BootstrapEvenementsCommand extends Command
{
    protected $signature = 'core:evenements:bootstrap';

    protected $description = 'Établit POL-EVENEMENTS-V1 et les contrats techniques CTR-GAMAD-EVENEMENT-*.';

    private const SOURCE = 'CAP-CORE-014 — bootstrap du journal des événements';

    /** Une action, une règle, pour l'autorité d'inscription seule (fiche partie 4 §13). */
    private const ACTIONS = [
        PolitiqueEvenements::ACTION_PUBLIER,
        PolitiqueEvenements::ACTION_LIRE,
        PolitiqueEvenements::ACTION_ABONNEMENT_CREER,
        PolitiqueEvenements::ACTION_ABONNEMENT_MODIFIER,
        PolitiqueEvenements::ACTION_ABONNEMENT_ACTIVER,
        PolitiqueEvenements::ACTION_ABONNEMENT_SUSPENDRE,
        PolitiqueEvenements::ACTION_ABONNEMENT_RETIRER,
        PolitiqueEvenements::ACTION_LIVRAISON_ACCUSER,
        PolitiqueEvenements::ACTION_LIVRAISON_REFUSER,
        PolitiqueEvenements::ACTION_REJEU_DEMANDER,
        PolitiqueEvenements::ACTION_LETTRE_MORTE_RELANCER,
        PolitiqueEvenements::ACTION_LETTRE_MORTE_CLOTURER,
        PolitiqueEvenements::ACTION_DIAGNOSTIC_LIRE,
    ];

    /** @var list<string> */
    private const OPERATIONS_CONTRAT = [
        'evenements.publier', 'evenements.lire', 'abonnements.gerer',
        'livraisons.lire', 'livraisons.accuser', 'livraisons.refuser',
        'evenements.rejouer', 'lettres-mortes.gerer',
    ];

    public function handle(): int
    {
        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);

            $registrePolitiques = new RegistrePolitiques($index, $registreIdentites, PolitiquesMagasin::connecter(), $ctr01);
            $registreContrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);
        } catch (\Throwable $e) {
            $this->error('Bootstrap interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;

        if (!$this->bootstrapAutoGouvernance($registrePolitiques, $acteur)) {
            return self::FAILURE;
        }
        if (!$this->bootstrapContratsTechniques($registreContrats, $acteur)) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Bootstrap CAP-CORE-014 terminé : POL-EVENEMENTS-V1 et huit contrats CTR-GAMAD-EVENEMENT-* actifs.');
        $this->line('CAP-CORE-014 reste NO GO : API, console, workers, readiness, sauvegarde et CI restent à livrer.');

        return self::SUCCESS;
    }

    private function bootstrapAutoGouvernance(RegistrePolitiques $registre, string $acteur): bool
    {
        $reference = PolitiqueEvenements::POLITIQUE;
        $version = '1.0.0';
        $source = self::SOURCE;

        if ($registre->resoudrePolitique($reference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $reference,
                'libelle' => 'Politique technique d’administration du journal des événements',
                'proprietaire_reference' => $acteur,
                'source_reference' => 'CAP-CORE-014 — auto-gouvernance du journal des événements',
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-014-{$reference}-INSCRIPTION",
            ]);
            if (isset($inscription['refus'])) {
                $this->error("{$reference} : inscription refusée — {$inscription['refus']} ({$inscription['detail']})");

                return false;
            }
            $this->info("{$reference} : inscrite.");
        } else {
            $this->line("{$reference} : déjà inscrite, aucun doublon créé.");
        }

        $existante = $registre->resoudreVersion($reference, $version);
        if ($existante !== null && $existante['etat'] === 'ACTIVE') {
            $this->line("{$reference} {$version} : déjà active, aucun doublon créé.");

            return true;
        }

        if ($existante === null) {
            $creation = $registre->creerVersion($reference, [
                'version' => $version,
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }

            $numero = 0;
            foreach (self::ACTIONS as $action) {
                $numero++;
                $ajout = $registre->ajouterRegle($reference, $version, [
                    'effet' => 'PERMET', 'action_reference' => $action, 'sujet_reference' => $acteur,
                    'motif' => "Seule l'autorité d'inscription exerce « {$action} » sur le journal des événements.",
                    'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-REGLE-{$numero}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : règle {$action} refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }

            $cas = array_map(static fn (string $action): array => ['sujet' => $acteur, 'action' => $action, 'attendu' => 'PERMIS'], self::ACTIONS);
            $simulation = $registre->simulerVersion($reference, $version, [
                'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}", 'cas' => $cas,
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-SIMULATION",
            ]);
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première écriture gouvernée sur ce registre',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }

    private function bootstrapContratsTechniques(RegistreContrats $registre, string $acteur): bool
    {
        foreach (self::OPERATIONS_CONTRAT as $n => $operation) {
            $reference = 'CTR-GAMAD-' . strtoupper(str_replace(['.', '-'], '_', $operation));
            if (!$this->etablirContratTechnique($registre, $acteur, $reference, $operation, $n)) {
                return false;
            }
        }

        return true;
    }

    private function etablirContratTechnique(RegistreContrats $registre, string $acteur, string $reference, string $operation, int $rang): bool
    {
        $version = '1.0.0';
        $source = self::SOURCE;

        if ($registre->resoudreContrat($reference) === null) {
            $inscription = $registre->inscrireContrat([
                'reference' => $reference, 'nom' => "Journal des événements — {$operation}", 'type_contrat' => 'COMMANDE',
                'finalite_reference' => 'EXPLOITATION_JOURNAL_EVENEMENTS', 'producteur_capacite_reference' => PolitiqueEvenements::CAPACITE,
                'proprietaire_reference' => $acteur, 'source_reference' => 'CAP-CORE-014 — journal des événements',
                'description' => "Opération technique interne {$operation} de CAP-CORE-014.",
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-014-{$reference}-INSCRIPTION",
            ]);
            if (isset($inscription['refus'])) {
                $this->error("{$reference} : inscription refusée — {$inscription['refus']} ({$inscription['detail']})");

                return false;
            }
            $this->info("{$reference} : inscrit.");
        } else {
            $this->line("{$reference} : déjà inscrit, aucun doublon créé.");
        }

        $existante = $registre->resoudreVersion($reference, $version);
        if ($existante !== null && $existante['etat'] === 'ACTIVE') {
            $this->line("{$reference} {$version} : déjà active, aucun doublon créé.");

            return true;
        }

        if ($existante === null) {
            $creation = $registre->creerVersion($reference, [
                'version' => $version, 'compatibilite_annoncee' => 'COMPATIBLE',
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }
            $partie = $registre->declarerPartie($reference, $version, [
                'role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => PolitiqueEvenements::CAPACITE,
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-PARTIE",
            ]);
            if (isset($partie['refus'])) {
                $this->error("{$reference} {$version} : partie refusée — {$partie['refus']}");

                return false;
            }
            $ajoutOperation = $registre->declarerOperation($reference, $version, [
                'reference_operation' => $operation, 'type_operation' => 'COMMANDER',
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-OPERATION-{$rang}",
            ]);
            if (isset($ajoutOperation['refus'])) {
                $this->error("{$reference} {$version} : opération {$operation} refusée — {$ajoutOperation['refus']}");

                return false;
            }
            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }
        }

        $analyse = $registre->analyserCompatibilite($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-ANALYSE",
        ]);
        if (isset($analyse['refus'])) {
            $this->error("{$reference} {$version} : analyse refusée — {$analyse['refus']}");

            return false;
        }
        $conformite = $registre->enregistrerConformite($reference, $version, [
            'resultat' => 'CONFORME', 'artefact_reference' => 'BOOTSTRAP-CAP-CORE-014',
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-CONFORMITE",
        ]);
        if (isset($conformite['refus'])) {
            $this->error("{$reference} {$version} : conformité refusée — {$conformite['refus']}");

            return false;
        }
        $activation = $registre->activerVersion($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-014-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première commande technique sur ce registre',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }
}
