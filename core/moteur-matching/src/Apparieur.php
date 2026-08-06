<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Évaluation déterministe d'un opérateur fermé (doc de chantier 03 §5) sur
 * une valeur observée et une valeur attendue déjà normalisées par
 * CAP-CORE-010.
 *
 * Fonction pure sur des faits déjà rassemblés — aucune expression SQL,
 * aucune regex fournie par le consommateur, aucune fonction PHP arbitraire.
 * Les dates sont comparées à un instant explicite ; les unités doivent déjà
 * être canoniques avant d'atteindre cette classe.
 */
final class Apparieur
{
    /**
     * @param mixed $observee  valeur normalisée observée (peut être null si non établie)
     * @param mixed $attendue  valeur normalisée attendue par le critère
     * @return bool|null  true/false si déterminable, null si non déterminable avec ces entrées
     */
    public static function evaluer(string $operateur, mixed $observee, mixed $attendue, ?string $instantReference = null): ?bool
    {
        if (!in_array($operateur, PolitiqueMatching::OPERATEURS, true)) {
            throw new \InvalidArgumentException("Opérateur hors liste close : {$operateur}");
        }

        if ($operateur === 'EXISTS_VERIFIED') {
            return $observee !== null;
        }
        if ($operateur === 'NOT_EXISTS_VERIFIED') {
            return $observee === null;
        }

        // Tout autre opérateur exige une valeur observée déterminable.
        if ($observee === null) {
            return null;
        }

        return match ($operateur) {
            'EQ' => self::comparable($observee, $attendue) ? $observee === $attendue || self::egaliteNumerique($observee, $attendue) : null,
            'NEQ' => self::comparable($observee, $attendue) ? !(($observee === $attendue) || self::egaliteNumerique($observee, $attendue)) : null,
            'IN' => is_array($attendue) ? in_array($observee, $attendue, true) : null,
            'NOT_IN' => is_array($attendue) ? !in_array($observee, $attendue, true) : null,
            'GT' => self::numerique($observee) !== null && self::numerique($attendue) !== null
                ? self::numerique($observee) > self::numerique($attendue) : null,
            'GTE' => self::numerique($observee) !== null && self::numerique($attendue) !== null
                ? self::numerique($observee) >= self::numerique($attendue) : null,
            'LT' => self::numerique($observee) !== null && self::numerique($attendue) !== null
                ? self::numerique($observee) < self::numerique($attendue) : null,
            'LTE' => self::numerique($observee) !== null && self::numerique($attendue) !== null
                ? self::numerique($observee) <= self::numerique($attendue) : null,
            'BETWEEN' => self::evaluerBetween($observee, $attendue),
            'CONTAINS_CANONICAL' => is_array($observee) ? in_array($attendue, $observee, true) : null,
            'INTERSECTS_CANONICAL' => is_array($observee) && is_array($attendue)
                ? array_intersect($observee, $attendue) !== [] : null,
            'WITHIN_REALM' => is_array($attendue) ? in_array($observee, $attendue, true) : ($attendue !== null ? $observee === $attendue : null),
            'VALID_AT' => self::evaluerValidAt($observee, $instantReference),
            default => null,
        };
    }

    /** @param array{0:mixed,1:mixed}|mixed $borne */
    private static function evaluerBetween(mixed $observee, mixed $borne): ?bool
    {
        if (!is_array($borne) || !array_key_exists(0, $borne) || !array_key_exists(1, $borne)) {
            return null;
        }
        $v = self::numerique($observee);
        $min = self::numerique($borne[0]);
        $max = self::numerique($borne[1]);
        if ($v === null || $min === null || $max === null) {
            return null;
        }

        return $v >= $min && $v <= $max;
    }

    /**
     * @param mixed $observee  tableau ['depuis' => ?string, 'jusqua' => ?string] au format ISO 8601
     */
    private static function evaluerValidAt(mixed $observee, ?string $instantReference): ?bool
    {
        if (!is_array($observee) || $instantReference === null) {
            return null;
        }
        $instant = strtotime($instantReference);
        if ($instant === false) {
            return null;
        }
        $depuis = isset($observee['depuis']) ? strtotime((string) $observee['depuis']) : false;
        $jusqua = isset($observee['jusqua']) && $observee['jusqua'] !== null ? strtotime((string) $observee['jusqua']) : null;
        if ($depuis === false) {
            return null;
        }

        return $instant >= $depuis && ($jusqua === null || $instant <= $jusqua);
    }

    private static function comparable(mixed $a, mixed $b): bool
    {
        return !is_array($a) && !is_array($b);
    }

    private static function egaliteNumerique(mixed $a, mixed $b): bool
    {
        $na = self::numerique($a);
        $nb = self::numerique($b);

        return $na !== null && $nb !== null && $na === $nb;
    }

    /**
     * `NAN` n'est jamais traité comme une valeur numérique comparable : sans
     * ce garde-fou, les comparaisons PHP (`NAN >= x` vaut toujours `false`)
     * transformeraient silencieusement une valeur non déterminable en un
     * résultat déterminé DEFAVORABLE — une invention, pas une constatation
     * (doc 03 §1). `JSON` ne peut pas transporter `NaN`/`Infinity` comme
     * littéraux ; ce cas ne survient que si une valeur déjà en mémoire PHP
     * est comparée directement, jamais depuis une charge HTTP décodée.
     */
    private static function numerique(mixed $v): ?float
    {
        if (is_float($v) && is_nan($v)) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (is_string($v) && is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }
}
