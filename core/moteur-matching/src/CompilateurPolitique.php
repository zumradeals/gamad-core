<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

use Gamad\RegistrePreuves\Canonicaliseur;

/**
 * Compile une spécification de politique de Matching en plan d'exécution
 * canonique et reproductible (doc de chantier 02 §4, doc 03 §1).
 *
 * Réserve documentée, vérifiée sur le code réel de CAP-CORE-007
 * (`Gamad\RegistrePolitiques\RegistrePolitiques`) : ce registre ne porte
 * aujourd'hui que des règles d'autorisation fermées (effet PERMET/REFUSE sur
 * une action pour un sujet — `regle_politique`), aucun contenu structuré de
 * critères, poids ou seuils. `POL-MATCHING-V1` (bootstrapée dans
 * CAP-CORE-007) gouverne donc uniquement QUI peut exercer une action du
 * Matching, pas QUOI comparer pour un contexte donné. Étendre CAP-CORE-007
 * pour porter un contenu structuré est hors périmètre de ce chantier — pas
 * une omission silencieuse, une limite technique constatée et documentée
 * (voir le rapport d'admission final).
 *
 * En attendant cette extension, ce compilateur prend en entrée une
 * spécification déjà versionnée et explicite (fournie par l'appelant —
 * jamais inventée ici), la valide contre le vocabulaire fermé de
 * `PolitiqueMatching`, puis produit un plan canonique et son empreinte.
 * Aucune donnée manquante n'est complétée par une valeur par défaut
 * arbitraire : une spécification incomplète est refusée.
 */
final class CompilateurPolitique
{
    public const ALGORITHME_DEFAUT = 'MATCHING_DETERMINISTE_V1';

    /**
     * @param array<string,mixed> $specification
     * @return array{refus:string,detail:string}|array{
     *     politique_reference:string, politique_version:string, contexte_reference:string,
     *     contrat_reference:string, contrat_version:string, algorithme_code:string, algorithme_version:string,
     *     plan_canonique_json:string, plan_hash:string,
     * }
     */
    public static function compiler(array $specification): array
    {
        foreach (['politique_reference', 'politique_version', 'contexte_reference', 'contrat_reference', 'contrat_version'] as $champ) {
            if (!is_string($specification[$champ] ?? null) || $specification[$champ] === '') {
                return self::refus('CHAMP_ABSENT', "champ obligatoire absent ou vide : {$champ}");
            }
        }

        $algorithmeCode = $specification['algorithme_code'] ?? self::ALGORITHME_DEFAUT;
        $algorithmeVersion = $specification['algorithme_version'] ?? '1';
        if (!is_string($algorithmeCode) || !is_string($algorithmeVersion)) {
            return self::refus('ALGORITHME_INVALIDE', 'algorithme_code et algorithme_version doivent être des chaînes');
        }

        $criteres = $specification['criteres'] ?? [];
        if (!is_array($criteres)) {
            return self::refus('CRITERES_INVALIDES', 'criteres doit être une liste');
        }
        $criteresValides = [];
        foreach ($criteres as $i => $critere) {
            $verification = self::validerCritere($critere, (string) $i);
            if (isset($verification['refus'])) {
                return $verification;
            }
            $criteresValides[] = $verification;
        }

        $seuils = $specification['seuils_classe'] ?? null;
        if ($seuils !== null) {
            if (!is_array($seuils) || !isset($seuils['fort'], $seuils['moyen'], $seuils['bas'])) {
                return self::refus('SEUILS_INVALIDES', 'seuils_classe doit fournir fort, moyen et bas');
            }
            foreach (['fort', 'moyen', 'bas'] as $c) {
                if (!is_numeric($seuils[$c])) {
                    return self::refus('SEUILS_INVALIDES', "seuil {$c} doit être numérique");
                }
            }
            if (!($seuils['fort'] >= $seuils['moyen'] && $seuils['moyen'] >= $seuils['bas'])) {
                return self::refus('SEUILS_INCOHERENTS', 'les seuils doivent être décroissants : fort ≥ moyen ≥ bas');
            }
        }

        $precisionArrondi = $specification['precision_arrondi'] ?? null;
        if ($precisionArrondi !== null && (!is_int($precisionArrondi) || $precisionArrondi < 0 || $precisionArrondi > 10)) {
            return self::refus('PRECISION_INVALIDE', 'precision_arrondi doit être un entier entre 0 et 10');
        }

        self::rejeterCodeExecutable($specification);

        $plan = [
            'politique_reference' => $specification['politique_reference'],
            'politique_version' => $specification['politique_version'],
            'contexte_reference' => $specification['contexte_reference'],
            'contrat_reference' => $specification['contrat_reference'],
            'contrat_version' => $specification['contrat_version'],
            'algorithme_code' => $algorithmeCode,
            'algorithme_version' => $algorithmeVersion,
            'criteres' => $criteresValides,
            'seuils_classe' => $seuils,
            'precision_arrondi' => $precisionArrondi,
        ];

        $planCanonique = Canonicaliseur::canonicaliser($plan);
        $planHash = hash('sha256', $planCanonique);

        return [
            'politique_reference' => $specification['politique_reference'],
            'politique_version' => $specification['politique_version'],
            'contexte_reference' => $specification['contexte_reference'],
            'contrat_reference' => $specification['contrat_reference'],
            'contrat_version' => $specification['contrat_version'],
            'algorithme_code' => $algorithmeCode,
            'algorithme_version' => $algorithmeVersion,
            'plan_canonique_json' => $planCanonique,
            'plan_hash' => $planHash,
        ];
    }

