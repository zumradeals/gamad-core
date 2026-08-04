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
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;
use Illuminate\Console\Command;

/**
 * Bootstrap idempotent de CAP-CORE-016.
 *
 * Trois temps : `POL-SECRETS-CLES-V1` (sans laquelle toute commande gouvernée
 * reste bloquée en refus par défaut, comme pour CAP-CORE-012/014), neuf
 * contrats techniques `CTR-GAMAD-SECRETS-*`, puis l'inventaire réel de
 * `bootstrap-secrets-cles-v1.json` — aucune valeur, aucune activation
 * automatique de version (fiche partie 5 §2, §18).
 *
 * Idempotent : rejouer cette commande ne crée aucun doublon.
 */
final class BootstrapSecretsCommand extends Command
{
    protected $signature = 'core:secrets:bootstrap';

    protected $description = "Établit POL-SECRETS-CLES-V1, les contrats techniques et l'inventaire réel de bootstrap-secrets-cles-v1.json — aucune valeur.";

    private const RESSOURCE = __DIR__ . '/../../../../../core/registre-secrets-cles/resources/bootstrap-secrets-cles-v1.json';

    private const EMPREINTE_SHA256 = '67f30930454938d755f7fa1f995a48ed9633bb41eb7ac535e0bb89e1b6689a24';

    private const SOURCE = 'CAP-CORE-016 — bootstrap du registre des secrets et clés';

    private const ACTIONS = [
        PolitiqueSecretsCles::ACTION_LIRE_METADONNEES,
        PolitiqueSecretsCles::ACTION_INSCRIRE,
        PolitiqueSecretsCles::ACTION_FOURNISSEUR_INSCRIRE,
        PolitiqueSecretsCles::ACTION_FOURNISSEUR_VERIFIER,
        PolitiqueSecretsCles::ACTION_VERSION_DECLARER,
        PolitiqueSecretsCles::ACTION_VERSION_VERIFIER,
        PolitiqueSecretsCles::ACTION_VERSION_ACTIVER,
        PolitiqueSecretsCles::ACTION_USAGE_DECLARER,
        PolitiqueSecretsCles::ACTION_ROTATION_PLANIFIER,
        PolitiqueSecretsCles::ACTION_ROTATION_VALIDER,
        PolitiqueSecretsCles::ACTION_ROTATION_EXECUTER,
        PolitiqueSecretsCles::ACTION_VERSION_SUSPENDRE,
        PolitiqueSecretsCles::ACTION_VERSION_REVOQUER,
        PolitiqueSecretsCles::ACTION_VERSION_COMPROMETTRE,
        PolitiqueSecretsCles::ACTION_VERSION_DETRUIRE,
        PolitiqueSecretsCles::ACTION_MATERIEL_PUBLIC_EXPORTER,
        PolitiqueSecretsCles::ACTION_DIAGNOSTIC_LIRE,
    ];

    /** @var list<string> */
    private const OPERATIONS_CONTRAT = [
        'metadonnees.lire', 'version.declarer', 'version.activer', 'usage.declarer',
        'rotation.planifier', 'rotation.executer', 'compromission.declarer',
        'materiel-public.lire', 'resolution.interne',
    ];

    public function handle(): int
    {
        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $registrePolitiques = new RegistrePolitiques($index, $registreIdentites, PolitiquesMagasin::connecter(), $ctr01);
            $registreContrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);
            $registreSecrets = new RegistreSecretsCles(SecretsMagasin::connecter());
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
        if (!$this->bootstrapInventaire($registreSecrets, $acteur)) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Bootstrap CAP-CORE-016 terminé : POL-SECRETS-CLES-V1, neuf contrats techniques et inventaire réel établis.');
        $this->line('Aucune version activée par ce bootstrap : vérifier puis activer explicitement (core:secrets:fournisseurs-verifier).');

