<?php

declare(strict_types=1);

namespace App\Application\Matching;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\MoteurMatching\Magasin as MatchingMagasin;
use Gamad\MoteurMatching\PolitiqueMatching;
use Gamad\MoteurMatching\RegistreMatching;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\RegistreSources;

/**
 * Cas d'usage HTTP de CAP-CORE-021 (doc de chantier 04). Même chemin gouverné
 * que `AccesPreuves` (CAP-CORE-015) et `AccesSecrets` (CAP-CORE-016) :
 * `CAP-CORE-004` (`Ctr03`) décide, `CAP-CORE-013` conserve la preuve
 * d'exploitation, seule une décision permise et prouvée atteint l'écriture
 * gouvernée dans `RegistreMatching`.
 *
 * Périmètre exposé volontairement restreint à ce qui a un code d'action
 * exact dans `PolitiqueMatching::ACTIONS` (doc 04 §1). Le vocabulaire
 * d'autorisation actuel ne porte pas d'action dédiée pour lire, accuser
 * réception, suspendre ou révoquer une activation isolément (seule
 * `activer-segment-matching` couvre sa création, et `terminer-activation-
 * matching` sa fin) ni pour lire, instruire, corriger ou clore une
 * contestation au-delà de son ouverture et de son réexamen — les routes
 * correspondantes de la fiche (doc 04 §7-8) ne sont donc pas câblées ici.
 * Étendre `PolitiqueMatching::ACTIONS` avant d'exposer ces routes est un
 * choix délibéré, pas un oubli : inventer une permission qui n'existe pas
 * dans le vocabulaire fermé est interdit par CLAUDE.md §3.
 *
 * L'inscription et l'activation d'un contexte, ainsi que l'activation d'un
 * profil compilé, restent des opérations d'exploitation (CLI), pas des
 * routes HTTP — la fiche ne leur donne d'ailleurs aucune route dédiée.
 *
 * Câblage réel des dépendances Core (voir chaque méthode d'écriture pour le
 * détail) : CAP-CORE-011 (produits), CAP-CORE-009 (contrats) et CAP-CORE-006
 * (sources) sont interrogés pour de vrai — jamais acceptés depuis le corps
 * d'une requête cliente. CAP-CORE-017 (risques), CAP-CORE-018 (incidents) et
 * CAP-CORE-008 (décisions formelles) ne peuvent pas l'être : ces capacités
 * n'existent pas encore comme code dans ce dépôt (aucun `core/registre-*`
 * correspondant). Les faits qui en dépendraient sont figés à leur valeur la
 * moins permissive côté blocage (`false`) plutôt que devinés ou acceptés du
 * client — mais ce n'est pas une vraie vérification, et le rapport
 * d'admission doit le dire explicitement.
 */
final class AccesMatching
{
    // ------------------------------------------------------------------
    // Lectures

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerContextes(string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueMatching::ACTION_CONTEXTES_CONSULTER, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['contextes' => $this->registre()->listerContextes()]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreContexte(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueMatching::ACTION_CONTEXTES_CONSULTER, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $contexte = $this->registre()->resoudreContexte($reference);
        if ($contexte === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'MATCHING_CONTEXT_UNKNOWN']];
        }

        return ['statut' => 200, 'corps' => ['contexte' => $contexte]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreProfil(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueMatching::ACTION_POLITIQUES_CONSULTER, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $profil = $this->registre()->resoudreProfil($reference);
        if ($profil === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'MATCHING_POLICY_UNKNOWN']];
        }

