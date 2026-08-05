<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\MoteurMatching\PolitiqueMatching;
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
 * Bootstrap idempotent de CAP-CORE-021 : `POL-MATCHING-V1` (sans laquelle
 * toute commande gouvernée reste bloquée en refus par défaut) et les douze
 * contrats `CTR-MAT-*` (doc de chantier 04 §11).
 *
 * Ne crée aucun contexte, aucun consommateur, aucune donnée métier — la
 * fiche l'interdit explicitement (« aucun second consommateur imaginaire »,
 * doc 03 §30). Les contextes et profils d'exécution sont bootstrapés par
 * `core:matching:bootstrap` une fois le compilateur de politique livré.
 *
 * `type_contrat = COMMANDE` (pas `HTTP_API`), même choix que CAP-CORE-014,
 * 015 et 016 : un contrat `HTTP_API` exige au moins une partie CONSOMMATEUR
 * déclarée avant soumission (`RegistreContrats::soumettreVersion`), et
 * aucun consommateur réel n'existe à ce stade du bootstrap. La surface HTTP
 * elle-même est décrite dans `openapi/core-v1.yaml`, pas dans ce type.
 *
 * Idempotent : rejouer cette commande ne crée aucun doublon.
 */
final class BootstrapMatchingCommand extends Command
{
    protected $signature = 'core:matching:bootstrap-gouvernance';

    protected $description = 'Établit POL-MATCHING-V1 et les douze contrats CTR-MAT-* de CAP-CORE-021 — aucun contexte ni consommateur inventé.';

    private const SOURCE = 'CAP-CORE-021 — bootstrap de gouvernance du moteur de Matching';

