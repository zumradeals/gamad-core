<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Vérification déterministe qu'un signal est utilisable pour un critère
 * donné (doc de chantier 03 §4, §20-21, doc 04 §20 items 26-40). Fonction
 * pure : ne lit jamais directement un autre magasin, ne matérialise rien —
 * l'orchestration lui fournit déjà le signal et l'état de sa source.
 */
final class ResolutionSources
{
    /**
     * @param array{sources_autorisees:list<string>,fraicheur_max_secondes:?int} $critere
     * @param array{source_reference:string,source_active:bool,finalite_reference:string,statut:string,observation_le:string} $signal
     * @return array{utilisable:bool,motif_code:?string,etat_signal:?string}
     */
    public static function verifierSignal(array $critere, array $signal, string $finaliteAttendue, string $instantReference): array
    {
        if ($critere['sources_autorisees'] !== [] && !in_array($signal['source_reference'], $critere['sources_autorisees'], true)) {
            return ['utilisable' => false, 'motif_code' => 'MATCHING_SOURCE_UNKNOWN', 'etat_signal' => null];
        }
        if (!$signal['source_active']) {
            return ['utilisable' => false, 'motif_code' => 'MATCHING_SOURCE_UNUSABLE', 'etat_signal' => null];
        }
        if ($signal['finalite_reference'] !== $finaliteAttendue) {
            return ['utilisable' => false, 'motif_code' => 'MATCHING_PURPOSE_NOT_ALLOWED', 'etat_signal' => null];
        }
        if (in_array($signal['statut'], ['REVOQUE', 'INUTILISABLE', 'SUPPRIME_LOGIQUEMENT'], true)) {
            return ['utilisable' => false, 'motif_code' => 'MATCHING_SOURCE_UNUSABLE', 'etat_signal' => $signal['statut']];
        }
        if ($signal['statut'] === 'PERIME') {
            return ['utilisable' => false, 'motif_code' => 'MATCHING_SIGNAL_EXPIRED', 'etat_signal' => 'PERIME'];
        }

        $instant = strtotime($instantReference);
        $observation = strtotime($signal['observation_le']);
        if ($critere['fraicheur_max_secondes'] !== null && $instant !== false && $observation !== false) {
            if (($instant - $observation) > $critere['fraicheur_max_secondes']) {
                return ['utilisable' => false, 'motif_code' => 'MATCHING_SIGNAL_EXPIRED', 'etat_signal' => 'PERIME'];
            }
        }

        if ($signal['statut'] === 'CONTRADICTOIRE') {
            // Un signal contradictoire reste visible et tracé, jamais caché
            // (doc 03 §21) : c'est à EvaluateurDeterministe de décider de sa
            // contribution, pas à cette résolution de le rejeter en silence.
            return ['utilisable' => true, 'motif_code' => null, 'etat_signal' => 'CONTRADICTOIRE'];
        }

        return ['utilisable' => true, 'motif_code' => null, 'etat_signal' => 'VALIDE'];
    }
}
