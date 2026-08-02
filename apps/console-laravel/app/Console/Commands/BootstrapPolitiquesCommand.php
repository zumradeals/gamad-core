<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Illuminate\Console\Command;

/**
 * Bootstrap idempotent des politiques et règles déjà connues avant
 * CAP-CORE-007 (CAP-CORE-007).
 *
 * Cette commande n'invente aucune politique et aucune règle : elle reprend
 * fidèlement `core/registre-politiques/resources/bootstrap-politiques-v1.json`
 * — une photographie figée des huit politiques et quarante-deux règles qui
 * vivaient dans `index-baseline-v1.json` au moment de ce chantier, avant leur
 * retrait de l'index reconstructible — et les inscrit comme version `1.0.0`
 * (ou `0.1.0` pour les deux politiques historiques dont la version
 * documentaire était `0.1`), activée.
 *
 * Cette ressource n'est plus lue depuis l'index : `CAP-CORE-007` a retiré
 * `politique`/`regle` de la baseline documentaire une fois tous ses
 * consommateurs migrés vers ce registre persistant. Son empreinte SHA-256 est
 * vérifiée avant toute lecture, comme celle d'`index-baseline-v1.json`.
 *
 * L'activation exige une simulation réussie. Celle produite ici est une
 * reprise, pas un nouveau jugement : pour chaque règle bootstrapée, un cas
 * dont le sujet et l'action sont ceux de la règle elle-même, et dont l'issue
 * attendue est l'effet de cette même règle. Une version qui rejoue exactement
 * les décisions déjà adoptées se simule donc nécessairement elle-même avec
 * succès ; ce n'est pas une preuve d'exactitude métier, seulement une preuve
 * de reproduction fidèle — au même titre que le contrôle de cardinalité d'un
 * import SQLite.
 *
 * Idempotent : rejouer cette commande ne crée aucun doublon de politique, de
 * version ou de règle, et ne réactive pas une version déjà active.
 */
final class BootstrapPolitiquesCommand extends Command
{
    protected $signature = 'core:politiques:bootstrap';

    protected $description = 'Reprend les politiques et règles figées dans bootstrap-politiques-v1.json vers le registre persistant CAP-CORE-007, sans en inventer.';

    private const RESSOURCE = __DIR__ . '/../../../../../core/registre-politiques/resources/bootstrap-politiques-v1.json';

    private const EMPREINTE_SHA256 = 'f64a5eada6c02e303c783b6c69bb34276e5ef900752145ce88db6628d7c51e08';

