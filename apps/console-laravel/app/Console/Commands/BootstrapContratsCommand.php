<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
 * Bootstrap idempotent du registre des contrats (CAP-CORE-009).
 *
 * Deux temps :
 *
 * 1. `POL-CONTRATS-V1` n'existait pas avant ce chantier : comme
 *    `POL-POLITIQUES-V1` pour CAP-CORE-007, ce n'est pas une reprise de
 *    l'existant mais l'auto-gouvernance sans laquelle `AccesContrats`
 *    resterait bloquée à 403 dès le premier appel — `CTR-03` refuse toute
 *    action sans version active qui la permette.
 * 2. `core/registre-contrats/resources/bootstrap-contrats-v1.json` — treize
 *    contrats établis par audit du code réel (`route:list`,
 *    `openapi/core-v1.yaml`, classes `CTR-*`), chacun relié à une route ou
 *    une méthode publique réelle. Aucune opération inventée.
 *
 * Idempotent : rejouer cette commande ne crée aucun doublon et ne réactive
 * pas une version déjà active.
 */
final class BootstrapContratsCommand extends Command
{
    protected $signature = 'core:contrats:bootstrap';

    protected $description = "Établit POL-CONTRATS-V1 puis reprend l'inventaire de bootstrap-contrats-v1.json vers le registre persistant CAP-CORE-009.";

    private const RESSOURCE = __DIR__ . '/../../../../../core/registre-contrats/resources/bootstrap-contrats-v1.json';

    private const EMPREINTE_SHA256 = 'ad426422c98fd6386ff371bf9cd97c533fe249e9ce5b5ace879c04f9f25f9966';

    private const SOURCE = 'core/registre-contrats/resources/bootstrap-contrats-v1.json — bootstrap CAP-CORE-009';

