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
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\PolitiqueRealms;
use Gamad\RegistreRealms\RegistreRealms;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Gamad\RegistreVocabulaire\PolitiqueVocabulaire;
use Gamad\RegistreVocabulaire\RegistreVocabulaire;
use Illuminate\Console\Command;

/**
 * Bootstrap idempotent du registre des realms (CAP-CORE-012).
 *
 * Quatre temps, tous inédits pour ce chantier — contrairement à
 * CAP-CORE-002, rien de ce que cette commande inscrit n'existait avant :
 *
 * 1. `VOC-GAMAD-REALM` (CAP-CORE-010) — les types de realm, les types de
 *    relation, les dimensions de périmètre, les rôles de rattachement et les
 *    motifs canoniques de refus, repris fidèlement de `PolitiqueRealms`
 *    (fiche §13, §41, §46).
 * 2. `POL-REALMS-V1` (CAP-CORE-007) — l'auto-gouvernance technique, sans
 *    laquelle `AccesRealms` resterait bloquée à 403 dès le premier appel
 *    (fiche §28).
 * 3. `CTR-12` (CAP-CORE-009) — le contrat interne décrivant la lecture du
 *    registre (fiche §46).
 * 4. `core/registre-realms/resources/bootstrap-realms-v1.json` — vérifié par
 *    empreinte SHA-256 avant toute écriture. Au moment de ce chantier, aucune
 *    identité de type `realm` n'existe : cette liste est honnêtement vide. La
 *    commande reste exécutable à tout moment ; elle reprendra automatiquement
 *    toute identité `realm` qui apparaîtrait plus tard dans l'index, en
 *    PREPARATION et sans rattachement déduit.
 *
 * Idempotent : rejouer cette commande ne crée aucun doublon.
 */
final class BootstrapRealmsCommand extends Command
{
    protected $signature = 'core:realms:bootstrap';

    protected $description = "Établit VOC-GAMAD-REALM, POL-REALMS-V1, CTR-12 et reprend les identités de type realm connues.";

    private const RESSOURCE = __DIR__ . '/../../../../../core/registre-realms/resources/bootstrap-realms-v1.json';

    private const EMPREINTE_SHA256 = '694455925740925496da015b569867cf519f9039a7c382e1f0632dd43816de9e';

