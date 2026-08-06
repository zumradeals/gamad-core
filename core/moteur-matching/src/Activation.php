<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Vérification déterministe d'une demande d'activation de segment (doc de
 * chantier 03 §13). Fonction pure sur des faits déjà résolus par l'appelant
 * (l'orchestration interroge CAP-CORE-004, CAP-CORE-008, CAP-CORE-017 et
 * CAP-CORE-018 en amont — cette classe ne les contacte jamais elle-même).
 *
 * L'activation ne devient `ACTIVE` qu'après accusé du consommateur ; cette
 * classe ne produit que la décision `AUTORISEE`/`REFUSEE`/`INDETERMINEE` en
 * amont de cet accusé (doc 03 §13, dernier paragraphe).
 */
final class Activation
{
    /**
     * @param array{
     *     segment_etat:string, segment_expire_le:string, instant_reference:string,
     *     segment_consommateur_produit:string, demande_consommateur_produit:string,
     *     segment_finalite_reference:string, demande_finalite_reference:string,
     *     segment_realm_reference:string, demande_realm_reference:string,
     *     produit_actif:bool, contrat_actif:bool, politique_active:bool,
     *     autorisation_decision:string, decision_formelle_requise:bool, decision_formelle_presente:bool,
     *     risque_bloquant:bool, incident_bloquant:bool, obligations_acceptees:bool,
     * } $faits
     * @return array{decision:'AUTORISEE'|'REFUSEE'|'INDETERMINEE', motif_code:?string}
     */
    public static function verifier(array $faits): array
    {
        if ($faits['segment_etat'] !== 'ACTIF') {
            return self::refus('MATCHING_SEGMENT_' . match ($faits['segment_etat']) {
                'EXPIRE' => 'EXPIRED', 'SUSPENDU' => 'SUSPENDED', 'REVOQUE' => 'REVOKED', default => 'SUSPENDED',
            });
        }
        if (strtotime($faits['instant_reference']) === false || strtotime($faits['segment_expire_le']) === false) {
            return ['decision' => 'INDETERMINEE', 'motif_code' => 'HORLOGE_INCOHERENTE'];
        }
        if (strtotime($faits['instant_reference']) > strtotime($faits['segment_expire_le'])) {
            return self::refus('MATCHING_SEGMENT_EXPIRED');
        }
        if ($faits['segment_consommateur_produit'] !== $faits['demande_consommateur_produit']) {
            return self::refus('MATCHING_CONSUMER_NOT_ALLOWED');
        }
        if ($faits['segment_finalite_reference'] !== $faits['demande_finalite_reference']) {
            return self::refus('MATCHING_PURPOSE_NOT_ALLOWED');
        }
        if ($faits['segment_realm_reference'] !== $faits['demande_realm_reference']) {
            return self::refus('MATCHING_REALM_NOT_ALLOWED');
        }
        if (!$faits['produit_actif']) {
            return self::refus('MATCHING_PRODUCT_INACTIVE');
        }
        if (!$faits['contrat_actif']) {
            return self::refus('MATCHING_CONTRACT_INACTIVE');
        }
        if (!$faits['politique_active']) {
            return self::refus('MATCHING_POLICY_INACTIVE');
        }
        if ($faits['autorisation_decision'] !== 'PERMIS') {
            return self::refus('MATCHING_ACTIVATION_DENIED');
        }
        if ($faits['decision_formelle_requise'] && !$faits['decision_formelle_presente']) {
            return self::refus('MATCHING_ACTIVATION_DENIED');
        }
        if ($faits['risque_bloquant']) {
            return self::refus('MATCHING_RISK_BLOCKED');
        }
        if ($faits['incident_bloquant']) {
            return self::refus('MATCHING_INCIDENT_BLOCKED');
        }
        if (!$faits['obligations_acceptees']) {
            return self::refus('MATCHING_ACTIVATION_DENIED');
        }

        return ['decision' => 'AUTORISEE', 'motif_code' => null];
    }

    private static function refus(string $motif): array
    {
        return ['decision' => 'REFUSEE', 'motif_code' => $motif];
    }
}
