<?php

declare(strict_types=1);

namespace App\Application\Matching;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\MoteurMatching\Magasin as MatchingMagasin;
use Gamad\MoteurMatching\PolitiqueMatching;
use Gamad\MoteurMatching\RegistreMatching;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;

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

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function demanderActivation(string $segmentReference, array $donnees, array $faits, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_SEGMENT_ACTIVER, $segmentReference, $acteur, $correlation, 'ACTIVATION_DEMANDEE',
            fn (): array => $this->registre()->demanderActivation($segmentReference, $donnees, $faits, $acteur),
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

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function enregistrerMesure(string $activationReference, array $donnees, array $faits, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_MESURE_ENREGISTRER, $activationReference, $acteur, $correlation, 'MESURE_ENREGISTREE',
            fn (): array => $this->registre()->enregistrerMesure($activationReference, $donnees, $faits, $acteur),
            201,
            $this->codesRefusStandard() + ['MATCHING_RAW_EXPORT_FORBIDDEN' => 403],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function ouvrirContestation(array $donnees, array $faits, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueMatching::ACTION_CONTESTATION_OUVRIR, $donnees['resultat_reference'] ?? null, $acteur, $correlation, 'CONTESTATION_OUVERTE',
            fn (): array => $this->registre()->ouvrirContestation($donnees, $faits, $acteur),
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
        return new RegistreMatching(MatchingMagasin::connecter());
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
