<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\PolitiqueOrganisations;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Illuminate\Console\Command;

/**
 * Bootstrap idempotent du registre des organisations (CAP-CORE-002).
 *
 * Trois temps :
 *
 * 1. `POL-ORGANISATIONS-V1` n'existait pas avant ce chantier : comme
 *    `POL-CONTRATS-V1` pour CAP-CORE-009, ce n'est pas une reprise de
 *    l'existant mais l'auto-gouvernance sans laquelle `AccesOrganisations`
 *    resterait bloquée à 403 dès le premier appel.
 * 2. `core/registre-organisations/resources/bootstrap-organisations-v1.json`
 *    — vérifié par empreinte SHA-256 avant toute écriture (CLAUDE.md §8).
 *    Au moment de ce chantier, aucune entité de type `organisation` n'existe
 *    dans `core/registre-normes/resources/index-baseline-v1.json` : cette
 *    liste est honnêtement vide. La commande reste exécutable à tout moment ;
 *    elle reprendra automatiquement toute identité `organisation` qui
 *    apparaîtrait plus tard dans l'index, sans modification de ce fichier.
 * 3. Migration des relations organisationnelles historiques de CAP-CORE-001
 *    (`relation_organisation`) vers `organisation_affiliation` (fiche
 *    CAP-CORE-002 §13.2). L'ancien indicateur `mandat_verifie` n'est JAMAIS
 *    recopié comme vérité : il est conservé uniquement en diagnostic dans le
 *    journal de sortie de cette commande, jamais dans le registre.
 *
 * Idempotent : rejouer cette commande ne crée aucun doublon.
 */
final class BootstrapOrganisationsCommand extends Command
{
    protected $signature = 'core:organisations:bootstrap';

    protected $description = "Établit POL-ORGANISATIONS-V1, reprend les identités d'organisation connues et migre les relations organisationnelles historiques de CAP-CORE-001 vers CAP-CORE-002.";

    private const RESSOURCE = __DIR__ . '/../../../../../core/registre-organisations/resources/bootstrap-organisations-v1.json';

    private const EMPREINTE_SHA256 = '70342c82628e32b474b65674809aca346412d996d867d570155e36b5fb50330e';

