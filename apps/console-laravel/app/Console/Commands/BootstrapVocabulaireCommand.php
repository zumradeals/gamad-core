<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Gamad\RegistreVocabulaire\PolitiqueVocabulaire;
use Gamad\RegistreVocabulaire\RegistreVocabulaire;
use Illuminate\Console\Command;

/**
 * Bootstrap idempotent du registre du vocabulaire canonique (CAP-CORE-010).
 *
 * Deux temps :
 *
 * 1. `POL-VOCABULAIRE-V1` n'existait pas avant ce chantier : comme
 *    `POL-CONTRATS-V1` pour CAP-CORE-009, ce n'est pas une reprise de
 *    l'existant mais l'auto-gouvernance sans laquelle `AccesVocabulaire`
 *    resterait bloquée à 403 dès le premier appel.
 * 2. `core/registre-vocabulaire/resources/bootstrap-vocabulaire-v1.json` —
 *    vingt-quatre vocabulaires établis par audit du code réel (constantes
 *    PHP déjà appliquées par contrainte CHECK SQL ou vérification PHP dans
 *    CAP-CORE-001, 006, 007, 009 et 011). Aucun terme inventé.
 *
 * Idempotent : rejouer cette commande ne crée aucun doublon et ne réactive
 * pas une version déjà active.
 */
final class BootstrapVocabulaireCommand extends Command
{
    protected $signature = 'core:vocabulaire:bootstrap';

    protected $description = "Établit POL-VOCABULAIRE-V1 puis reprend l'inventaire de bootstrap-vocabulaire-v1.json vers le registre persistant CAP-CORE-010.";

    private const RESSOURCE = __DIR__ . '/../../../../../core/registre-vocabulaire/resources/bootstrap-vocabulaire-v1.json';

    private const EMPREINTE_SHA256 = 'cf57640eafe218f1ae904d5638b23a741b1f509526f0b9a4b124614fca5999d4';

