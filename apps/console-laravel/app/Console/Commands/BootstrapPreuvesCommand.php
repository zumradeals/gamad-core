<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\PolitiqueContrats;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Gamad\RegistrePreuves\Canonicaliseur;
use Gamad\RegistrePreuves\Magasin as PreuvesMagasin;
use Gamad\RegistrePreuves\PolitiquePreuves;
use Gamad\RegistrePreuves\RegistrePreuves;
use Illuminate\Console\Command;

/**
 * Bootstrap idempotent de CAP-CORE-015.
 *
 * Trois temps : `POL-PREUVES-V1` (sans laquelle toute commande gouvernée
 * reste bloquée en refus par défaut), sept contrats techniques
 * `CTR-GAMAD-PREUVE-*`, puis une unique preuve réelle et vérifiable :
 * l'empreinte SHA-256 de la baseline opérationnelle actuellement chargée —
 * `signature_absente`, jamais présentée comme antidatée (fiche partie 3
 * §16 : « ne pas dater une preuve historique comme si CAP-CORE-015 l'avait
 * émise à l'époque »).
 *
 * Idempotent : rejouer cette commande ne crée aucun doublon.
 */
final class BootstrapPreuvesCommand extends Command
{
    protected $signature = 'core:preuves:bootstrap';

    protected $description = "Établit POL-PREUVES-V1, les contrats techniques et l'empreinte de la baseline opérationnelle — aucune signature rétroactive.";

    private const SOURCE = 'CAP-CORE-015 — bootstrap du registre des preuves';

    private const ACTIONS = [
        PolitiquePreuves::ACTION_LIRE,
        PolitiquePreuves::ACTION_PREPARER,
        PolitiquePreuves::ACTION_EMPREINTE_EMETTRE,
        PolitiquePreuves::ACTION_SIGNATURE_EMETTRE,
        PolitiquePreuves::ACTION_ATTESTATION_EMETTRE,
        PolitiquePreuves::ACTION_MANIFESTE_EMETTRE,
        PolitiquePreuves::ACTION_CHECKPOINT_EMETTRE,
        PolitiquePreuves::ACTION_VERIFIER,
        PolitiquePreuves::ACTION_LOT_VERIFIER,
        PolitiquePreuves::ACTION_REVOQUER,
        PolitiquePreuves::ACTION_SUSPENDRE,
        PolitiquePreuves::ACTION_COMPROMISSION_DECLARER,
        PolitiquePreuves::ACTION_PAQUET_EXPORTER,
        PolitiquePreuves::ACTION_DIAGNOSTIC_LIRE,
    ];

    /** @var list<string> */
    private const OPERATIONS_CONTRAT = [
        'preuve.resoudre', 'preuve.verifier', 'preuve.paquet.verifier', 'preuve.emettre',
        'preuve.revoquer', 'manifeste.resoudre', 'attestation.resoudre',
    ];

    public function handle(): int
    {
        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $registrePolitiques = new RegistrePolitiques($index, $registreIdentites, PolitiquesMagasin::connecter(), $ctr01);
            $registreContrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);
            $registrePreuves = new RegistrePreuves(PreuvesMagasin::connecter());
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
        if (!$this->bootstrapEmpreinteBaseline($registrePreuves, $acteur)) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Bootstrap CAP-CORE-015 terminé : POL-PREUVES-V1, sept contrats techniques et empreinte de baseline établis.');