    private const SOURCE = 'core/registre-politiques/resources/bootstrap-politiques-v1.json — bootstrap CAP-CORE-007';

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
            if (!is_array($payload) || !is_array($payload['politiques'] ?? null)) {
                throw new \RuntimeException('format de ressource invalide');
            }

            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $magasin = PolitiquesMagasin::connecter();
            $registre = new RegistrePolitiques($index, $registreIdentites, $magasin, $ctr01);
        } catch (\Throwable $e) {
            $this->error('Bootstrap interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $politiqueAdmin = PolitiqueAdministration::POLITIQUE;
        $source = self::SOURCE;
        $repris = 0;
        $reglesReprises = 0;

        foreach ($payload['politiques'] as $ligne) {
            $reference = (string) $ligne['reference'];
            $version = (string) $ligne['version'];
            $sourceRef = (string) $ligne['source'];
            if (!empty($ligne['adoption_reference'])) {
                $sourceRef .= ' (' . $ligne['adoption_reference'] . ')';
            }
            $regles = $ligne['regles'];

            if ($registre->resoudrePolitique($reference) === null) {
                $inscription = $registre->inscrirePolitique([
                    'reference' => $reference,
                    'libelle' => (string) $ligne['libelle'],
                    'proprietaire_reference' => $acteur,
                    'source_reference' => $sourceRef,
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-007-{$reference}-INSCRIPTION",
                ]);
                if (isset($inscription['refus'])) {
                    $this->error("{$reference} : inscription refusée — {$inscription['refus']} ({$inscription['detail']})");

                    return self::FAILURE;
                }
                $this->info("{$reference} : inscrite.");
            } else {
                $this->line("{$reference} : déjà inscrite, aucun doublon créé.");
            }

            $existante = $registre->resoudreVersion($reference, $version);
            if ($existante !== null && $existante['etat'] === 'ACTIVE') {
                $this->line("{$reference} {$version} : déjà active, aucun doublon créé.");
                $repris++;
                $reglesReprises += count($regles);

                continue;
            }

            if ($existante === null) {
                $creation = $registre->creerVersion($reference, [
                    'version' => $version,
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-VERSION",
                ]);
                if (isset($creation['refus'])) {
                    $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                    return self::FAILURE;
                }

                $numero = 0;
                foreach ($regles as $r) {
                    $numero++;
                    $ajout = $registre->ajouterRegle($reference, $version, [
                        'effet' => $r['effet'],
                        'action_reference' => $r['action'],
                        'sujet_reference' => $r['sujet_type'],
                        'motif' => $r['motif'],
                        'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                        'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-REGLE-{$numero}",
                    ]);
                    if (isset($ajout['refus'])) {
                        $this->error("{$reference} {$version} : règle refusée — {$ajout['refus']} ({$ajout['detail']})");

                        return self::FAILURE;
                    }
                    $reglesReprises++;
                }

                $soumission = $registre->soumettreVersion($reference, $version, [
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-SOUMISSION",
                ]);
                if (isset($soumission['refus'])) {
                    $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                    return self::FAILURE;
                }

                $cas = [];
                foreach ($regles as $r) {
                    $cas[] = [
                        'sujet' => $r['sujet_type'] ?? $acteur,
                        'action' => $r['action'],
                        'attendu' => $r['effet'] === 'PERMET' ? 'PERMIS' : 'REFUSE',
                    ];
                }
                $simulation = $registre->simulerVersion($reference, $version, [
                    'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}",
                    'cas' => $cas,
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-SIMULATION",
                ]);
                if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                    $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                    return self::FAILURE;
                }
            }

            $activation = $registre->activerVersion($reference, $version, [
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-ACTIVATION",
                'motif' => 'reprise de la politique déjà exploitée avant ce chantier',
            ]);
            if (isset($activation['refus'])) {
                $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

                return self::FAILURE;
            }
            $this->info("{$reference} {$version} : cycle → ACTIVE.");
            $repris++;
        }

        $autoGouvernance = $this->bootstrapAutoGouvernance($registre, $acteur, $politiqueAdmin, $source);
        if ($autoGouvernance === self::FAILURE) {
            return self::FAILURE;
        }
        $repris += $autoGouvernance['politiques'];
        $reglesReprises += $autoGouvernance['regles'];

        $this->newLine();
        $this->info("Bootstrap CAP-CORE-007 terminé. {$repris} politique(s), {$reglesReprises} règle(s) reprise(s). Aucune politique n’a été inventée ; aucun doublon créé.");

        return self::SUCCESS;
    }

    /**
     * `POL-POLITIQUES-V1` n'existait pas avant CAP-CORE-007 : ce n'est pas
     * une reprise de l'existant, mais l'auteur de la politique technique dont
     * ce chantier a lui-même besoin pour que son API et sa console gouvernées
     * (`AccesPolitiques`) ne soient pas bloquées à 403 dès le premier appel —
     * `CTR-03` refuse toute action sans version active qui la permette,
     * y compris pour administrer le registre des politiques lui-même.
     *
     * Huit règles, une par action que `AccesPolitiques` soumet réellement à
     * CTR-03 ; seule l'autorité d'inscription les exerce, comme pour les
     * autres registres techniques (`POL-PRODUITS-V1`, `POL-SOURCES-V1`).
     *
     * @return self::FAILURE|array{politiques:int,regles:int}
     */
    private function bootstrapAutoGouvernance(
        RegistrePolitiques $registre,
        string $acteur,
        string $politiqueAdmin,
        string $source,
    ): array|int {
        $reference = PolitiqueAdministration::POLITIQUE;
        $version = '1.0.0';
        $regles = [
            ['effet' => 'PERMET', 'action' => PolitiqueAdministration::ACTION_INSCRIRE, 'sujet_type' => $acteur,
                'motif' => "Seule l'autorité d'inscription inscrit une politique dans le registre."],
            ['effet' => 'PERMET', 'action' => PolitiqueAdministration::ACTION_VERSION_CREER, 'sujet_type' => $acteur,
                'motif' => "Seule l'autorité d'inscription crée une version de politique, en BROUILLON."],
            ['effet' => 'PERMET', 'action' => PolitiqueAdministration::ACTION_VERSION_MODIFIER, 'sujet_type' => $acteur,
                'motif' => "Seule l'autorité d'inscription ajoute ou modifie une règle d'une version en BROUILLON."],
            ['effet' => 'PERMET', 'action' => PolitiqueAdministration::ACTION_VERSION_SOUMETTRE, 'sujet_type' => $acteur,
                'motif' => "Seule l'autorité d'inscription soumet une version, figeant son contenu."],
            ['effet' => 'PERMET', 'action' => PolitiqueAdministration::ACTION_VERSION_SIMULER, 'sujet_type' => $acteur,
                'motif' => "Seule l'autorité d'inscription simule une version en EN_VALIDATION avant activation."],
            ['effet' => 'PERMET', 'action' => PolitiqueAdministration::ACTION_VERSION_ACTIVER, 'sujet_type' => $acteur,
                'motif' => "Seule l'autorité d'inscription active une version déjà simulée avec succès."],
            ['effet' => 'PERMET', 'action' => PolitiqueAdministration::ACTION_VERSION_SUSPENDRE, 'sujet_type' => $acteur,
                'motif' => "Seule l'autorité d'inscription suspend une version active."],
            ['effet' => 'PERMET', 'action' => PolitiqueAdministration::ACTION_RETIRER, 'sujet_type' => $acteur,
                'motif' => "Seule l'autorité d'inscription retire une politique, irréversiblement."],
        ];

        if ($registre->resoudrePolitique($reference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $reference,
                'libelle' => 'Politique technique d’administration du registre des politiques',
                'proprietaire_reference' => $acteur,
                'source_reference' => 'CAP-CORE-007 — auto-gouvernance du registre des politiques',
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-007-{$reference}-INSCRIPTION",
            ]);
            if (isset($inscription['refus'])) {
                $this->error("{$reference} : inscription refusée — {$inscription['refus']} ({$inscription['detail']})");

                return self::FAILURE;
            }
            $this->info("{$reference} : inscrite.");
        } else {
            $this->line("{$reference} : déjà inscrite, aucun doublon créé.");
        }

        $existante = $registre->resoudreVersion($reference, $version);
        if ($existante !== null && $existante['etat'] === 'ACTIVE') {
            $this->line("{$reference} {$version} : déjà active, aucun doublon créé.");

            return ['politiques' => 1, 'regles' => count($regles)];
        }

        if ($existante === null) {
            $creation = $registre->creerVersion($reference, [
                'version' => $version,
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return self::FAILURE;
            }

            $numero = 0;
            foreach ($regles as $r) {
                $numero++;
                $ajout = $registre->ajouterRegle($reference, $version, [
                    'effet' => $r['effet'], 'action_reference' => $r['action'],
                    'sujet_reference' => $r['sujet_type'], 'motif' => $r['motif'],
                    'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-REGLE-{$numero}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : règle refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return self::FAILURE;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return self::FAILURE;
            }

            $cas = [];
            foreach ($regles as $r) {
                $cas[] = ['sujet' => $r['sujet_type'], 'action' => $r['action'], 'attendu' => 'PERMIS'];
            }
            $simulation = $registre->simulerVersion($reference, $version, [
                'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}",
                'cas' => $cas,
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-SIMULATION",
            ]);
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                return self::FAILURE;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-007-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première écriture gouvernée sur ce registre',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return self::FAILURE;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return ['politiques' => 1, 'regles' => count($regles)];
    }
}
