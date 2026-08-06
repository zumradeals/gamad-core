<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Validation et agrégation déterministe des mesures d'activation (doc de
 * chantier 03 §16, doc 04 §20 items 116-128). Fonction pure : ne lit ni
 * n'écrit le magasin, ne modifie jamais une politique.
 */
final class Mesure
{
    /**
     * @param array{contrat_actif:bool,finalite_identique:bool,nominative:bool,nominatif_autorise_contrat:bool} $faits
     * @return array{valide:bool,motif_code:?string}
     */
    public static function valider(array $faits): array
    {
        if (!$faits['contrat_actif']) {
            return ['valide' => false, 'motif_code' => 'MATCHING_CONTRACT_INACTIVE'];
        }
        if (!$faits['finalite_identique']) {
            return ['valide' => false, 'motif_code' => 'MATCHING_PURPOSE_NOT_ALLOWED'];
        }
        // Une mesure nominative (non agrégée) équivaut à une sortie de donnée
        // individuelle : même interdiction par défaut qu'un export brut de
        // segment (doc 03 §16 : « aucune copie de contenu ou conversation
        // complète »), sauf contrat spécifique l'autorisant explicitement.
        if ($faits['nominative'] && !$faits['nominatif_autorise_contrat']) {
            return ['valide' => false, 'motif_code' => 'MATCHING_RAW_EXPORT_FORBIDDEN'];
        }

        return ['valide' => true, 'motif_code' => null];
    }

    /**
     * Agrégation mécanique (somme, moyenne, nombre) — aucune pondération
     * commerciale, aucune interprétation de qualité inventée ici.
     *
     * @param list<array{mesure_code:string,valeur_numerique:?float}> $mesures
     * @return array<string,array{nombre:int,somme:float,moyenne:?float}>
     */
    public static function agreger(array $mesures): array
    {
        $parCode = [];
        foreach ($mesures as $mesure) {
            $code = $mesure['mesure_code'];
            $parCode[$code] ??= ['nombre' => 0, 'somme' => 0.0, 'valeurs' => 0];
            $parCode[$code]['nombre']++;
            if ($mesure['valeur_numerique'] !== null) {
                $parCode[$code]['somme'] += $mesure['valeur_numerique'];
                $parCode[$code]['valeurs']++;
            }
        }

        $resultat = [];
        foreach ($parCode as $code => $agregat) {
            $resultat[$code] = [
                'nombre' => $agregat['nombre'],
                'somme' => $agregat['somme'],
                'moyenne' => $agregat['valeurs'] > 0 ? round($agregat['somme'] / $agregat['valeurs'], 4) : null,
            ];
        }

        return $resultat;
    }
}