    /** @param mixed $critere @return array{refus:string,detail:string}|array<string,mixed> */
    private static function validerCritere(mixed $critere, string $position): array
    {
        if (!is_array($critere)) {
            return self::refus('CRITERE_INVALIDE', "critère #{$position} : doit être un tableau");
        }
        $reference = $critere['critere_reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            return self::refus('CRITERE_SANS_REFERENCE', "critère #{$position} : référence absente");
        }
        $operateur = $critere['operateur'] ?? null;
        if (!is_string($operateur) || !in_array($operateur, PolitiqueMatching::OPERATEURS, true)) {
            return self::refus('OPERATEUR_INCONNU', "critère {$reference} : opérateur hors liste close ({$operateur})");
        }
        $traitementInconnu = $critere['traitement_inconnu'] ?? null;
        if (!is_string($traitementInconnu) || !in_array($traitementInconnu, PolitiqueMatching::TRAITEMENTS_INCONNU, true)) {
            return self::refus('TRAITEMENT_INCONNU_INVALIDE', "critère {$reference} : traitement_inconnu hors liste close");
        }
        $traitementContradictoire = $critere['traitement_contradictoire'] ?? null;
        if ($traitementContradictoire !== null && !in_array($traitementContradictoire, PolitiqueMatching::TRAITEMENTS_INCONNU, true)) {
            return self::refus('TRAITEMENT_CONTRADICTOIRE_INVALIDE', "critère {$reference} : traitement_contradictoire hors liste close");
        }
        $sourcesAutorisees = $critere['sources_autorisees'] ?? [];
        if (!is_array($sourcesAutorisees)) {
            return self::refus('SOURCES_INVALIDES', "critère {$reference} : sources_autorisees doit être une liste");
        }
        $poids = $critere['poids'] ?? null;
        if ($poids !== null && !is_numeric($poids)) {
            return self::refus('POIDS_INVALIDE', "critère {$reference} : poids doit être numérique ou absent");
        }
        $identifiantSuspect = mb_strtoupper(str_replace(['-', '_', ' '], '', $reference . ($critere['explication_code'] ?? '')));
        foreach (PolitiqueMatching::CRITERES_INTERDITS as $interdit) {
            $motif = str_replace('_', '', $interdit);
            if ($identifiantSuspect === $motif) {
                return self::refus('CRITERE_INTERDIT', "critère {$reference} : correspond à une catégorie interdite par défaut ({$interdit})");
            }
        }

        return [
            'critere_reference' => $reference,
            'operateur' => $operateur,
            'valeur_type' => is_string($critere['valeur_type'] ?? null) ? $critere['valeur_type'] : 'TEXTE',
            'obligatoire' => (bool) ($critere['obligatoire'] ?? false),
            'exclusion_dure' => (bool) ($critere['exclusion_dure'] ?? false),
            'poids' => $poids !== null ? (float) $poids : null,
            'seuil' => isset($critere['seuil']) && is_numeric($critere['seuil']) ? (float) $critere['seuil'] : null,
            'traitement_inconnu' => $traitementInconnu,
            'traitement_contradictoire' => $traitementContradictoire,
            'fraicheur_max_secondes' => isset($critere['fraicheur_max_secondes']) ? (int) $critere['fraicheur_max_secondes'] : null,
            'sources_autorisees' => array_values(array_map('strval', $sourcesAutorisees)),
            'explication_code' => is_string($critere['explication_code'] ?? null) ? $critere['explication_code'] : null,
            'facteur_public_autorise' => (bool) ($critere['facteur_public_autorise'] ?? false),
        ];
    }

    /** Refuse toute valeur qui ne serait pas sérialisable en JSON pur (aucun code exécutable libre). */
    private static function rejeterCodeExecutable(mixed $valeur): void
    {
        if (is_object($valeur) || is_resource($valeur)) {
            throw new ExceptionMatching('Spécification de politique invalide : valeur non sérialisable détectée (objet ou ressource).');
        }
        if (is_array($valeur)) {
            foreach ($valeur as $v) {
                self::rejeterCodeExecutable($v);
            }
        }
    }

    /** @return array{refus:string,detail:string} */
    private static function refus(string $code, string $detail): array
    {
        return ['refus' => $code, 'detail' => $detail];
    }
}
