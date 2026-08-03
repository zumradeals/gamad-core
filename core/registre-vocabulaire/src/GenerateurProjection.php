<?php

declare(strict_types=1);

namespace Gamad\RegistreVocabulaire;

/**
 * Projections dérivées d'une version de vocabulaire, et contrôle de dérive
 * avec les constantes PHP et contraintes SQL déjà en vigueur dans les
 * capacités consommatrices.
 *
 * Une projection n'est jamais la source canonique : en cas de divergence
 * avec le registre, le registre l'emporte. `comparerCodes()` ne remplace
 * aucune contrainte `CHECK` ni aucune constante PHP existante (section 21 de
 * la fiche de codage) — il détecte seulement l'écart.
 */
final class GenerateurProjection
{
    /** @param list<array<string,mixed>> $termes */
    public static function genererJson(string $reference, string $version, array $termes): string
    {
        $payload = [
            'vocabulaire' => $reference,
            'version' => $version,
            'termes' => array_map(static fn (array $t): array => [
                'reference' => $t['reference'], 'code' => $t['code'], 'definition' => $t['definition'],
                'type_semantique' => $t['type_semantique'],
            ], $termes),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** @param list<array<string,mixed>> $termes */
    public static function genererConstantesPhp(string $reference, array $termes): string
    {
        $nomClasse = 'Vocabulaire' . str_replace(['VOC-GAMAD-', '-'], ['', ''], $reference);
        $lignes = ["final class {$nomClasse} {", '    public const CODES = ['];
        foreach ($termes as $t) {
            $lignes[] = "        '{$t['code']}',";
        }
        $lignes[] = '    ];';
        $lignes[] = '}';

        return implode("\n", $lignes);
    }

    /** @param list<array<string,mixed>> $termes */
    public static function genererEnumOpenApi(array $termes): string
    {
        $codes = array_map(static fn (array $t): string => (string) $t['code'], $termes);

        return json_encode(['enum' => array_values($codes)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param list<array<string,mixed>> $termes */
    public static function genererContrainteSql(string $colonne, array $termes): string
    {
        $codes = array_map(static fn (array $t): string => "'" . str_replace("'", "''", (string) $t['code']) . "'", $termes);

        return "CHECK ({$colonne} IN (" . implode(',', $codes) . '))';
    }

    /** @param list<array<string,mixed>> $termes */
    public static function genererDocumentation(string $reference, string $version, array $termes): string
    {
        $lignes = ["# {$reference} — version {$version}", ''];
        foreach ($termes as $t) {
            $lignes[] = "- `{$t['code']}` ({$t['type_semantique']}) — {$t['definition']}";
        }

        return implode("\n", $lignes);
    }

    /**
     * Compare les codes actifs d'une version de vocabulaire à ceux
     * réellement appliqués par une capacité consommatrice (constante PHP,
     * `CHECK` SQL). Ne remplace ni ne modifie l'enforcement existant.
     *
     * @param list<string> $codesVocabulaire
     * @param list<string> $codesReels
     * @return array{manquants:list<string>,superflus:list<string>}
     */
    public static function comparerCodes(array $codesVocabulaire, array $codesReels): array
    {
        sort($codesVocabulaire);
        sort($codesReels);

        return [
            'manquants' => array_values(array_diff($codesReels, $codesVocabulaire)),
            'superflus' => array_values(array_diff($codesVocabulaire, $codesReels)),
        ];
    }
}