        return self::SUCCESS;
    }

    private function bootstrapAutoGouvernance(RegistrePolitiques $registre, string $acteur): bool
    {
        $reference = PolitiquePreuves::POLITIQUE;
        $version = '1.0.0';
        $source = self::SOURCE;

        if ($registre->resoudrePolitique($reference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $reference,
                'libelle' => 'Politique technique d’administration du registre des preuves d’intégrité',
                'proprietaire_reference' => $acteur,
                'source_reference' => self::SOURCE,
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-015-{$reference}-INSCRIPTION",
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
                'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-VERSION",
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
                    'motif' => "Seule l'autorité d'inscription exerce « {$action} » sur le registre des preuves d'intégrité.",
                    'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-REGLE-{$numero}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : règle {$action} refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }

            $cas = array_map(static fn (string $action): array => ['sujet' => $acteur, 'action' => $action, 'attendu' => 'PERMIS'], self::ACTIONS);
            $simulation = $registre->simulerVersion($reference, $version, [
                'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}", 'cas' => $cas,
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-SIMULATION",
            ]);
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-ACTIVATION",
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
            $reference = 'CTR-GAMAD-PREUVE-' . strtoupper(str_replace(['.', '-'], '_', $operation));
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
                'reference' => $reference, 'nom' => "Registre des preuves d'intégrité — {$operation}", 'type_contrat' => 'COMMANDE',
                'finalite_reference' => 'EXPLOITATION_PREUVES_INTEGRITE', 'producteur_capacite_reference' => PolitiquePreuves::CAPACITE,
                'proprietaire_reference' => $acteur, 'source_reference' => self::SOURCE,
                'description' => "Opération technique interne {$operation} de CAP-CORE-015.",
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-015-{$reference}-INSCRIPTION",
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
                'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }
            $partie = $registre->declarerPartie($reference, $version, [
                'role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => PolitiquePreuves::CAPACITE,
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-PARTIE",
            ]);
            if (isset($partie['refus'])) {
                $this->error("{$reference} {$version} : partie refusée — {$partie['refus']}");

                return false;
            }
            $ajoutOperation = $registre->declarerOperation($reference, $version, [
                'reference_operation' => $operation, 'type_operation' => 'COMMANDER',
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-OPERATION-{$rang}",
            ]);
            if (isset($ajoutOperation['refus'])) {
                $this->error("{$reference} {$version} : opération {$operation} refusée — {$ajoutOperation['refus']}");

                return false;
            }
            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }
        }

        $analyse = $registre->analyserCompatibilite($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-ANALYSE",
        ]);
        if (isset($analyse['refus'])) {
            $this->error("{$reference} {$version} : analyse refusée — {$analyse['refus']}");

            return false;
        }
        $conformite = $registre->enregistrerConformite($reference, $version, [
            'resultat' => 'CONFORME', 'artefact_reference' => 'BOOTSTRAP-CAP-CORE-015',
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-CONFORMITE",
        ]);
        if (isset($conformite['refus'])) {
            $this->error("{$reference} {$version} : conformité refusée — {$conformite['refus']}");

            return false;
        }
        $activation = $registre->activerVersion($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-015-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première commande technique sur ce registre',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }

    private function bootstrapEmpreinteBaseline(RegistrePreuves $registre, string $acteur): bool
    {
        $idempotencyKey = 'BASELINE-OPERATIONNELLE-COURANTE';
        $g = [
            'politique' => PolitiquePreuves::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'BOOT-CAP-CORE-015-BASELINE',
        ];
        $preparation = $registre->preparerPreuve(array_merge($g, [
            'type_preuve' => 'EMPREINTE_ARTEFACT', 'sujet_type' => 'BASELINE_OPERATIONNELLE',
            'sujet_reference' => 'index-baseline-v1.json', 'producteur_capacite_reference' => 'CAP-CORE-007',
            'realm_reference' => 'RLM-GAMAD-CORE', 'finalite_reference' => 'INTEGRITE_BASELINE',
            'source_reference' => self::SOURCE, 'classification' => 'INTERNE',
            'idempotency_key' => $idempotencyKey,
            'representation' => [
                'format_representation' => 'OCTETS_BRUTS', 'media_type' => 'application/json',
                'metadonnees' => ['observee_le' => gmdate('c'), 'signature' => 'absente'],
            ],
        ]));
        if (isset($preparation['refus'])) {
            $this->error("Empreinte de baseline : refus — {$preparation['refus']} ({$preparation['detail']})");

            return false;
        }
        if ($preparation['idempotent'] ?? false) {
            $this->line("Empreinte de baseline : déjà bootstrapée ({$preparation['reference']}), aucun doublon créé.");

            return true;
        }

        $cheminBaseline = dirname(__DIR__, 5) . '/core/registre-normes/resources/index-baseline-v1.json';
        $contenuBaseline = file_get_contents($cheminBaseline);
        if ($contenuBaseline === false) {
            $this->error("Empreinte de baseline : fichier introuvable ({$cheminBaseline}).");

            return false;
        }
        $resultat = $registre->emettreEmpreinte((string) $preparation['reference'], 'SHA-256', $contenuBaseline, $g);
        if (isset($resultat['refus'])) {
            $this->error("Empreinte de baseline : refus à l'émission — {$resultat['refus']} ({$resultat['detail']})");

            return false;
        }
        $empreinteAttendue = BaselineOperationnelle::standard()->empreinte();
        if (!hash_equals($empreinteAttendue, $resultat['empreinte_hex'])) {
            $this->error("Empreinte de baseline : divergence avec BaselineOperationnelle::empreinte() — {$resultat['empreinte_hex']} ≠ {$empreinteAttendue}.");

            return false;
        }
        $this->info("Empreinte de baseline : {$preparation['reference']} — {$resultat['empreinte_hex']} (signature_absente, {$resultat['etat']}), concordante avec CAP-CORE-007.");

        return true;
    }
}
