<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

/**
 * Empreinte Git d'un contenu, calculée sans le binaire `git`.
 *
 * `git hash-object` calcule sha1("blob " . taille . "\0" . contenu). Reproduire
 * ce calcul en PHP rend le service portable (Railway n'a pas nécessairement le
 * binaire git) tout en restant identique, octet pour octet, aux empreintes
 * déclarées dans les actes d'adoption. Sert l'invariant INV-1.
 */
final class GitBlob
{
    public static function hashContent(string $contenu): string
    {
        return sha1('blob ' . strlen($contenu) . "\0" . $contenu);
    }

    public static function hashFile(string $chemin): string
    {
        $contenu = @file_get_contents($chemin);
        if ($contenu === false) {
            throw new \RuntimeException("Lecture impossible : {$chemin}");
        }

        return self::hashContent($contenu);
    }
}
