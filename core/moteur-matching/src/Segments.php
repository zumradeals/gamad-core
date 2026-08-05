<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Construction d'un segment protégé (doc de chantier 02 §17-18, doc 03 §12).
 *
 * Tokenisation : la fiche autorise soit une opération de CAP-CORE-016, soit
 * « une construction cryptographique validée » (doc 02 §18). Ce chantier
 * retient la seconde option pour cette première version : un identifiant
 * aléatoire opaque (`random_bytes`, non dérivé, non réversible), sans aucune
 * clé à conserver ni à faire correspondre — la correspondance token → sujet
 * n'existe que dans `matching_segment_membre`. Une intégration CAP-CORE-016
 * (par exemple un HMAC borné) reste possible dans un chantier ultérieur si
 * un besoin réel l'exige ; ce n'est pas fait ici faute de nécessité constatée.
 *
 * Fonctions pures : ne lit ni n'écrit le magasin. L'appelant (façade
 * d'orchestration, à venir) persiste les lignes produites ici.
 */
final class Segments
{
    private const LONGUEUR_TOKEN_OCTETS = 32;

    /**
     * @param list<string> $sujetsAdmissibles références internes des sujets appariés, déjà dédupliquées et triées par l'appelant
     * @return array{refus:string,detail:string}|array{
     *     population_nombre:int, membres_hash:string,
     *     membres:list<array{membre_token:string,sujet_reference_interne:string}>,
     * }
     */
    public static function construire(array $sujetsAdmissibles, int $seuilPetitePopulation): array
    {
        $population = count($sujetsAdmissibles);
        if ($population < $seuilPetitePopulation) {
            return [
                'refus' => 'MATCHING_POPULATION_TOO_SMALL',
                'detail' => "population admissible ({$population}) sous le seuil minimal d'agrégation ({$seuilPetitePopulation})",
            ];
        }

        $tries = $sujetsAdmissibles;
        sort($tries, SORT_STRING);
        $membresHash = hash('sha256', implode('|', $tries));

        $membres = [];
        $tokensEmis = [];
        foreach ($tries as $sujet) {
            do {
                $token = 'MTK-' . bin2hex(random_bytes(self::LONGUEUR_TOKEN_OCTETS));
            } while (isset($tokensEmis[$token]));
            $tokensEmis[$token] = true;
            $membres[] = ['membre_token' => $token, 'sujet_reference_interne' => $sujet];
        }

        return ['population_nombre' => $population, 'membres_hash' => $membresHash, 'membres' => $membres];
    }

    /**
     * Vérification d'appartenance (doc 02 §19) : ne révèle jamais la liste
     * complète, seulement le statut du token présenté.
     *
     * @param list<array{membre_token:string,sujet_reference_interne:string,statut:string,valide_jusqua:string}> $membresSegment
     * @return 'APPARTIENT'|'N_APPARTIENT_PAS'|'INDETERMINE'
     */
    public static function verifierAppartenance(array $membresSegment, string $tokenPresente, string $instantReference): string
    {
        foreach ($membresSegment as $membre) {
            if (!hash_equals($membre['membre_token'], $tokenPresente)) {
                continue;
            }
            if ($membre['statut'] !== 'ACTIF') {
                return 'N_APPARTIENT_PAS';
            }
            if (strtotime($membre['valide_jusqua']) !== false && strtotime($instantReference) > strtotime($membre['valide_jusqua'])) {
                return 'N_APPARTIENT_PAS';
            }

            return 'APPARTIENT';
        }

        return 'N_APPARTIENT_PAS';
    }
}