    /** @var list<array{reference:string,nom:string,operation:string}> */
    private const CONTRATS = [
        ['reference' => 'CTR-MAT-01', 'nom' => 'Soumission d’une demande de Matching', 'operation' => 'matching.demande.soumettre'],
        ['reference' => 'CTR-MAT-02', 'nom' => 'Résolution de population candidate', 'operation' => 'matching.population.resoudre'],
        ['reference' => 'CTR-MAT-03', 'nom' => 'Acquisition de signaux matérialisés', 'operation' => 'matching.signal.acquerir'],
        ['reference' => 'CTR-MAT-04', 'nom' => 'Interrogation minimale d’une source', 'operation' => 'matching.question.interroger'],
        ['reference' => 'CTR-MAT-05', 'nom' => 'Exécution et résultat de Matching', 'operation' => 'matching.execution.executer'],
        ['reference' => 'CTR-MAT-06', 'nom' => 'Explication d’un résultat', 'operation' => 'matching.resultat.expliquer'],
        ['reference' => 'CTR-MAT-07', 'nom' => 'Construction et vérification de segment', 'operation' => 'matching.segment.construire'],
        ['reference' => 'CTR-MAT-08', 'nom' => 'Activation d’un segment', 'operation' => 'matching.segment.activer'],
        ['reference' => 'CTR-MAT-09', 'nom' => 'Mesure d’une activation', 'operation' => 'matching.activation.mesurer'],
        ['reference' => 'CTR-MAT-10', 'nom' => 'Contestation et réexamen', 'operation' => 'matching.contestation.ouvrir'],
        ['reference' => 'CTR-MAT-11', 'nom' => 'Comparaison de politiques', 'operation' => 'matching.politique.comparer'],
        ['reference' => 'CTR-MAT-12', 'nom' => 'Preuve et paquet de Matching', 'operation' => 'matching.paquet.exporter'],
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
        foreach (self::CONTRATS as $contrat) {
            if (!$this->etablirContrat($registreContrats, $acteur, $contrat['reference'], $contrat['nom'], $contrat['operation'])) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Bootstrap de gouvernance CAP-CORE-021 terminé : POL-MATCHING-V1 et douze contrats CTR-MAT-* établis.');

        return self::SUCCESS;
    }

    private function bootstrapAutoGouvernance(RegistrePolitiques $registre, string $acteur): bool
    {
        $reference = PolitiqueMatching::POLITIQUE;
        $version = '1.0.0';
        $source = self::SOURCE;

        if ($registre->resoudrePolitique($reference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $reference,
                'libelle' => 'Politique technique d’administration du moteur de Matching',
                'proprietaire_reference' => $acteur,
                'source_reference' => self::SOURCE,
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-021-{$reference}-INSCRIPTION",
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
                'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }

            $numero = 0;
            foreach (PolitiqueMatching::ACTIONS as $action) {
                $numero++;
                $ajout = $registre->ajouterRegle($reference, $version, [
                    'effet' => 'PERMET', 'action_reference' => $action, 'sujet_reference' => $acteur,
                    'motif' => "Seule l'autorité d'inscription exerce « {$action} » sur le moteur de Matching.",
                    'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-REGLE-{$numero}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : règle {$action} refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }

            $cas = array_map(static fn (string $action): array => ['sujet' => $acteur, 'action' => $action, 'attendu' => 'PERMIS'], PolitiqueMatching::ACTIONS);
            $simulation = $registre->simulerVersion($reference, $version, [
                'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}", 'cas' => $cas,
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-SIMULATION",
            ]);
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première écriture gouvernée sur ce registre',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }

    private function etablirContrat(RegistreContrats $registre, string $acteur, string $reference, string $nom, string $operation): bool
    {
        $version = '1.0.0';
        $source = self::SOURCE;

        if ($registre->resoudreContrat($reference) === null) {
            $inscription = $registre->inscrireContrat([
                'reference' => $reference, 'nom' => "Moteur de Matching — {$nom}", 'type_contrat' => 'COMMANDE',
                'finalite_reference' => 'EXPLOITATION_MATCHING', 'producteur_capacite_reference' => PolitiqueMatching::CAPACITE,
                'proprietaire_reference' => $acteur, 'source_reference' => self::SOURCE,
                'description' => "{$nom} — CAP-CORE-021.",
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-021-{$reference}-INSCRIPTION",
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
                'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }
            $partie = $registre->declarerPartie($reference, $version, [
                'role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => PolitiqueMatching::CAPACITE,
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-PARTIE",
            ]);
            if (isset($partie['refus'])) {
                $this->error("{$reference} {$version} : partie refusée — {$partie['refus']}");

                return false;
            }
            $ajoutOperation = $registre->declarerOperation($reference, $version, [
                'reference_operation' => $operation, 'type_operation' => 'COMMANDER',
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-OPERATION",
            ]);
            if (isset($ajoutOperation['refus'])) {
                $this->error("{$reference} {$version} : opération {$operation} refusée — {$ajoutOperation['refus']}");

                return false;
            }
            foreach (['FINALITE', 'EXPIRATION', 'AUDIT', 'AUTORISATION'] as $typeObligation) {
                $obligation = $registre->declarerObligation($reference, $version, [
                    'type_obligation' => $typeObligation,
                    'description' => "Toute opération de {$nom} respecte l'obligation {$typeObligation} du Matching (finalité exacte, expiration, audit et autorisation obligatoires).",
                    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-OBLIGATION-{$typeObligation}",
                ]);
                if (isset($obligation['refus'])) {
                    $this->error("{$reference} {$version} : obligation {$typeObligation} refusée — {$obligation['refus']}");

                    return false;
                }
            }
            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }
        }

        $analyse = $registre->analyserCompatibilite($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-ANALYSE",
        ]);
        if (isset($analyse['refus'])) {
            $this->error("{$reference} {$version} : analyse refusée — {$analyse['refus']}");

            return false;
        }
        $conformite = $registre->enregistrerConformite($reference, $version, [
            'resultat' => 'CONFORME', 'artefact_reference' => 'BOOTSTRAP-CAP-CORE-021',
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-CONFORMITE",
        ]);
        if (isset($conformite['refus'])) {
            $this->error("{$reference} {$version} : conformité refusée — {$conformite['refus']}");

            return false;
        }
        $activation = $registre->activerVersion($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-021-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première commande technique sur ce contrat',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }
}