    private const SOURCE = 'core/registre-organisations/resources/bootstrap-organisations-v1.json — bootstrap CAP-CORE-002';

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
            if (!is_array($payload) || !is_array($payload['organisations'] ?? null)) {
                throw new \RuntimeException('format de ressource invalide');
            }

            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $magasinPolitiques = PolitiquesMagasin::connecter();
            $registrePolitiques = new RegistrePolitiques($index, $registreIdentites, $magasinPolitiques, $ctr01);
            $magasinOrganisations = OrganisationsMagasin::connecter();
            $registre = new RegistreOrganisations($index, $registreIdentites, $magasinOrganisations, $ctr01);
        } catch (\Throwable $e) {
            $this->error('Bootstrap interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;

        if (!$this->bootstrapAutoGouvernance($registrePolitiques, $acteur)) {
            return self::FAILURE;
        }

        $repris = 0;
        foreach ($payload['organisations'] as $ligne) {
            if (!$this->reprendreOrganisation($registre, $ligne, $acteur)) {
                return self::FAILURE;
            }
            $repris++;
        }

        $reprisesIdentites = $this->reprendreIdentitesOrganisation($registre, $ctr01, $acteur);
        if ($reprisesIdentites === null) {
            return self::FAILURE;
        }

        $migration = $this->migrerRelationsOrganisation($registre, $registreIdentites, $ctr01, $acteur);
        if ($migration === null) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Bootstrap CAP-CORE-002 terminé. %d organisation(s) reprise(s) du catalogue, '
            . '%d identité(s) « organisation » de l’index reprise(s) sans fiche, '
            . '%d relation(s) historique(s) migrée(s) vers organisation_affiliation. '
            . 'Aucune organisation n’a été inventée ; aucun doublon créé.',
            $repris,
            $reprisesIdentites,
            $migration,
        ));

        return self::SUCCESS;
    }

    /**
     * Toute identité de type `organisation` déjà reconnue par CAP-CORE-001
     * mais dépourvue de fiche CAP-CORE-002 reçoit une fiche minimale,
     * `INDETERMINE`, non vérifiée, en PREPARATION (fiche §13.1). Elle n'est
     * jamais activée automatiquement : l'activation reste une décision
     * distincte.
     */
    private function reprendreIdentitesOrganisation(RegistreOrganisations $registre, Ctr01 $ctr01, string $acteur): ?int
    {
        $repris = 0;
        foreach ($ctr01->resoudreInventaire('organisation') as $identite) {
            $reference = (string) $identite['reference'];
            if ($registre->resoudreOrganisationParIdentite($reference) !== null) {
                continue;
            }
            $inscription = $registre->inscrireOrganisation([
                'identite_reference' => $reference,
                'type_organisation_reference' => 'INDETERMINE',
                'personnalite_juridique' => false,
                'proprietaire_reference' => $acteur,
                'source' => 'core/registre-normes/resources/index-baseline-v1.json — bootstrap CAP-CORE-002',
                'denomination_officielle' => (string) $identite['libelle'],
                'classification_reference' => 'INTERNE',
                'politique' => PolitiqueOrganisations::POLITIQUE, 'producteur' => $acteur,
                'preuve' => "BOOT-CAP-CORE-002-{$reference}-REPRISE-IDENTITE",
            ]);
            if (isset($inscription['refus'])) {
                $this->error("{$reference} : reprise de fiche refusée — {$inscription['refus']} ({$inscription['detail']})");

                return null;
            }
            $this->info("{$reference} : fiche organisationnelle reprise en PREPARATION ({$inscription['reference']}), type INDETERMINE, non vérifiée.");
            $repris++;
        }

        return $repris;
    }

    /**
     * Migre chaque ligne de `relation_organisation` (CAP-CORE-001) vers
     * `organisation_affiliation` (CAP-CORE-002). L'ancienne référence de
     * relation est conservée dans la preuve, pour traçabilité. L'ancien
     * indicateur `mandat_verifie` est journalisé dans la sortie de la
     * commande à titre de diagnostic de migration — jamais recopié comme
     * vérité du nouveau registre (fiche §13.2, §32).
     */
    private function migrerRelationsOrganisation(
        RegistreOrganisations $registre,
        \PDO $registreIdentites,
        Ctr01 $ctr01,
        string $acteur,
    ): ?int {
        try {
            $lignes = $registreIdentites->query('SELECT * FROM relation_organisation ORDER BY date_debut, reference')->fetchAll();
        } catch (\Throwable $e) {
            $this->error('Lecture de relation_organisation impossible : ' . $e->getMessage());

            return null;
        }
        if ($lignes === []) {
            $this->line('relation_organisation : aucune ligne historique à migrer.');

            return 0;
        }

        $migrees = 0;
        foreach ($lignes as $r) {
            $ancienneReference = (string) $r['reference'];
            $identitePersonne = (string) $r['identite_reference'];
            $identiteOrganisation = (string) $r['organisation_reference'];

            $ficheOrganisation = $registre->resoudreOrganisationParIdentite($identiteOrganisation);
            if ($ficheOrganisation === null) {
                $identiteResolue = $ctr01->resoudreIdentite($identiteOrganisation);
                if ($identiteResolue === null || ($identiteResolue['type'] ?? null) !== 'organisation') {
                    $this->warn("{$ancienneReference} : organisation `{$identiteOrganisation}` non reconnue, ligne ignorée (rien à migrer).");

                    continue;
                }
                $inscription = $registre->inscrireOrganisation([
                    'identite_reference' => $identiteOrganisation, 'type_organisation_reference' => 'INDETERMINE',
                    'personnalite_juridique' => false, 'proprietaire_reference' => $acteur,
                    'source' => 'core/registre-identites — migration relation_organisation (CAP-CORE-001 → CAP-CORE-002)',
                    'denomination_officielle' => (string) $identiteResolue['libelle'], 'classification_reference' => 'INTERNE',
                    'politique' => PolitiqueOrganisations::POLITIQUE, 'producteur' => $acteur,
                    'preuve' => "BOOT-CAP-CORE-002-MIGRATION-{$ancienneReference}-FICHE",
                ]);
                if (isset($inscription['refus'])) {
                    $this->error("{$ancienneReference} : création de fiche pour migration refusée — {$inscription['refus']}");

                    return null;
                }
                $registre->activerOrganisation($inscription['reference'], [
                    'politique' => PolitiqueOrganisations::POLITIQUE, 'producteur' => $acteur,
                    'source' => 'migration relation_organisation', 'preuve' => "BOOT-CAP-CORE-002-MIGRATION-{$ancienneReference}-ACTIVATION",
                    'motif' => 'organisation déjà porteuse de relations actives dans CAP-CORE-001 avant migration',
                ]);
                $ficheOrganisation = $registre->resoudreOrganisation($inscription['reference']);
            }
            $organisationRef = (string) $ficheOrganisation['reference'];

            $dejaMigree = $registre->resoudreAffiliationsIdentite($identitePersonne, ['type' => (string) $r['relation_type']]);
            $existeDeja = array_filter($dejaMigree, static fn (array $a): bool => $a['organisation_reference'] === $organisationRef);
            if ($existeDeja !== []) {
                $this->line("{$ancienneReference} : déjà migrée vers " . array_values($existeDeja)[0]['reference'] . ', aucun doublon créé.');

                continue;
            }

            $proposition = $registre->proposerAffiliation([
                'organisation_reference' => $organisationRef, 'identite_reference' => $identitePersonne,
                'type_affiliation_reference' => (string) $r['relation_type'],
                'niveau_assurance_reference' => (string) $r['niveau_assurance'],
                'classification_reference' => (string) $r['classification'],
                'date_debut' => (string) $r['date_debut'],
                'source' => (string) $r['source'] . " (migration {$ancienneReference})",
                'preuve' => "BOOT-CAP-CORE-002-MIGRATION-{$ancienneReference}-AFFILIATION",
                'producteur_reference' => (string) $r['producteur'],
                'politique' => PolitiqueOrganisations::POLITIQUE, 'producteur' => $acteur,
            ]);
            if (isset($proposition['refus'])) {
                $this->error("{$ancienneReference} : proposition d’affiliation refusée — {$proposition['refus']} ({$proposition['detail']})");

                return null;
            }
            $activation = $registre->activerAffiliation($proposition['reference'], [
                'politique' => PolitiqueOrganisations::POLITIQUE, 'producteur' => $acteur,
                'source' => 'migration relation_organisation', 'preuve' => "BOOT-CAP-CORE-002-MIGRATION-{$ancienneReference}-ACTIVATION-AFFILIATION",
                'motif' => 'reprise fidèle d’une relation active dans CAP-CORE-001',
            ]);
            if (isset($activation['refus'])) {
                $this->warn("{$ancienneReference} : affiliation migrée en PROPOSEE seulement — {$activation['refus']} ({$activation['detail']})");
            }

            $this->info(sprintf(
                '%s : migrée vers %s (%s). Ancien indicateur mandat_verifie=%s conservé ici à titre de diagnostic '
                . 'uniquement — non recopié comme vérité ; toute représentation opposable doit être reconfirmée via CAP-CORE-003.',
                $ancienneReference,
                $proposition['reference'],
                $r['relation_type'],
                ((int) $r['mandat_verifie']) === 1 ? 'oui' : 'non',
            ));
            $migrees++;
        }

        return $migrees;
    }

    private function reprendreOrganisation(RegistreOrganisations $registre, array $ligne, string $acteur): bool
    {
        $identite = (string) $ligne['identite_reference'];
        $politiqueAdmin = PolitiqueOrganisations::POLITIQUE;
        $source = self::SOURCE;

        if ($registre->resoudreOrganisationParIdentite($identite) !== null) {
            $this->line("{$identite} : déjà repris, aucun doublon créé.");

            return true;
        }

        $inscription = $registre->inscrireOrganisation([
            'identite_reference' => $identite,
            'type_organisation_reference' => $ligne['type_organisation_reference'] ?? 'INDETERMINE',
            'personnalite_juridique' => (bool) ($ligne['personnalite_juridique'] ?? false),
            'proprietaire_reference' => $acteur,
            'source' => $source,
            'denomination_officielle' => (string) $ligne['denomination_officielle'],
            'classification_reference' => $ligne['classification_reference'] ?? 'INTERNE',
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-002-{$identite}-INSCRIPTION",
        ]);
        if (isset($inscription['refus'])) {
            $this->error("{$identite} : reprise refusée — {$inscription['refus']} ({$inscription['detail']})");

            return false;
        }
        $this->info("{$identite} : fiche organisationnelle reprise ({$inscription['reference']}).");

        return true;
    }

    /**
     * `POL-ORGANISATIONS-V1` : une action, une règle, pour l'autorité
     * d'inscription seule — comme les autres registres techniques du Core.
     */
    private function bootstrapAutoGouvernance(RegistrePolitiques $registre, string $acteur): bool
    {
        $reference = PolitiqueOrganisations::POLITIQUE;
        $version = '1.0.0';
        $politiqueAdmin = $reference;
        $source = self::SOURCE;
        $actions = [
            PolitiqueOrganisations::ACTION_LIRE,
            PolitiqueOrganisations::ACTION_INSCRIRE,
            PolitiqueOrganisations::ACTION_MODIFIER,
            PolitiqueOrganisations::ACTION_ACTIVER,
            PolitiqueOrganisations::ACTION_SUSPENDRE,
            PolitiqueOrganisations::ACTION_DISSOUDRE,
            PolitiqueOrganisations::ACTION_RETIRER,
            PolitiqueOrganisations::ACTION_IDENTIFIANT_DECLARER,
            PolitiqueOrganisations::ACTION_IDENTIFIANT_FERMER,
            PolitiqueOrganisations::ACTION_UNITE_CREER,
            PolitiqueOrganisations::ACTION_UNITE_MODIFIER,
            PolitiqueOrganisations::ACTION_UNITE_FERMER,
            PolitiqueOrganisations::ACTION_RELATION_DECLARER,
            PolitiqueOrganisations::ACTION_RELATION_FERMER,
            PolitiqueOrganisations::ACTION_AFFILIATION_PROPOSER,
            PolitiqueOrganisations::ACTION_AFFILIATION_ACTIVER,
            PolitiqueOrganisations::ACTION_AFFILIATION_SUSPENDRE,
            PolitiqueOrganisations::ACTION_AFFILIATION_FERMER,
            PolitiqueOrganisations::ACTION_FONCTION_CREER,
            PolitiqueOrganisations::ACTION_REPRESENTATION_VERIFIER,
        ];
        $regles = array_map(static fn (string $action): array => [
            'effet' => 'PERMET', 'action' => $action, 'sujet_type' => $acteur,
            'motif' => "Seule l'autorité d'inscription exerce « {$action} » sur le registre des organisations.",
        ], $actions);

        if ($registre->resoudrePolitique($reference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $reference,
                'libelle' => 'Politique technique d’administration du registre des organisations',
                'proprietaire_reference' => $acteur,
                'source_reference' => 'CAP-CORE-002 — auto-gouvernance du registre des organisations',
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-002-{$reference}-INSCRIPTION",
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
                'preuve' => "BOOT-CAP-CORE-002-{$reference}-{$version}-VERSION",
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
                    'preuve' => "BOOT-CAP-CORE-002-{$reference}-{$version}-REGLE-{$numero}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : règle refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-002-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }

            $cas = array_map(static fn (array $r): array => ['sujet' => $r['sujet_type'], 'action' => $r['action'], 'attendu' => 'PERMIS'], $regles);
            $simulation = $registre->simulerVersion($reference, $version, [
                'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}", 'cas' => $cas,
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-002-{$reference}-{$version}-SIMULATION",
            ]);
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-002-{$reference}-{$version}-ACTIVATION",
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