        return ['statut' => 200, 'corps' => ['profil' => $profil]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerDemandes(array $filtres, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueMatching::ACTION_DEMANDE_CONSULTER, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['demandes' => $this->registre()->listerDemandes($filtres)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function historiqueDemande(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueMatching::ACTION_DEMANDE_CONSULTER, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['historique' => $this->registre()->historiqueDemande($reference)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreDemande(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueMatching::ACTION_DEMANDE_CONSULTER, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $demande = $this->registre()->resoudreDemande($reference);
        if ($demande === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'DEMANDE_INCONNUE']];
        }

        return ['statut' => 200, 'corps' => ['demande' => $demande]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreResultat(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueMatching::ACTION_RESULTAT_CONSULTER, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $resultat = $this->registre()->resoudreResultat($reference);
        if ($resultat === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'RESULTAT_INCONNU']];
        }

        return ['statut' => 200, 'corps' => ['resultat' => $resultat]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function expliquerResultat(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueMatching::ACTION_RESULTAT_EXPLIQUER, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $projection = $this->registre()->expliquerResultat($reference);
        if (isset($projection['refus'])) {
            return ['statut' => $projection['refus'] === 'MATCHING_RESULT_EXPIRED' ? 410 : 404, 'corps' => ['erreur' => $projection['refus'], 'detail' => $projection['detail']]];
        }

        return ['statut' => 200, 'corps' => ['explication' => $projection]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreSegment(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueMatching::ACTION_SEGMENT_CONSULTER, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $segment = $this->registre()->resoudreSegment($reference);
        if ($segment === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'SEGMENT_INCONNU']];
        }

        return ['statut' => 200, 'corps' => ['segment' => $this->masquerMembres($segment)]];
    }

    // ------------------------------------------------------------------
    // Écritures gouvernées

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function compilerProfil(array $specification, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_POLITIQUE_COMPILER, null, $acteur, $correlation, 'PROFIL_COMPILE',
            fn (): array => $this->registre()->compilerProfil($specification, $acteur),
            201,
            $this->codesRefusStandard(),
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function soumettreDemande(array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_DEMANDE_SOUMETTRE, null, $acteur, $correlation, 'DEMANDE_SOUMISE',
            fn (): array => $this->registre()->soumettreDemande($donnees, $acteur),
            201,
            $this->codesRefusStandard(),
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function annulerDemande(string $reference, string $motif, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_DEMANDE_ANNULER, $reference, $acteur, $correlation, 'DEMANDE_ANNULEE',
            fn (): array => $this->registre()->annulerDemande($reference, $motif, $acteur),
            200,
            $this->codesRefusStandard(),
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function executer(string $reference, array $faits, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_MATCHING_EXECUTER, $reference, $acteur, $correlation, 'MATCHING_EXECUTE',
            fn (): array => $this->registre()->executer($reference, $faits, $acteur),
            200,
            $this->codesRefusStandard(),
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function construireSegment(string $demandeReference, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_SEGMENT_CONSTRUIRE, $demandeReference, $acteur, $correlation, 'SEGMENT_CONSTRUIT',
            fn (): array => $this->registre()->construireSegment($demandeReference, $donnees, $acteur),
            201,
            $this->codesRefusStandard() + ['MATCHING_POPULATION_TOO_SMALL' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function verifierAppartenanceSegment(string $reference, string $token, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_SEGMENT_APPARTENANCE_VERIFIER, $reference, $acteur, $correlation, 'APPARTENANCE_VERIFIEE',
            fn (): array => $this->registre()->verifierAppartenanceSegment($reference, $token),
            200,
            $this->codesRefusStandard(),
        );
    }

    /**
     * Les faits d'`Activation::verifier` ne sont jamais acceptés depuis le
     * corps de la requête (voir l'ancienne signature avec `$faits` client,
     * corrigée ici) : un appelant aurait pu s'auto-autoriser en envoyant
     * `{"faits":{"autorisation_decision":"PERMIS", ...}}`. Tous les faits
     * de sécurité sont recalculés côté serveur :
     *
     * - `autorisation_decision` : garanti `PERMIS` à ce point — `gouverner()`
     *   a déjà refusé l'appel sinon (CTR-03, CAP-CORE-004 réel) ;
     * - `produit_actif`, `contrat_actif` : requêtes réelles à CAP-CORE-011
     *   et CAP-CORE-009 ;
     * - `politique_active` : le profil actif du contexte du segment existe
     *   réellement dans le magasin du Matching ;
     * - `risque_bloquant`, `incident_bloquant`, `decision_formelle_requise` :
     *   figés à `false` — **réserve non contournable** : CAP-CORE-017,
     *   CAP-CORE-018 et CAP-CORE-008 n'existent pas encore comme code dans
     *   ce dépôt (aucun `core/registre-*` correspondant), donc aucune vraie
     *   vérification n'est possible aujourd'hui. Ce n'est pas un oubli
     *   silencieux : tant que ces capacités ne sont pas codées, aucune
     *   activation n'est bloquée pour ce motif, et le rapport d'admission
     *   doit le dire.
     * - `obligations_acceptees` : seul fait accepté du corps de la requête,
     *   parce qu'il n'autorise rien — c'est l'accusé du consommateur.
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function demanderActivation(string $segmentReference, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_SEGMENT_ACTIVER, $segmentReference, $acteur, $correlation, 'ACTIVATION_DEMANDEE',
            function () use ($segmentReference, $donnees, $acteur): array {
                $registre = $this->registre();
                $segment = $registre->resoudreSegment($segmentReference);
                $politiqueActive = $segment !== null && $registre->resoudreProfilActif((string) $segment['contexte_reference']) !== null;
                $faits = [
                    'produit_actif' => $this->produitActifReel((string) ($donnees['consommateur_produit'] ?? '')),
                    'contrat_actif' => $this->contratActifReel((string) ($donnees['contrat_reference'] ?? ''), (string) ($donnees['contrat_version'] ?? '')),
                    'politique_active' => $politiqueActive,
                    'autorisation_decision' => 'PERMIS',
                    'decision_formelle_requise' => false,
                    'decision_formelle_presente' => false,
                    'risque_bloquant' => false,
                    'incident_bloquant' => false,
                    'obligations_acceptees' => (bool) ($donnees['obligations_acceptees'] ?? false),
                ];

                return $registre->demanderActivation($segmentReference, $donnees, $faits, $acteur);
            },
            201,
            $this->codesRefusStandard(),
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendreSegment(string $reference, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_SEGMENT_SUSPENDRE, $reference, $acteur, $correlation, 'SEGMENT_SUSPENDU',
            fn (): array => $this->registre()->suspendreSegment($reference, $acteur),
            200,
            $this->codesRefusStandard(),
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function revoquerSegment(string $reference, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_SEGMENT_REVOQUER, $reference, $acteur, $correlation, 'SEGMENT_REVOQUE',
            fn (): array => $this->registre()->revoquerSegment($reference, $acteur),
            200,
            $this->codesRefusStandard(),
        );
    }

    /**
     * `contrat_actif` (CAP-CORE-009 réel), `finalite_identique` (comparée à
     * l'activation résolue en magasin) et `nominatif_autorise_contrat`
     * (figé à `false` — aucun champ de contrat ne porte aujourd'hui cette
     * autorisation dans CAP-CORE-009, réserve documentée) ne sont jamais
     * acceptés depuis le corps de la requête. `nominative` reste déclarée
     * par l'appelant : c'est une classification de la donnée, pas un fait
     * d'autorisation — le Core ne peut pas la déduire seul.
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function enregistrerMesure(string $activationReference, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_MESURE_ENREGISTRER, $activationReference, $acteur, $correlation, 'MESURE_ENREGISTREE',
            function () use ($activationReference, $donnees, $acteur): array {
                $registre = $this->registre();
                $activation = $registre->resoudreActivation($activationReference);
                $faits = [
                    'contrat_actif' => $this->contratActifReel((string) ($donnees['contrat_reference'] ?? ''), (string) ($donnees['contrat_version'] ?? '1')),
                    'finalite_identique' => $activation !== null && ($donnees['finalite_reference'] ?? null) === $activation['finalite_reference'],
                    'nominative' => (bool) ($donnees['nominative'] ?? false),
                    'nominatif_autorise_contrat' => false,
                ];

                return $registre->enregistrerMesure($activationReference, $donnees, $faits, $acteur);
            },
            201,
            $this->codesRefusStandard() + ['MATCHING_RAW_EXPORT_FORBIDDEN' => 403],
        );
    }

    /**
     * `contestant_autorise` n'est jamais accepté depuis le corps de la
     * requête. Vérification réelle mais volontairement étroite pour ce
     * périmètre : le contestant est autorisé s'il est l'acteur qui a soumis
     * la demande à l'origine du résultat contesté (`matching_demande.
     * soumise_par`) — typiquement l'identité d'intégration du consommateur
     * produit. **Réserve documentée** : un membre de la population qui
     * contesterait sa propre qualification (plutôt que le consommateur qui
     * a soumis la demande) exigerait de relier `contestant_reference` à une
     * identité ou un mandat réel via CAP-CORE-001/002/003, non câblé ici.
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function ouvrirContestation(array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_CONTESTATION_OUVRIR, $donnees['resultat_reference'] ?? null, $acteur, $correlation, 'CONTESTATION_OUVERTE',
            function () use ($donnees, $acteur): array {
                $registre = $this->registre();
                $contestantAutorise = false;
                $resultatReference = $donnees['resultat_reference'] ?? null;
                if (is_string($resultatReference) && $resultatReference !== '') {
                    $resultat = $registre->resoudreResultat($resultatReference);
                    $execution = $resultat !== null ? $registre->resoudreExecution((string) $resultat['execution_reference']) : null;
                    $demande = $execution !== null ? $registre->resoudreDemande((string) $execution['demande_reference']) : null;
                    $contestantAutorise = $demande !== null && ($donnees['contestant_reference'] ?? null) === $demande['soumise_par'];
                }
                $faits = ['contestant_autorise' => $contestantAutorise];

                return $registre->ouvrirContestation($donnees, $faits, $acteur);
            },
            201,
            $this->codesRefusStandard(),
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function reexaminer(string $contestationReference, ?string $nouvelleExecution, array $sourcesCorrigees, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_RESULTAT_REEXAMINER, $contestationReference, $acteur, $correlation, 'REEXAMEN_CONCLU',
            fn (): array => $this->registre()->reexaminer($contestationReference, $nouvelleExecution, $sourcesCorrigees, $acteur),
            201,
            $this->codesRefusStandard(),
        );
    }

    // ------------------------------------------------------------------
    // Internes

    private function registre(): RegistreMatching
    {
        return new RegistreMatching(
            MatchingMagasin::connecter(),
            resolveurProduitActif: fn (string $reference): bool => $this->produitActifReel($reference),
            resolveurContratActif: fn (string $reference, string $version): bool => $this->contratActifReel($reference, $version),
            resolveurSourceActive: fn (string $reference): bool => $this->sourceActiveReelle($reference),
        );
    }

    /** Requête réelle à CAP-CORE-011 : `false` si le produit est inconnu, inactif, ou si le registre est indisponible (échec fermé). */
    private function produitActifReel(string $reference): bool
    {
        if ($reference === '') {
            return false;
        }
        try {
            $registre = new RegistreProduits(Db::connect(), IdentiteMagasin::connecter(), ProduitsMagasin::connecter());
            $produit = $registre->resoudreProduit($reference);
        } catch (\Throwable) {
            return false;
        }

        return $produit !== null && $produit['etat'] === 'ACTIF';
    }

    /** Requête réelle à CAP-CORE-009 : `false` si la version demandée n'est pas la version active, ou si le registre est indisponible (échec fermé). */
    private function contratActifReel(string $reference, string $version): bool
    {
        if ($reference === '' || $version === '') {
            return false;
        }
        try {
            $registre = new RegistreContrats(Db::connect(), IdentiteMagasin::connecter(), ContratsMagasin::connecter());
            $active = $registre->resoudreVersionActive($reference);
        } catch (\Throwable) {
            return false;
        }

        return $active !== null && (string) $active['version'] === $version;
    }

    /** Requête réelle à CAP-CORE-006 : `false` si la source est inconnue, non `ACTIVE`, ou si le registre est indisponible (échec fermé). */
    private function sourceActiveReelle(string $reference): bool
    {
        if ($reference === '') {
            return false;
        }
        try {
            $registre = new RegistreSources(Db::connect(), IdentiteMagasin::connecter(), SourcesMagasin::connecter(), ProduitsMagasin::connecter());
            $source = $registre->resoudreSource($reference);
        } catch (\Throwable) {
            return false;
        }

        return $source !== null && $source['etat'] === 'ACTIVE';
    }

    private function journal(): Journal
    {
        return new Journal(JournalMagasin::connecter());
    }

    /** Le segment lui-même n'expose jamais ses membres (doc 01 §4, doc 04 §6) : aucune route de lecture des membres n'existe, et cette façade ne les charge même pas. */
    private function masquerMembres(array $segment): array
    {
        unset($segment['membres'], $segment['membres_hash']);

        return $segment;
    }

    /** @return array<string,int> */
    private function codesRefusStandard(): array
    {
        return [
            'DOSSIER_INCOMPLET' => 422, 'CHAMP_ABSENT' => 422, 'CRITERE_INVALIDE' => 422, 'CRITERE_SANS_REFERENCE' => 422,
            'OPERATEUR_INCONNU' => 422, 'TRAITEMENT_INCONNU_INVALIDE' => 422, 'TRAITEMENT_CONTRADICTOIRE_INVALIDE' => 422,
            'SOURCES_INVALIDES' => 422, 'POIDS_INVALIDE' => 422, 'CRITERE_INTERDIT' => 422, 'SEUILS_INVALIDES' => 422,
            'SEUILS_INCOHERENTS' => 422, 'PRECISION_INVALIDE' => 422, 'ALGORITHME_INVALIDE' => 422, 'CRITERES_INVALIDES' => 422,
            'MODE_RESULTAT_INCONNU' => 422, 'MATCHING_CONTEXT_UNKNOWN' => 404, 'MATCHING_CONTEXT_INACTIVE' => 409,
            'MATCHING_POLICY_UNKNOWN' => 404, 'MATCHING_POLICY_INACTIVE' => 409, 'MATCHING_CRITERION_NOT_ALLOWED' => 422,
            'MATCHING_REQUIRED_DATA_UNKNOWN' => 422, 'MATCHING_LIMIT_EXCEEDED' => 429, 'DEMANDE_INCONNUE' => 404,
            'TRANSITION_INVALIDE' => 409, 'RESULTAT_INCONNU' => 404, 'MATCHING_RESULT_EXPIRED' => 410,
            'SEGMENT_INCONNU' => 404, 'MATCHING_POPULATION_TOO_SMALL' => 422, 'ACTIVATION_INCONNUE' => 404,
            'MATCHING_ACTIVATION_DENIED' => 403, 'MATCHING_RISK_BLOCKED' => 403, 'MATCHING_INCIDENT_BLOCKED' => 403,
            'CONTESTATION_INCONNUE' => 404,
        ];
    }

    /** @return array{statut:int,corps:array<string,mixed>}|null */
    private function verifierLecture(string $action, ?string $ressource, string $acteur): ?array
    {
        try {
            $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser($acteur, $action, $ressource);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($decision['decision'] !== 'PERMIS') {
            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision]];
        }

        return null;
    }

    /**
     * @param callable():array<string,mixed> $operation
     * @return array{statut:int,corps:array<string,mixed>}
     */
    private function gouverner(
        string $action,
        ?string $ressource,
        string $acteur,
        ?string $correlation,
        string $typeReussite,
        callable $operation,
        int $statutReussite,
        array $codesRefus = [],
    ): array {
        try {
            $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser($acteur, $action, $ressource);
            $journal = $this->journal();
            $preuve = $journal->enregistrer([
                'categorie' => 'MATCHING', 'type' => 'DECISION_' . $typeReussite, 'acteur' => $acteur, 'action' => $action,
                'ressource' => $ressource, 'decision' => $decision['decision'] === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'], 'correlation_id' => $correlation, 'donnees' => ['politique' => $decision['politique']],
            ]);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        if ($decision['decision'] !== 'PERMIS') {
            $this->tracer($journal, [
                'categorie' => 'MATCHING', 'type' => 'OPERATION_MATCHING_REFUSEE', 'acteur' => $acteur, 'action' => $action,
                'ressource' => $ressource, 'decision' => 'REFUSEE', 'motif' => 'autorisation refusée', 'correlation_id' => $preuve['correlation_id'],
            ]);

            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve]];
        }

        try {
            $resultat = $operation();
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'MATCHING_DEPENDENCY_UNAVAILABLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'MATCHING', 'type' => 'OPERATION_MATCHING_REFUSEE', 'acteur' => $acteur, 'action' => $action,
                'ressource' => $ressource, 'decision' => 'REFUSEE', 'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'], 'donnees' => ['refus' => $resultat['refus']],
            ]);

            return [
                'statut' => $codesRefus[$resultat['refus']] ?? 422,
                'corps' => ['erreur' => 'OPERATION_REFUSEE', 'resultat' => $resultat, 'preuve' => $preuve],
            ];
        }

        $this->tracer($journal, [
            'categorie' => 'MATCHING', 'type' => $typeReussite, 'acteur' => $acteur, 'action' => $action,
            'ressource' => $ressource ?? (string) ($resultat['reference'] ?? ''), 'decision' => 'EXECUTEE', 'correlation_id' => $preuve['correlation_id'],
        ]);

        return ['statut' => $statutReussite, 'corps' => ['resultat' => $resultat, 'decision' => $decision, 'preuve' => $preuve]];
    }

    /** @param array<string,mixed> $evenement */
    private function tracer(Journal $journal, array $evenement): ?array
    {
        try {
            return $journal->enregistrer($evenement);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    private function socleIndisponible(): array
    {
        return [
            'statut' => 503,
            'corps' => [
                'erreur' => 'SOCLE_INDISPONIBLE',
                'message' => 'Le moteur de Matching est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
