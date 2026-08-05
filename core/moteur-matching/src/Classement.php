<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Tri déterministe de plusieurs résultats d'une même exécution (doc de
 * chantier 03 §10). Fonction pure : ne lit rien, ne décide d'aucune
 * autorisation, ne fabrique aucun ordre aléatoire.
 *
 * Ordre de départage, dans cet ordre strict :
 *   1. admissibilité (un candidat refusé au pré-filtrage n'est jamais classé) ;
 *   2. classe (CORRESPONDANCE_FORTE > PROBABLE > PARTIELLE > le reste) ;
 *   3. pertinence (descendante, `null` traité comme le plus bas) ;
 *   4. confiance (descendante, `null` traité comme le plus bas) ;
 *   5. règles secondaires explicitement déclarées par le profil, dans
 *      l'ordre fourni par l'appelant — jamais un attribut caché choisi par
 *      cette classe elle-même ;
 *   6. référence stable du candidat, en dernier départage technique.
 */
final class Classement
{
    private const PRIORITE_CLASSE = [
        'CORRESPONDANCE_FORTE' => 3,
        'CORRESPONDANCE_PROBABLE' => 2,
        'CORRESPONDANCE_PARTIELLE' => 1,
    ];

    /**
     * @param list<array{
     *     candidat_reference:string, admissible:bool, classe:string,
     *     pertinence:?float, confiance:?float, regles_secondaires:list<float|int>,
     * }> $resultats
     * @return list<array{candidat_reference:string, rang:?int}+array<string,mixed>>
     */
    public static function classer(array $resultats): array
    {
        $indexes = array_keys($resultats);
        usort($indexes, function (int $a, int $b) use ($resultats): int {
            return self::comparer($resultats[$a], $resultats[$b]);
        });

        $classes = [];
        $rang = 0;
        foreach ($indexes as $i) {
            $ligne = $resultats[$i];
            $estClasse = $ligne['admissible'] && self::priorite($ligne['classe']) > 0;
            if ($estClasse) {
                $rang++;
                $ligne['rang'] = $rang;
            } else {
                $ligne['rang'] = null;
            }
            $classes[] = $ligne;
        }

        return $classes;
    }

    private static function comparer(array $a, array $b): int
    {
        if ($a['admissible'] !== $b['admissible']) {
            return $a['admissible'] ? -1 : 1;
        }

        $pa = self::priorite($a['classe']);
        $pb = self::priorite($b['classe']);
        if ($pa !== $pb) {
            return $pa > $pb ? -1 : 1;
        }

        $cmpPertinence = self::comparerNullable($a['pertinence'], $b['pertinence']);
        if ($cmpPertinence !== 0) {
            return $cmpPertinence;
        }

        $cmpConfiance = self::comparerNullable($a['confiance'], $b['confiance']);
        if ($cmpConfiance !== 0) {
            return $cmpConfiance;
        }

        $reglesA = $a['regles_secondaires'] ?? [];
        $reglesB = $b['regles_secondaires'] ?? [];
        $n = max(count($reglesA), count($reglesB));
        for ($i = 0; $i < $n; $i++) {
            $va = $reglesA[$i] ?? null;
            $vb = $reglesB[$i] ?? null;
            $cmp = self::comparerNullable(is_numeric($va) ? (float) $va : null, is_numeric($vb) ? (float) $vb : null);
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return $a['candidat_reference'] <=> $b['candidat_reference'];
    }

    private static function priorite(string $classe): int
    {
        return self::PRIORITE_CLASSE[$classe] ?? 0;
    }

    private static function comparerNullable(?float $a, ?float $b): int
    {
        if ($a === $b) {
            return 0;
        }
        if ($a === null) {
            return 1;
        }
        if ($b === null) {
            return -1;
        }

        return $a > $b ? -1 : 1;
    }
}
