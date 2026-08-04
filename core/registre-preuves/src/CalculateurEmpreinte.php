<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

/**
 * Calcul d'empreinte borné (CAP-CORE-015, fiche partie 2 §5, §9 et
 * partie 3 §4). SHA-256 et SHA-512 seulement ; MD5 et SHA-1 sont refusés
 * pour toute nouvelle preuve. Le calcul en flux évite de charger un gros
 * artefact entièrement en mémoire.
 */
final class CalculateurEmpreinte
{
    public const VERSION = 1;

    private const CORRESPONDANCE_ALGORITHME = [
        'SHA-256' => 'sha256',
        'SHA-512' => 'sha512',
    ];

    public static function empreinteChaine(string $contenu, string $algorithme): string
    {
        self::verifierAlgorithme($algorithme);

        return hash(self::CORRESPONDANCE_ALGORITHME[$algorithme], $contenu);
    }

    /** Calcul en flux : n'exige jamais de charger tout le fichier en mémoire. */
    public static function empreinteFlux(string $cheminAbsolu, string $algorithme): array
    {
        self::verifierAlgorithme($algorithme);
        if ($cheminAbsolu === '' || $cheminAbsolu[0] !== '/') {
            throw new ExceptionPreuve('chemin non absolu refusé');
        }
        if (str_contains($cheminAbsolu, '..')) {
            throw new ExceptionPreuve('path traversal refusé');
        }
        if (is_link($cheminAbsolu)) {
            throw new ExceptionPreuve('lien symbolique refusé');
        }
        if (!is_file($cheminAbsolu)) {
            throw new ExceptionPreuve('artefact absent');
        }

        $contexte = hash_init(self::CORRESPONDANCE_ALGORITHME[$algorithme]);
        $flux = fopen($cheminAbsolu, 'rb');
        if ($flux === false) {
            throw new ExceptionPreuve('lecture du flux impossible');
        }
        hash_update_stream($contexte, $flux);
        fclose($flux);

        return [
            'empreinte_hex' => hash_final($contexte),
            'taille_octets' => (int) filesize($cheminAbsolu),
        ];
    }

    public static function longueurAttendueBits(string $algorithme): int
    {
        return match ($algorithme) {
            'SHA-256' => 256,
            'SHA-512' => 512,
            default => throw new ExceptionPreuve("algorithme d'empreinte non supporté : {$algorithme}"),
        };
    }

    public static function empreinteValide(string $empreinteHex, string $algorithme): bool
    {
        $longueurAttendue = self::longueurAttendueBits($algorithme) / 4;

        return preg_match('/^[0-9a-f]+$/', $empreinteHex) === 1
            && strlen($empreinteHex) === $longueurAttendue;
    }

    public static function comparerConstant(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    private static function verifierAlgorithme(string $algorithme): void
    {
        if (in_array($algorithme, PolitiquePreuves::ALGORITHMES_EMPREINTE_REFUSES, true)) {
            throw new ExceptionPreuve("algorithme refusé pour toute nouvelle preuve : {$algorithme}");
        }
        if (!isset(self::CORRESPONDANCE_ALGORITHME[$algorithme])) {
            throw new ExceptionPreuve("algorithme d'empreinte non supporté : {$algorithme}");
        }
    }
}
