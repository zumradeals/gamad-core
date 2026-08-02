<?php

declare(strict_types=1);

namespace Gamad\RegistreContrats;

/**
 * Contrôles structurels appliqués aux schémas et obligations déclarés.
 *
 * Ce validateur ne compile pas une spécification JSON Schema complète : il
 * vérifie que le contenu déclaré est structurellement exploitable
 * (`JSON_SCHEMA` doit être un objet JSON valide avec des propriétés
 * nommées), et refuse ce qui ressemble à un secret réel ou à une expression
 * exécutable — jamais ce que le code ne peut pas trancher avec certitude.
 *
 * Convention de représentation retenue pour `JSON_SCHEMA` dans ce registre :
 * `{"proprietes": {"champ": {"type": "string", "requis": true, "enum": [...]}}}`.
 * Ce n'est pas la spécification JSON Schema complète (draft 2020-12) ; c'est
 * un sous-ensemble suffisant pour que `AnalyseurCompatibilite` détecte les
 * ruptures listées dans la fiche de codage sans dépendre d'une bibliothèque
 * absente du projet.
 */
final class ValidateurContrat
{
    private const MOTIFS_SECRET = [
        '/mot_de_passe["\']?\s*[:=]\s*["\'](?!\*{3}|<)[^"\']{3,}/i',
        '/password["\']?\s*[:=]\s*["\'](?!\*{3}|<)[^"\']{3,}/i',
        '/secret["\']?\s*[:=]\s*["\'](?!\*{3}|<)[^"\']{3,}/i',
        '/api[_-]?key["\']?\s*[:=]\s*["\'](?!\*{3}|<)[^"\']{6,}/i',
        '/\bsk-[A-Za-z0-9]{16,}\b/',
        '/\bghp_[A-Za-z0-9]{16,}\b/',
        '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/', // JWT
    ];

    private const MOTIFS_EXECUTABLE = [
        '/<\?php/i',
        '/\beval\s*\(/i',
        '/\bfunction\s*\(/i',
        '/\bexec\s*\(/i',
        '/;\s*DROP\s+TABLE/i',
    ];

    /** @return array{valide:bool,motif?:string} */
    public static function validerSchema(string $format, ?string $contenu): array
    {
        if ($format === 'AUCUN_CORPS') {
            return ['valide' => true];
        }
        if ($contenu === null || trim($contenu) === '') {
            return ['valide' => false, 'motif' => 'contenu absent pour un schéma qui en exige un'];
        }
        if (self::contientSecret($contenu)) {
            return ['valide' => false, 'motif' => 'SECRET_DETECTE'];
        }
        if ($format === 'JSON_SCHEMA' || $format === 'OPENAPI_SCHEMA') {
            $decode = json_decode($contenu, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decode)) {
                return ['valide' => false, 'motif' => 'contenu non JSON valide pour un schéma structuré'];
            }
            if ($format === 'JSON_SCHEMA' && isset($decode['proprietes']) && !is_array($decode['proprietes'])) {
                return ['valide' => false, 'motif' => '`proprietes` doit être un objet'];
            }
        }

        return ['valide' => true];
    }

    /** @return array{valide:bool,motif?:string} */
    public static function validerObligation(string $description): array
    {
        if (trim($description) === '') {
            return ['valide' => false, 'motif' => 'description absente'];
        }
        if (self::contientExpressionExecutable($description)) {
            return ['valide' => false, 'motif' => 'EXPRESSION_EXECUTABLE'];
        }

        return ['valide' => true];
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

    public static function contientExpressionExecutable(string $contenu): bool
    {
        foreach (self::MOTIFS_EXECUTABLE as $motif) {
            if (preg_match($motif, $contenu) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Propriétés déclarées d'un schéma `JSON_SCHEMA`, selon la convention du
     * registre. Retourne un tableau vide pour tout autre format ou contenu
     * non conforme — jamais une exception : un diagnostic ne panne pas.
     *
     * @return array<string,array{type:?string,requis:bool,enum:?list<mixed>}>
     */
    public static function proprietes(string $format, ?string $contenu): array
    {
        if ($format !== 'JSON_SCHEMA' || $contenu === null) {
            return [];
        }
        $decode = json_decode($contenu, true);
        if (!is_array($decode) || !isset($decode['proprietes']) || !is_array($decode['proprietes'])) {
            return [];
        }
        $proprietes = [];
        foreach ($decode['proprietes'] as $nom => $definition) {
            if (!is_array($definition)) {
                continue;
            }
            $proprietes[(string) $nom] = [
                'type' => isset($definition['type']) ? (string) $definition['type'] : null,
                'requis' => (bool) ($definition['requis'] ?? false),
                'enum' => isset($definition['enum']) && is_array($definition['enum']) ? array_values($definition['enum']) : null,
            ];
        }

        return $proprietes;
    }
}