    private const SOURCE = 'core/registre-realms/resources/bootstrap-realms-v1.json — bootstrap CAP-CORE-012';

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
            if (!is_array($payload) || !is_array($payload['realms'] ?? null)) {
                throw new \RuntimeException('format de ressource invalide');
            }

            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);

            $magasinPolitiques = PolitiquesMagasin::connecter();
            $registrePolitiques = new RegistrePolitiques($index, $registreIdentites, $magasinPolitiques, $ctr01);

            $magasinVocabulaire = VocabulaireMagasin::connecter();
            $registreVocabulaire = new RegistreVocabulaire($index, $registreIdentites, $magasinVocabulaire, $ctr01);

            $magasinContrats = ContratsMagasin::connecter();
            $registreContrats = new RegistreContrats($index, $registreIdentites, $magasinContrats, $ctr01);

            $organisations = new RegistreOrganisations($index, $registreIdentites, OrganisationsMagasin::connecter(), $ctr01);
            $produits = new RegistreProduits($index, $registreIdentites, ProduitsMagasin::connecter(), $ctr01);

            $magasinRealms = RealmsMagasin::connecter();
            $registre = new RegistreRealms($index, $registreIdentites, $magasinRealms, $ctr01, $organisations, $produits, $registreContrats);
        } catch (\Throwable $e) {
            $this->error('Bootstrap interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;

        if (!$this->bootstrapVocabulaire($registreVocabulaire, $acteur)) {
            return self::FAILURE;
        }
        if (!$this->bootstrapAutoGouvernance($registrePolitiques, $acteur)) {
            return self::FAILURE;
        }
        if (!$this->bootstrapContrat($registreContrats, $acteur)) {
            return self::FAILURE;
        }

        $repris = 0;
        foreach ($payload['realms'] as $ligne) {
            if (!$this->reprendreRealm($registre, $ligne, $acteur)) {
                return self::FAILURE;
            }
            $repris++;
        }

        $reprisesIdentites = $this->reprendreIdentitesRealm($registre, $ctr01, $acteur);
        if ($reprisesIdentites === null) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Bootstrap CAP-CORE-012 terminé. %d realm(s) repris du catalogue, '
            . '%d identité(s) « realm » de l’index reprise(s) sans fiche. '
            . 'Aucun realm n’a été inventé ; aucun doublon créé.',
            $repris,
            $reprisesIdentites,
        ));

        return self::SUCCESS;
    }

    /**
     * Toute identité de type `realm` déjà reconnue par CAP-CORE-001 mais
     * dépourvue de fiche CAP-CORE-012 reçoit une fiche minimale, `TECHNIQUE`,
     * en PREPARATION (fiche §27). Elle n'est jamais activée automatiquement,
     * ni rattachée à une organisation ou un produit par déduction de nom.
     */
    private function reprendreIdentitesRealm(RegistreRealms $registre, Ctr01 $ctr01, string $acteur): ?int
    {
        $repris = 0;
        foreach ($ctr01->resoudreInventaire('realm') as $identite) {
            $reference = (string) $identite['reference'];
            if ($registre->resoudreRealmParIdentite($reference) !== null) {
                continue;
            }
            $code = 'RLM-REPRIS-' . strtolower(str_replace('IDN-RLM-', '', $reference));
            $inscription = $registre->inscrireRealm([
                'identite_reference' => $reference,
                'code_canonique' => $code,
                'type_realm_reference' => 'TECHNIQUE',
                'source' => 'core/registre-normes/resources/index-baseline-v1.json — bootstrap CAP-CORE-012',
                'nom_affichage' => (string) $identite['libelle'],
                'classification_reference' => 'INTERNE',
                'politique' => PolitiqueRealms::POLITIQUE, 'producteur' => $acteur,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-REPRISE-IDENTITE",
            ]);
            if (isset($inscription['refus'])) {
                $this->error("{$reference} : reprise de fiche refusée — {$inscription['refus']} ({$inscription['detail']})");

                return null;
            }
            $this->info("{$reference} : fiche de realm reprise en PREPARATION ({$inscription['reference']}), type TECHNIQUE, sans rattachement déduit.");
            $repris++;
        }

        return $repris;
    }

    private function reprendreRealm(RegistreRealms $registre, array $ligne, string $acteur): bool
    {
        $identite = (string) $ligne['identite_reference'];
        if ($registre->resoudreRealmParIdentite($identite) !== null) {
            $this->line("{$identite} : déjà repris, aucun doublon créé.");

            return true;
        }

        $inscription = $registre->inscrireRealm([
            'identite_reference' => $identite,
            'code_canonique' => (string) $ligne['code_canonique'],
            'type_realm_reference' => $ligne['type_realm_reference'] ?? 'TECHNIQUE',
            'source' => self::SOURCE,
            'nom_affichage' => (string) $ligne['nom_affichage'],
            'classification_reference' => $ligne['classification_reference'] ?? 'INTERNE',
            'politique' => PolitiqueRealms::POLITIQUE, 'producteur' => $acteur,
            'preuve' => "BOOT-CAP-CORE-012-{$identite}-INSCRIPTION",
        ]);
        if (isset($inscription['refus'])) {
            $this->error("{$identite} : reprise refusée — {$inscription['refus']} ({$inscription['detail']})");

            return false;
        }
        $this->info("{$identite} : fiche de realm reprise ({$inscription['reference']}).");

        return true;
    }

    /**
     * `VOC-GAMAD-REALM` : les listes closes de `PolitiqueRealms`, comme
     * termes réels d'un vocabulaire CAP-CORE-010, jamais comme simples
     * constantes PHP isolées (fiche §13, §41).
     */
    private function bootstrapVocabulaire(RegistreVocabulaire $registre, string $acteur): bool
    {
        $reference = PolitiqueRealms::VOCABULAIRE;
        $version = '1.0.0';
        $source = self::SOURCE;

        if ($registre->resoudreVocabulaire($reference) === null) {
            $inscription = $registre->inscrireVocabulaire([
                'reference' => $reference, 'namespace' => 'gamad.realm', 'nom' => 'Realms — types, relations et motifs',
                'domaine' => 'CAP-CORE-012', 'portee' => 'CORE', 'proprietaire_reference' => $acteur,
                'source_reference' => 'CAP-CORE-012 — auto-gouvernance du registre des realms',
                'politique' => PolitiqueVocabulaire::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-INSCRIPTION",
            ]);
            if (isset($inscription['refus'])) {
                $this->error("{$reference} : inscription refusée — {$inscription['refus']} ({$inscription['detail']})");

                return false;
            }
            $this->info("{$reference} : inscrit.");
        } else {
            $this->line("{$reference} : déjà inscrit, aucun doublon créé.");
        }

        $existante = $registre->resoudreVersionActive($reference);
        if ($existante !== null) {
            $this->line("{$reference} {$version} : déjà actif, aucun doublon créé.");

            return true;
        }
        if ($registre->resoudreVersion($reference, $version) !== null) {
            $this->line("{$reference} {$version} : déjà créé, poursuite du bootstrap.");
        } else {
            $creation = $registre->creerVersion($reference, [
                'version' => $version,
                'politique' => PolitiqueVocabulaire::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }

            $termes = [];
            foreach (PolitiqueRealms::TYPES_REALM as $valeur) {
                $termes[] = ['code' => "TYPE_{$valeur}", 'type_semantique' => 'TYPE', 'definition' => "Type de realm « {$valeur} », fiche CAP-CORE-012 §13."];
            }
            foreach (PolitiqueRealms::TYPES_RELATION as $valeur) {
                $termes[] = ['code' => "RELATION_{$valeur}", 'type_semantique' => 'RELATION', 'definition' => "Type de relation entre realms « {$valeur} », fiche CAP-CORE-012 §16."];
            }
            foreach (PolitiqueRealms::DIMENSIONS_PERIMETRE as $valeur) {
                $termes[] = ['code' => "DIMENSION_{$valeur}", 'type_semantique' => 'CATEGORIE', 'definition' => "Dimension de périmètre « {$valeur} », fiche CAP-CORE-012 §17."];
            }
            foreach (PolitiqueRealms::ROLES_ORGANISATION as $valeur) {
                $termes[] = ['code' => "ROLE_ORGANISATION_{$valeur}", 'type_semantique' => 'ROLE', 'definition' => "Rôle d'organisation rattachée à un realm « {$valeur} », fiche CAP-CORE-012 §19."];
            }
            foreach (PolitiqueRealms::ROLES_PRODUIT as $valeur) {
                $termes[] = ['code' => "ROLE_PRODUIT_{$valeur}", 'type_semantique' => 'ROLE', 'definition' => "Rôle de produit rattaché à un realm « {$valeur} », fiche CAP-CORE-012 §20."];
            }
            foreach (PolitiqueRealms::MOTIFS_REFUS as $valeur) {
                $termes[] = ['code' => "MOTIF_{$valeur}", 'type_semantique' => 'ERREUR', 'definition' => "Motif canonique de refus du contrôle de portée « {$valeur} », fiche CAP-CORE-012 §41."];
            }

            foreach ($termes as $n => $terme) {
                $ajout = $registre->ajouterTerme($reference, $version, [
                    'reference' => sprintf('TRM-%s-%03d', $reference, $n + 1),
                    'code' => $terme['code'], 'type_semantique' => $terme['type_semantique'], 'definition' => $terme['definition'],
                    'politique' => PolitiqueVocabulaire::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-TERME-{$n}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : terme {$terme['code']} refusé — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => PolitiqueVocabulaire::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }
        }

        $analyse = $registre->analyserCompatibilite($reference, $version, [
            'politique' => PolitiqueVocabulaire::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-ANALYSE",
        ]);
        if (isset($analyse['refus'])) {
            $this->error("{$reference} {$version} : analyse refusée — {$analyse['refus']}");

            return false;
        }
        $projection = $registre->genererProjection($reference, $version, [
            'type_projection' => 'JSON',
            'politique' => PolitiqueVocabulaire::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-PROJECTION",
        ]);
        if (isset($projection['refus'])) {
            $this->error("{$reference} {$version} : projection refusée — {$projection['refus']}");

            return false;
        }
        $conformite = $registre->enregistrerConformite($reference, $version, [
            'resultat' => 'CONFORME', 'consommateur_reference' => 'CAP-CORE-012', 'type_consommateur' => 'CAPACITE',
            'politique' => PolitiqueVocabulaire::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-CONFORMITE",
        ]);
        if (isset($conformite['refus'])) {
            $this->error("{$reference} {$version} : conformité refusée — {$conformite['refus']}");

            return false;
        }
        $activation = $registre->activerVersion($reference, $version, [
            'politique' => PolitiqueVocabulaire::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première commande gouvernée sur ce registre',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }

    /**
     * `POL-REALMS-V1` : une action, une règle, pour l'autorité d'inscription
     * seule — comme les autres registres techniques du Core.
     */
    private function bootstrapAutoGouvernance(RegistrePolitiques $registre, string $acteur): bool
    {
        $reference = PolitiqueRealms::POLITIQUE;
        $version = '1.0.0';
        $source = self::SOURCE;
        $actions = [
            PolitiqueRealms::ACTION_LIRE, PolitiqueRealms::ACTION_INSCRIRE, PolitiqueRealms::ACTION_MODIFIER,
            PolitiqueRealms::ACTION_ACTIVER, PolitiqueRealms::ACTION_SUSPENDRE, PolitiqueRealms::ACTION_FERMER,
            PolitiqueRealms::ACTION_RETIRER, PolitiqueRealms::ACTION_RELATION_DECLARER, PolitiqueRealms::ACTION_RELATION_FERMER,
            PolitiqueRealms::ACTION_PERIMETRE_DECLARER, PolitiqueRealms::ACTION_PERIMETRE_FERMER,
            PolitiqueRealms::ACTION_IDENTIFIANT_DECLARER, PolitiqueRealms::ACTION_IDENTIFIANT_FERMER,
            PolitiqueRealms::ACTION_ORGANISATION_RATTACHER, PolitiqueRealms::ACTION_ORGANISATION_DETACHER,
            PolitiqueRealms::ACTION_PRODUIT_RATTACHER, PolitiqueRealms::ACTION_PRODUIT_DETACHER,
            PolitiqueRealms::ACTION_CONTRAT_RATTACHER, PolitiqueRealms::ACTION_CONTRAT_DETACHER,
            PolitiqueRealms::ACTION_FRANCHISSEMENT_DECLARER, PolitiqueRealms::ACTION_FRANCHISSEMENT_FERMER,
            PolitiqueRealms::ACTION_VERIFICATION_ENREGISTRER, PolitiqueRealms::ACTION_PORTEE_VERIFIER,
        ];
        $regles = array_map(static fn (string $action): array => [
            'effet' => 'PERMET', 'action' => $action, 'sujet_type' => $acteur,
            'motif' => "Seule l'autorité d'inscription exerce « {$action} » sur le registre des realms.",
        ], $actions);

        if ($registre->resoudrePolitique($reference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $reference,
                'libelle' => 'Politique technique d’administration du registre des realms',
                'proprietaire_reference' => $acteur,
                'source_reference' => 'CAP-CORE-012 — auto-gouvernance du registre des realms',
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-INSCRIPTION",
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
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-VERSION",
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
                    'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-REGLE-{$numero}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : règle refusée — {$ajout['refus']} ({$ajout['detail']})");

                    return false;
                }
            }

            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }

            $cas = array_map(static fn (array $r): array => ['sujet' => $r['sujet_type'], 'action' => $r['action'], 'attendu' => 'PERMIS'], $regles);
            $simulation = $registre->simulerVersion($reference, $version, [
                'jeu_reference' => "BOOTSTRAP-{$reference}-{$version}", 'cas' => $cas,
                'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-SIMULATION",
            ]);
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                $this->error("{$reference} {$version} : simulation de reprise non réussie — " . json_encode($simulation));

                return false;
            }
        }

        $activation = $registre->activerVersion($reference, $version, [
            'politique' => $reference, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première écriture gouvernée sur ce registre',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }

    /**
     * `CTR-12` : contrat interne CAP-CORE-009 décrivant la lecture du
     * registre des realms (fiche §46).
     */
    private function bootstrapContrat(RegistreContrats $registre, string $acteur): bool
    {
        $reference = PolitiqueRealms::CONTRAT;
        $version = '1.0.0';
        $source = self::SOURCE;

        if ($registre->resoudreContrat($reference) === null) {
            $inscription = $registre->inscrireContrat([
                'reference' => $reference, 'nom' => 'Realms Registry', 'type_contrat' => 'INTERCAPACITE',
                'finalite_reference' => 'RESOLUTION_REALM', 'producteur_capacite_reference' => 'CAP-CORE-012',
                'proprietaire_reference' => $acteur, 'source_reference' => 'CAP-CORE-012 — registre des realms',
                'description' => 'Résolution de realm par référence, identité ou code ; lecture de l’état, de la hiérarchie, des organisations et des produits rattachés ; contrôle de portée explicable ; comportement fermé en panne.',
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-INSCRIPTION",
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
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-VERSION",
            ]);
            if (isset($creation['refus'])) {
                $this->error("{$reference} {$version} : création refusée — {$creation['refus']}");

                return false;
            }
            $partie = $registre->declarerPartie($reference, $version, [
                'role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-012',
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-PARTIE",
            ]);
            if (isset($partie['refus'])) {
                $this->error("{$reference} {$version} : partie refusée — {$partie['refus']}");

                return false;
            }
            $operations = [
                'realms.resoudre', 'realms.resoudre_par_identite', 'realms.resoudre_par_code',
                'realms.resoudre_etat', 'realms.resoudre_hierarchie', 'realms.resoudre_organisations',
                'realms.resoudre_produits', 'realms.verifier_portee',
            ];
            foreach ($operations as $n => $op) {
                $ajout = $registre->declarerOperation($reference, $version, [
                    'reference_operation' => $op, 'type_operation' => 'INTERROGER',
                    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                    'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-OPERATION-{$n}",
                ]);
                if (isset($ajout['refus'])) {
                    $this->error("{$reference} {$version} : opération {$op} refusée — {$ajout['refus']}");

                    return false;
                }
            }
            $soumission = $registre->soumettreVersion($reference, $version, [
                'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-SOUMISSION",
            ]);
            if (isset($soumission['refus'])) {
                $this->error("{$reference} {$version} : soumission refusée — {$soumission['refus']}");

                return false;
            }
        }

        $analyse = $registre->analyserCompatibilite($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-ANALYSE",
        ]);
        if (isset($analyse['refus'])) {
            $this->error("{$reference} {$version} : analyse refusée — {$analyse['refus']}");

            return false;
        }
        $conformite = $registre->enregistrerConformite($reference, $version, [
            'resultat' => 'CONFORME', 'artefact_reference' => 'BOOTSTRAP-CAP-CORE-012',
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-CONFORMITE",
        ]);
        if (isset($conformite['refus'])) {
            $this->error("{$reference} {$version} : conformité refusée — {$conformite['refus']}");

            return false;
        }
        $activation = $registre->activerVersion($reference, $version, [
            'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "BOOT-CAP-CORE-012-{$reference}-{$version}-ACTIVATION",
            'motif' => 'auto-gouvernance requise dès la première lecture externe de ce contrat',
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$reference} {$version} : activation refusée — {$activation['refus']}");

            return false;
        }
        $this->info("{$reference} {$version} : cycle → ACTIVE.");

        return true;
    }
}
