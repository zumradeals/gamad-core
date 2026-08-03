<?php

declare(strict_types=1);

namespace Gamad\RegistreVocabulaire;

/**
 * Contrôles structurels appliqués aux définitions et codes déclarés.
 *
 * Ne tranche jamais ce que le code ne peut pas établir avec certitude :
 * l'ambiguïté d'un alias et les cycles de relation exigent l'état du
 * registre et restent portés par `RegistreVocabulaire` lui-même.
 */
final class ValidateurTerme
{
    private const MOTIFS_EXECUTABLE = [
        '/<\?php/i',
        '/\beval\s*\(/i',
        '/\bfunction\s*\(/i',
        '/\bexec\s*\(/i',
        '/;\s*DROP\s+TABLE/i',
    ];

    private const MOTIFS_SECRET = [
        '/mot_de_passe["\']?\s*[:=]\s*["\'](?!\*{3}|<)[^"\']{3,}/i',
        '/password["\']?\s*[:=]\s*["\'](?!\*{3}|<)[^"\']{3,}/i',
        '/secret["\']?\s*[:=]\s*["\'](?!\*{3}|<)[^"\']{3,}/i',
    ];

    /** @return array{valide:bool,motif?:string} */
    public static function validerDefinition(string $definition): array
    {
        if (trim($definition) === '') {
            return ['valide' => false, 'motif' => 'définition absente'];
        }
        if (self::contientExpressionExecutable($definition)) {
            return ['valide' => false, 'motif' => 'EXPRESSION_EXECUTABLE'];
        }
        if (self::contientSecret($definition)) {
            return ['valide' => false, 'motif' => 'SECRET_DETECTE'];
        }

        return ['valide' => true];
    }

    /** Un code canonique est une valeur machine stable : lettres, chiffres, underscore. */
    public static function codeValide(string $code): bool
    {
        return preg_match('/^[A-Z][A-Z0-9_]*$/', $code) === 1;
    }

    public static function contientExpressionExecutable(string $contenu): bool
    {
        foreach (self::MOTIFS_EXECUTABLE as $motif) {
            if (preg_match($motif, $contenu) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function contientSecret(string $contenu): bool
    {
        foreach (self::MOTIFS_SECRET as $motif) {
            if (preg_match($motif, $contenu) === 1) {
                return true;
            }
        }

        return false;
    }
}