        return self::SUCCESS;
    }

    private function bootstrapAutoGouvernance(RegistrePolitiques $registre, string $acteur): bool
    {
        $reference = PolitiqueSecretsCles::POLITIQUE;
        $version = '1.0.0';
        $source = self::SOURCE;

        if ($registre->resoudrePolitique($reference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $reference,
                'libelle' => 'Politique technique d’administration du registre des secrets et clés',
                'proprietaire_reference' => $acteur,
                'source_reference' => self::SOURCE,
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-016-{$reference}-INSCRIPTION",
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
                'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-VERSION",
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
                    'motif' => "Seule l'autorité d'inscription exerce « {$action} » sur le registre des secrets et clés.",
                    'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-REGLE-{$numero}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : règle {$action} refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }

            $cas = array_map(static fn (string $action): array => ['sujet' => $acteur, 'action' => $action, 'attendu' => 'PERMIS'], self::ACTIONS);
            $simulation = $registre->simulerVersion($reference, $version, [
                'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}", 'cas' => $cas,
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-SIMULATION",
            ]);
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-ACTIVATION",
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
            $reference = 'CTR-GAMAD-SECRETS-' . strtoupper(str_replace(['.', '-'], '_', $operation));
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
                'reference' => $reference, 'nom' => "Registre des secrets et clés — {$operation}", 'type_contrat' => 'COMMANDE',
                'finalite_reference' => 'EXPLOITATION_SECRETS_CLES', 'producteur_capacite_reference' => PolitiqueSecretsCles::CAPACITE,
                'proprietaire_reference' => $acteur, 'source_reference' => self::SOURCE,
                'description' => "Opération technique interne {$operation} de CAP-CORE-016.",
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-016-{$reference}-INSCRIPTION",
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
                'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }
            $partie = $registre->declarerPartie($reference, $version, [
                'role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => PolitiqueSecretsCles::CAPACITE,
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-PARTIE",
            ]);
            if (isset($partie['refus'])) {
                $this->error("{$reference} {$version} : partie refusée — {$partie['refus']}");

                return false;
            }
            $ajoutOperation = $registre->declarerOperation($reference, $version, [
                'reference_operation' => $operation, 'type_operation' => 'COMMANDER',
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-OPERATION-{$rang}",
            ]);
            if (isset($ajoutOperation['refus'])) {
                $this->error("{$reference} {$version} : opération {$operation} refusée — {$ajoutOperation['refus']}");

                return false;
            }
            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }
        }

        $analyse = $registre->analyserCompatibilite($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-ANALYSE",
        ]);
        if (isset($analyse['refus'])) {
            $this->error("{$reference} {$version} : analyse refusée — {$analyse['refus']}");

            return false;
        }
        $conformite = $registre->enregistrerConformite($reference, $version, [
            'resultat' => 'CONFORME', 'artefact_reference' => 'BOOTSTRAP-CAP-CORE-016',
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-CONFORMITE",
        ]);
        if (isset($conformite['refus'])) {
            $this->error("{$reference} {$version} : conformité refusée — {$conformite['refus']}");

            return false;
        }
        $activation = $registre->activerVersion($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-016-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première commande technique sur ce registre',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }

    private function bootstrapInventaire(RegistreSecretsCles $registre, string $acteur): bool
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
            if (!is_array($payload) || !is_array($payload['ressources'] ?? null)) {
                throw new \RuntimeException('format de ressource invalide');
            }
        } catch (\Throwable $e) {
            $this->error('Inventaire interrompu : ' . $e->getMessage());

            return false;
        }

        $g = fn (): array => [
            'politique' => PolitiqueSecretsCles::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'BOOTSTRAP-SECRETS-' . strtoupper(bin2hex(random_bytes(6))),
        ];

        $ressourcesInscrites = 0;
        $fournisseursInscrits = 0;
        $versionsDeclarees = 0;
        $usagesDeclares = 0;
        $nonConfigurees = 0;

        foreach ($payload['ressources'] as $entree) {
            $reference = (string) $entree['reference'];
            if ($registre->resoudreSecret($reference) === null) {
                $resultat = $registre->inscrireSecret(array_merge($g(), $entree));
                if (isset($resultat['refus'])) {
                    $this->error("{$reference} : refus à l'inscription — {$resultat['refus']} ({$resultat['detail']})");

                    return false;
                }
                $ressourcesInscrites++;
                $this->info("{$reference} : inscrite.");
            } else {
                $this->line("{$reference} : déjà inscrite, aucun doublon créé.");
            }

            if (!empty($entree['non_configure'])) {
                $nonConfigurees++;
                $this->line("{$reference} : non configurée en production ({$entree['motif_non_configure']}) — aucun fournisseur ni version fictifs.");
                continue;
            }

            $fournisseurEntree = $entree['fournisseur'] ?? null;
            if (!is_array($fournisseurEntree)) {
                continue;
            }
            $fournisseurReference = 'FOU-GAMAD-' . preg_replace('/[^A-Z0-9]+/', '-', strtoupper($reference));
            if ($registre->resoudreFournisseur($fournisseurReference) === null) {
                $resultat = $registre->inscrireFournisseur(array_merge($g(), [
                    'reference' => $fournisseurReference,
                    'nom' => (string) $fournisseurEntree['nom'],
                    'type_fournisseur' => (string) $fournisseurEntree['type_fournisseur'],
                    'environnement_reference' => (string) $entree['environnement_reference'],
                    'proprietaire_reference' => (string) $entree['proprietaire_reference'],
                    'capacites' => ['LIRE'],
                ]));
                if (isset($resultat['refus'])) {
                    $this->error("{$fournisseurReference} : refus — {$resultat['refus']} ({$resultat['detail']})");

                    return false;
                }
                $fournisseursInscrits++;
                $this->info("{$fournisseurReference} : inscrit (PREPARATION).");
            } else {
                $this->line("{$fournisseurReference} : déjà inscrit, aucun doublon créé.");
            }

            if ($registre->resoudreVersion($reference, '1') === null) {
                $resultat = $registre->declarerVersion(array_merge($g(), [
                    'secret_reference' => $reference, 'version' => '1',
                    'fournisseur_reference' => $fournisseurReference,
                    'handle_fournisseur' => (string) $fournisseurEntree['handle'],
                ]));
                if (isset($resultat['refus'])) {
                    $this->error("{$reference} v1 : refus — {$resultat['refus']} ({$resultat['detail']})");

                    return false;
                }
                $versionsDeclarees++;
                $this->info("{$reference} v1 : déclarée (PREPARATION, non activée).");
            } else {
                $this->line("{$reference} v1 : déjà déclarée, aucun doublon créé.");
            }

            foreach ((array) ($entree['usages'] ?? []) as $usageEntree) {
                $existant = $registre->listerUsages($reference);
                $dejaDeclare = false;
                foreach ($existant as $ligneUsage) {
                    if ($ligneUsage['operation_reference'] === $usageEntree['operation_reference']
                        && $ligneUsage['mode_usage'] === $usageEntree['mode_usage']) {
                        $dejaDeclare = true;
                        break;
                    }
                }
                if ($dejaDeclare) {
                    continue;
                }
                $resultat = $registre->declarerUsage(array_merge($g(), [
                    'secret_reference' => $reference,
                    'capacite_reference' => 'CAP-CORE-016',
                    'environnement_reference' => (string) $entree['environnement_reference'],
                    'operation_reference' => (string) $usageEntree['operation_reference'],
                    'finalite_reference' => (string) $usageEntree['finalite_reference'],
                    'mode_usage' => (string) $usageEntree['mode_usage'],
                ]));
                if (isset($resultat['refus'])) {
                    $this->error("{$reference} usage {$usageEntree['operation_reference']} : refus — {$resultat['refus']} ({$resultat['detail']})");

                    return false;
                }
                $usagesDeclares++;
            }
        }

        $this->info(sprintf(
            'Inventaire CAP-CORE-016 : %d ressource(s) nouvelle(s), %d fournisseur(s), %d version(s), %d usage(s), %d référence(s) non configurée(s).',
            $ressourcesInscrites, $fournisseursInscrits, $versionsDeclarees, $usagesDeclares, $nonConfigurees,
        ));

        return true;
    }
}
