<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Agrégation déterministe des évaluations de critères d'un candidat en une
 * classe, une pertinence et une confiance distinctes (doc de chantier 03
 * §6-10). Fonction pure sur des faits déjà rassemblés : aucune lecture de
 * magasin, aucun accès réseau, aucun horodatage implicite.
 *
 * Hypothèses documentées, faute de précision plus fine dans la fiche :
 *   - un critère `obligatoire=true` mais `exclusion_dure=false` dont l'état
 *     est DEFAVORABLE contribue normalement au score pondéré (poids × 0),
 *     il ne force pas à lui seul NON_CORRESPONDANT — seule l'exclusion dure
 *     le fait (doc 03 §6 : « DEFAVORABLE sur exclusion dure → NON_CORRESPONDANT »).
 *   - un critère sans poids déclaré (poids=null) est traité comme informatif :
 *     il produit un facteur explicatif mais ne participe pas au dénominateur.
 *   - CONTRADICTOIRE est exclu du dénominateur par défaut, comme NON_ETABLI,
 *     sauf si le profil déclare `inclure_contradictoire_denominateur=true`.
 *   - la confiance agrège les `confiance_source` fournies pondérées par le
 *     poids du critère, réduite d'une pénalité par critère contradictoire ;
 *     les coefficients de pénalité sont des paramètres du profil (fournis
 *     par la politique), jamais une valeur inventée par le moteur — les
 *     valeurs par défaut ci-dessous sont des replis techniques neutres,
 *     utilisés seulement si le profil ne les précise pas.
 */
final class EvaluateurDeterministe
{
    private const PENALITE_CONTRADICTOIRE_DEFAUT = 0.5;
    private const SEUILS_CLASSE_DEFAUT = ['fort' => 0.75, 'moyen' => 0.5, 'bas' => 0.25];
    private const PRECISION_DEFAUT = 4;