    private const SOURCE = 'core/registre-vocabulaire/resources/bootstrap-vocabulaire-v1.json — bootstrap CAP-CORE-010';

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
                throw new \RuntimeException(sprintf('empreinte invalide : attendu %s, obtenu %s', self::EMPREINTE_SHA256, $empreinte));
            }
            $payload = json_decode($brut, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || !is_array($payload['vocabulaires'] ?? null)) {
                throw new \RuntimeException('format de ressource invalide');
            }

            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $magasinPolitiques = PolitiquesMagasin::connecter();
            $registrePolitiques = new RegistrePolitiques($index, $registreIdentites, $magasinPolitiques, $ctr01);
            $magasinVocabulaire = VocabulaireMagasin::connecter();
            $registre = new RegistreVocabulaire($index, $registreIdentites, $magasinVocabulaire, $ctr01);
        } catch (\Throwable $e) {
            $this->error('Bootstrap interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;

        if (!$this->bootstrapAutoGouvernance($registrePolitiques, $acteur)) {
            return self::FAILURE;
        }

        $repris = 0;
        $termesRepris = 0;
        foreach ($payload['vocabulaires'] as $ligne) {
            $resultat = $this->reprendreVocabulaire($registre, $ligne, $acteur);
            if ($resultat === false) {
                return self::FAILURE;
            }
            $repris++;
            $termesRepris += count($ligne['termes']);
        }

        $this->newLine();
        $this->info("Bootstrap CAP-CORE-010 terminé. {$repris} vocabulaire(s), {$termesRepris} terme(s) repris. Aucun terme n’a été inventé ; aucun doublon créé.");

        return self::SUCCESS;
    }

    private function reprendreVocabulaire(RegistreVocabulaire $registre, array $ligne, string $acteur): bool
    {
        $reference = (string) $ligne['reference'];
        $version = '1.0.0';
        $politiqueAdmin = PolitiqueVocabulaire::POLITIQUE;
        $source = self::SOURCE;

        if ($registre->resoudreVocabulaire($reference) === null) {
            $inscription = $registre->inscrireVocabulaire([
                'reference' => $reference, 'namespace' => $ligne['namespace'], 'nom' => $ligne['nom'],
                'domaine' => $ligne['domaine'], 'portee' => $ligne['portee'], 'proprietaire_reference' => $acteur,
                'source_reference' => $ligne['source_reference'], 'description' => $ligne['description'] ?? null,
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-INSCRIPTION",
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
                'version' => $version, 'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']} ({$creation['detail']})");

                return false;
            }

            foreach ($ligne['termes'] as $i => $terme) {
                $ajout = $registre->ajouterTerme($reference, $version, [
                    ...$terme, 'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-TERME-{$i}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : terme refusé — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']} ({$soumission['detail']})");

                return false;
            }

            $analyse = $registre->analyserCompatibilite($reference, $version, [
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-ANALYSE",
            ]);
            if (isset($analyse['refus'])) {
                $this->error("{$reference} {$version} : analyse refusée — {$analyse['refus']} ({$analyse['detail']})");

                return false;
            }

            $projection = $registre->genererProjection($reference, $version, [
                'type_projection' => 'JSON', 'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-PROJECTION",
            ]);
            if (isset($projection['refus'])) {
                $this->error("{$reference} {$version} : projection refusée — {$projection['refus']} ({$projection['detail']})");

                return false;
            }

            $conformite = $registre->enregistrerConformite($reference, $version, [
                'resultat' => 'CONFORME', 'consommateur_reference' => 'CAP-CORE-010', 'type_consommateur' => 'CAPACITE',
                'commit_reference' => 'commit:' . self::commitCourant(),
                'rapport_resume_json' => json_encode(['motif' => 'reprise fidèle — première version, rien à rompre']),
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-CONFORMITE",
            ]);
            if (isset($conformite['refus'])) {
                $this->error("{$reference} {$version} : conformité refusée — {$conformite['refus']} ({$conformite['detail']})");

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-ACTIVATION",
            'motif' => 'reprise de l’inventaire des vocabulaires déjà exploités avant ce chantier',
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

            return $hash !== false ? substr(trim($hash), 0, 12) : 'HEAD';
        }

        return substr($tete, 0, 12);
    }

    /**
     * `POL-VOCABULAIRE-V1` : seize actions, une par action que
     * `AccesVocabulaire` soumet réellement à CTR-03. Seule l'autorité
     * d'inscription les exerce, comme pour les autres registres techniques.
     */
    private function bootstrapAutoGouvernance(RegistrePolitiques $registre, string $acteur): bool
    {
        $reference = PolitiqueVocabulaire::POLITIQUE;
        $version = '1.0.0';
        $politiqueAdmin = $reference;
        $source = self::SOURCE;
        $actions = [
            PolitiqueVocabulaire::ACTION_INSCRIRE,
            PolitiqueVocabulaire::ACTION_VERSION_CREER,
            PolitiqueVocabulaire::ACTION_TERME_AJOUTER,
            PolitiqueVocabulaire::ACTION_TERME_EVOLUER,
            PolitiqueVocabulaire::ACTION_TERME_MODIFIER,
            PolitiqueVocabulaire::ACTION_ALIAS_AJOUTER,
            PolitiqueVocabulaire::ACTION_MAPPING_AJOUTER,
            PolitiqueVocabulaire::ACTION_USAGE_DECLARER,
            PolitiqueVocabulaire::ACTION_VERSION_SOUMETTRE,
            PolitiqueVocabulaire::ACTION_VERSION_ANALYSER,
            PolitiqueVocabulaire::ACTION_VERSION_ACTIVER,
            PolitiqueVocabulaire::ACTION_VERSION_DEPRECIER,
            PolitiqueVocabulaire::ACTION_VERSION_RETIRER,
            PolitiqueVocabulaire::ACTION_PROJECTION_GENERER,
            PolitiqueVocabulaire::ACTION_CONFORMITE_ENREGISTRER,
        ];
        $regles = array_map(static fn (string $action): array => [
            'effet' => 'PERMET', 'action' => $action, 'sujet_type' => $acteur,
            'motif' => "Seule l'autorité d'inscription exerce « {$action} » sur le registre du vocabulaire.",
        ], $actions);

        if ($registre->resoudrePolitique($reference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $reference, 'libelle' => 'Politique technique d’administration du registre du vocabulaire',
                'proprietaire_reference' => $acteur, 'source_reference' => 'CAP-CORE-010 — auto-gouvernance du registre du vocabulaire',
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-INSCRIPTION",
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
                'version' => $version, 'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }

            $numero = 0;
            foreach ($regles as $r) {
                $numero++;
                $ajout = $registre->ajouterRegle($reference, $version, [
                    'effet' => $r['effet'], 'action_reference' => $r['action'], 'sujet_reference' => $r['sujet_type'], 'motif' => $r['motif'],
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-REGLE-{$numero}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : règle refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }

            $cas = array_map(static fn (array $r): array => ['sujet' => $r['sujet_type'], 'action' => $r['action'], 'attendu' => 'PERMIS'], $regles);
            $simulation = $registre->simulerVersion($reference, $version, [
                'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}", 'cas' => $cas,
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-SIMULATION",
            ]);
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-010-{$reference}-{$version}-ACTIVATION",
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