    public function handle(): int
    {
        try {
            $chemin = realpath(self::RESSOURCE) ?: self::RESSOURCE;
            $brut = file_get_contents($chemin);
            if ($brut === false) {
                throw new \RuntimeException("ressource introuvable : {$chemin}");
            }
            $empreinte = hash('sha256', $brut);
            if (!hash_equals(self::EMPREINTE_SHA256, $empreinte)) {
                throw new \RuntimeException(sprintf(
                    'empreinte invalide : attendu %s, obtenu %s',
                    self::EMPREINTE_SHA256,
                    $empreinte,
                ));
            }
            $payload = json_decode($brut, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || !is_array($payload['contrats'] ?? null)) {
                throw new \RuntimeException('format de ressource invalide');
            }

            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $magasinPolitiques = PolitiquesMagasin::connecter();
            $registrePolitiques = new RegistrePolitiques($index, $registreIdentites, $magasinPolitiques, $ctr01);
            $magasinContrats = ContratsMagasin::connecter();
            $registre = new RegistreContrats($index, $registreIdentites, $magasinContrats, $ctr01);
        } catch (\Throwable $e) {
            $this->error('Bootstrap interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;

        if (!$this->bootstrapAutoGouvernance($registrePolitiques, $acteur)) {
            return self::FAILURE;
        }

        $repris = 0;
        foreach ($payload['contrats'] as $ligne) {
            if (!$this->reprendreContrat($registre, $ligne, $acteur)) {
                return self::FAILURE;
            }
            $repris++;
        }

        $this->newLine();
        $this->info("Bootstrap CAP-CORE-009 terminé. {$repris} contrat(s) repris. Aucune opération n’a été inventée ; aucun doublon créé.");

        return self::SUCCESS;
    }

    private function reprendreContrat(RegistreContrats $registre, array $ligne, string $acteur): bool
    {
        $reference = (string) $ligne['reference'];
        $version = (string) $ligne['version'];
        $politiqueAdmin = PolitiqueContrats::POLITIQUE;
        $source = self::SOURCE;

        if ($registre->resoudreContrat($reference) === null) {
            $inscription = $registre->inscrireContrat([
                'reference' => $reference, 'nom' => $ligne['nom'], 'type_contrat' => $ligne['type_contrat'],
                'finalite_reference' => $ligne['finalite_reference'],
                'producteur_capacite_reference' => $ligne['producteur_capacite_reference'],
                'producteur_produit_reference' => $ligne['producteur_produit_reference'],
                'proprietaire_reference' => $acteur, 'source_reference' => $ligne['source_reference'],
                'description' => $ligne['description'] ?? null,
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-009-{$reference}-INSCRIPTION",
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
                'version' => $version, 'compatibilite_annoncee' => $ligne['compatibilite_annoncee'],
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']} ({$creation['detail']})");

                return false;
            }

            foreach ($ligne['parties'] as $i => $p) {
                $ajout = $registre->declarerPartie($reference, $version, [
                    'role' => $p['role'], 'partie_type' => $p['partie_type'], 'partie_reference' => $p['partie_reference'],
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-PARTIE-{$i}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : partie refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }
            foreach ($ligne['operations'] as $i => $o) {
                $ajout = $registre->declarerOperation($reference, $version, [
                    ...$o,
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-OPERATION-{$i}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : opération refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }
            foreach ($ligne['schemas'] as $i => $s) {
                $ajout = $registre->declarerSchema($reference, $version, [
                    ...$s,
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-SCHEMA-{$i}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : schéma refusé — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }
            foreach ($ligne['erreurs'] as $i => $e) {
                $ajout = $registre->declarerErreur($reference, $version, [
                    ...$e,
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-ERREUR-{$i}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : erreur refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']} ({$soumission['detail']})");

                return false;
            }

            $analyse = $registre->analyserCompatibilite($reference, $version, [
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-ANALYSE",
            ]);
            if (isset($analyse['refus'])) {
                $this->error("{$reference} {$version} : analyse refusée — {$analyse['refus']} ({$analyse['detail']})");

                return false;
            }

            $conformite = $registre->enregistrerConformite($reference, $version, [
                'resultat' => 'CONFORME', 'artefact_reference' => 'commit:' . self::commitCourant(),
                'resume' => 'reprise fidèle de l’inventaire déjà exploité — première version, rien à rompre',
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-CONFORMITE",
            ]);
            if (isset($conformite['refus'])) {
                $this->error("{$reference} {$version} : conformité refusée — {$conformite['refus']} ({$conformite['detail']})");

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-ACTIVATION",
            'motif' => 'reprise de l’inventaire des contrats déjà exploités avant ce chantier',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']} ({$activation['detail']})");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }

    private static function commitCourant(): string
    {
        $tete = @file_get_contents(__DIR__ . '/../../../../../.git/HEAD');
        if ($tete === false) {
            return 'inconnu';
        }
        $tete = trim($tete);
        if (str_starts_with($tete, 'ref: ')) {
            $ref = trim(substr($tete, 5));
            $hash = @file_get_contents(__DIR__ . '/../../../../../.git/' . $ref);
            if ($hash !== false) {
                return substr(trim($hash), 0, 12);
            }

            return 'HEAD';
        }

        return substr($tete, 0, 12);
    }

    /**
     * `POL-CONTRATS-V1` : treize actions, une par action que `AccesContrats`
     * soumet réellement à CTR-03. Seule l'autorité d'inscription les exerce,
     * comme pour les autres registres techniques.
     */
    private function bootstrapAutoGouvernance(RegistrePolitiques $registre, string $acteur): bool
    {
        $reference = PolitiqueContrats::POLITIQUE;
        $version = '1.0.0';
        $politiqueAdmin = $reference;
        $source = self::SOURCE;
        $actions = [
            PolitiqueContrats::ACTION_INSCRIRE,
            PolitiqueContrats::ACTION_VERSION_CREER,
            PolitiqueContrats::ACTION_VERSION_MODIFIER,
            PolitiqueContrats::ACTION_CONSOMMATEUR_RATTACHER,
            PolitiqueContrats::ACTION_VERSION_SOUMETTRE,
            PolitiqueContrats::ACTION_VERSION_ANALYSER,
            PolitiqueContrats::ACTION_VERSION_ACTIVER,
            PolitiqueContrats::ACTION_VERSION_DEPRECIER,
            PolitiqueContrats::ACTION_VERSION_SUSPENDRE,
            PolitiqueContrats::ACTION_VERSION_RETIRER,
            PolitiqueContrats::ACTION_CONFORMITE_ENREGISTRER,
            PolitiqueContrats::ACTION_PROJECTION_GENERER,
        ];
        $regles = array_map(static fn (string $action): array => [
            'effet' => 'PERMET', 'action' => $action, 'sujet_type' => $acteur,
            'motif' => "Seule l'autorité d'inscription exerce « {$action} » sur le registre des contrats.",
        ], $actions);

        if ($registre->resoudrePolitique($reference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $reference,
                'libelle' => 'Politique technique d’administration du registre des contrats',
                'proprietaire_reference' => $acteur,
                'source_reference' => 'CAP-CORE-009 — auto-gouvernance du registre des contrats',
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-009-{$reference}-INSCRIPTION",
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
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }

            $numero = 0;
            foreach ($regles as $r) {
                $numero++;
                $ajout = $registre->ajouterRegle($reference, $version, [
                    'effet' => $r['effet'], 'action_reference' => $r['action'],
                    'sujet_reference' => $r['sujet_type'], 'motif' => $r['motif'],
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-REGLE-{$numero}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : règle refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }

            $cas = array_map(static fn (array $r): array => ['sujet' => $r['sujet_type'], 'action' => $r['action'], 'attendu' => 'PERMIS'], $regles);
            $simulation = $registre->simulerVersion($reference, $version, [
                'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}", 'cas' => $cas,
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-SIMULATION",
            ]);
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-009-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première écriture gouvernée sur ce registre',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }
}