    /**
     * @param list<array{
     *     critere_reference:string, obligatoire:bool, exclusion_dure:bool,
     *     poids:?float, traitement_inconnu:string, traitement_contradictoire:?string,
     *     facteur_public_autorise:bool,
     * }> $criteresProfil
     * @param array<string,array{etat:string,confiance_source:?float}> $evaluationsParCritere indexé par critere_reference
     * @param array{seuils_classe?:array{fort:float,moyen:float,bas:float},precision_arrondi?:int,penalite_contradictoire?:float,inclure_contradictoire_denominateur?:bool} $parametres
     * @return array{
     *     classe:string, pertinence:?float, confiance:?float, arret_code:?string,
     *     facteurs:list<array{critere_reference:string,nature:string,public_autorise:bool}>,
     * }
     */
    public static function evaluer(array $criteresProfil, array $evaluationsParCritere, array $parametres = []): array
    {
        $seuils = $parametres['seuils_classe'] ?? self::SEUILS_CLASSE_DEFAUT;
        $precision = $parametres['precision_arrondi'] ?? self::PRECISION_DEFAUT;
        $penaliteContradictoire = $parametres['penalite_contradictoire'] ?? self::PENALITE_CONTRADICTOIRE_DEFAUT;
        $inclureContradictoireDenominateur = $parametres['inclure_contradictoire_denominateur'] ?? false;

        $facteurs = [];
        $sommeContributions = 0.0;
        $sommePoids = 0.0;
        $sommeConfiancePonderee = 0.0;
        $sommePoidsConfiance = 0.0;
        $nombreContradictoires = 0;

        foreach ($criteresProfil as $critere) {
            $reference = $critere['critere_reference'];
            $evaluation = $evaluationsParCritere[$reference] ?? ['etat' => 'NON_ETABLI', 'confiance_source' => null];
            $etat = $evaluation['etat'];
            $confianceSource = $evaluation['confiance_source'];
            $poids = $critere['poids'];

            if ($etat === 'INTERDIT') {
                return [
                    'classe' => 'INTERDIT', 'pertinence' => null, 'confiance' => null,
                    'arret_code' => 'CRITERE_INTERDIT',
                    'facteurs' => [['critere_reference' => $reference, 'nature' => 'RESTRICTION', 'public_autorise' => false]],
                ];
            }

            if ($critere['exclusion_dure'] && $etat === 'DEFAVORABLE') {
                $facteurs[] = ['critere_reference' => $reference, 'nature' => 'DEFAVORABLE', 'public_autorise' => $critere['facteur_public_autorise']];

                return [
                    'classe' => 'NON_CORRESPONDANT', 'pertinence' => null, 'confiance' => null,
                    'arret_code' => 'EXCLUSION_DURE', 'facteurs' => $facteurs,
                ];
            }

            if ($critere['obligatoire'] && $etat === 'NON_ETABLI') {
                $facteurs[] = ['critere_reference' => $reference, 'nature' => 'NON_ETABLI', 'public_autorise' => $critere['facteur_public_autorise']];
                if ($critere['traitement_inconnu'] === 'REFUS_EXECUTION') {
                    return ['classe' => 'INDETERMINE', 'pertinence' => null, 'confiance' => null, 'arret_code' => 'REFUS_EXECUTION_OBLIGATION_NON_ETABLIE', 'facteurs' => $facteurs];
                }

                return ['classe' => 'INDETERMINE', 'pertinence' => null, 'confiance' => null, 'arret_code' => 'OBLIGATION_NON_ETABLIE', 'facteurs' => $facteurs];
            }

            if ($critere['obligatoire'] && $etat === 'CONTRADICTOIRE') {
                $facteurs[] = ['critere_reference' => $reference, 'nature' => 'CONTRADICTOIRE', 'public_autorise' => $critere['facteur_public_autorise']];
                $traitement = $critere['traitement_contradictoire'] ?? $critere['traitement_inconnu'];
                if ($traitement === 'REFUS_EXECUTION') {
                    return ['classe' => 'INDETERMINE', 'pertinence' => null, 'confiance' => null, 'arret_code' => 'REFUS_EXECUTION_OBLIGATION_CONTRADICTOIRE', 'facteurs' => $facteurs];
                }

                return ['classe' => 'INDETERMINE', 'pertinence' => null, 'confiance' => null, 'arret_code' => 'OBLIGATION_CONTRADICTOIRE', 'facteurs' => $facteurs];
            }

            // Critère pondéré (ou informatif si poids null).
            $nature = match ($etat) {
                'SATISFAIT' => 'FAVORABLE',
                'DEFAVORABLE' => 'DEFAVORABLE',
                'NON_ETABLI' => 'NON_ETABLI',
                'CONTRADICTOIRE' => 'CONTRADICTOIRE',
                'NON_APPLICABLE' => null,
                default => 'NON_ETABLI',
            };
            if ($nature !== null) {
                $facteurs[] = ['critere_reference' => $reference, 'nature' => $nature, 'public_autorise' => $critere['facteur_public_autorise']];
            }

            if ($poids === null || $etat === 'NON_APPLICABLE') {
                continue;
            }

            if ($etat === 'CONTRADICTOIRE') {
                $nombreContradictoires++;
                if ($inclureContradictoireDenominateur) {
                    $sommePoids += $poids;
                }
                continue;
            }

            if ($etat === 'NON_ETABLI') {
                if ($critere['traitement_inconnu'] === 'IGNORER_AVEC_TRACE') {
                    continue;
                }
                // ECHEC_CRITERE et INDETERMINE (non obligatoire) : traité comme
                // défavorable dans le dénominateur, sans invention de valeur.
                $sommePoids += $poids;
                continue;
            }

            // SATISFAIT ou DEFAVORABLE : contribution admissible.
            $sommePoids += $poids;
            if ($etat === 'SATISFAIT') {
                $sommeContributions += $poids;
            }
            if ($confianceSource !== null) {
                $sommeConfiancePonderee += $poids * $confianceSource;
                $sommePoidsConfiance += $poids;
            }
        }

        $pertinence = $sommePoids > 0.0 ? round($sommeContributions / $sommePoids, $precision) : null;
        $confiance = $sommePoidsConfiance > 0.0
            ? round(max(0.0, min(1.0, ($sommeConfiancePonderee / $sommePoidsConfiance) * (1.0 - $penaliteContradictoire * min(1, $nombreContradictoires)))), $precision)
            : null;

        if ($pertinence === null) {
            return ['classe' => 'INDETERMINE', 'pertinence' => null, 'confiance' => $confiance, 'arret_code' => 'AUCUN_CRITERE_ADMISSIBLE', 'facteurs' => $facteurs];
        }

        $classe = match (true) {
            $pertinence >= $seuils['fort'] => 'CORRESPONDANCE_FORTE',
            $pertinence >= $seuils['moyen'] => 'CORRESPONDANCE_PROBABLE',
            $pertinence >= $seuils['bas'] => 'CORRESPONDANCE_PARTIELLE',
            default => 'NON_CORRESPONDANT',
        };

        return ['classe' => $classe, 'pertinence' => $pertinence, 'confiance' => $confiance, 'arret_code' => null, 'facteurs' => $facteurs];
    }
}
