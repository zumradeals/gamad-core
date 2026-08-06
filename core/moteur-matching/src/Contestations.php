<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Recevabilité et réexamen d'une contestation (doc de chantier 03 §19, doc
 * 02 §23-24, doc 04 §20 items 139-148). Fonction pure sur des faits déjà
 * résolus — cette classe ne sollicite jamais elle-même une source
 * souveraine, elle constate seulement l'écart entre deux exécutions déjà
 * produites par l'orchestration.
 */
final class Contestations
{
    /**
     * @param array{contestant_autorise:bool,resultat_existe:bool,motif_code:?string} $faits
     * @return array{recevable:bool,motif_refus:?string}
     */
    public static function verifierRecevabilite(array $faits): array
    {
        if (!$faits['resultat_existe']) {
            return ['recevable' => false, 'motif_refus' => 'MATCHING_RESULT_EXPIRED'];
        }
        if (!$faits['contestant_autorise']) {
            return ['recevable' => false, 'motif_refus' => 'MATCHING_ACTIVATION_DENIED'];
        }
        if ($faits['motif_code'] === null || $faits['motif_code'] === '') {
            return ['recevable' => false, 'motif_refus' => 'MATCHING_REQUIRED_DATA_UNKNOWN'];
        }

        return ['recevable' => true, 'motif_refus' => null];
    }

    /**
     * Compare deux classifications de résultat (avant/après réexécution) et
     * produit le verdict canonique — ne réécrit jamais l'ancien résultat, le
     * réexamen se contente de le confirmer, le remplacer ou le neutraliser.
     *
     * @return 'CONFIRME'|'MODIFIE'|'ANNULE'|'DEVENU_INDETERMINE'|'DEVENU_INTERDIT'
     */
    public static function determinerVerdict(?string $classeAncienne, ?string $classeNouvelle): string
    {
        if ($classeNouvelle === null) {
            return 'ANNULE';
        }
        if ($classeNouvelle === 'INTERDIT') {
            return 'DEVENU_INTERDIT';
        }
        if ($classeNouvelle === 'INDETERMINE') {
            return 'DEVENU_INDETERMINE';
        }
        if ($classeNouvelle === $classeAncienne) {
            return 'CONFIRME';
        }

        return 'MODIFIE';
    }
}
