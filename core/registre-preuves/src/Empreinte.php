<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

/**
 * Calcul d'empreintes par algorithme nommé (CAP-CORE-015, INV-31).
 *
 * Une empreinte n'est une preuve qu'accompagnée de l'algorithme qui la produit
 * ET de la convention d'encodage de l'objet. Pour Git, cette convention est
 * l'en-tête `blob · taille · NUL` précédant le contenu : l'omettre produit une
 * valeur qui ne correspond à aucune déclaration du corpus.
 *
 * Cette classe ne connaît aucun secret et n'en manipule aucun. Elle ne signe
 * rien : elle calcule (ADOPTION-0025, Art. 3.a).
 */
final class Empreinte
{
    /** Algorithme sous-entendu par les déclarations nues du corpus (INV-31). */
    public const ALGORITHME_HISTORIQUE = 'git-sha1';

    /**
     * Calcule l'empreinte d'un contenu par l'algorithme nommé.
     *
     * @throws \InvalidArgumentException si l'algorithme est inconnu — le
     *         service refuse de deviner plutôt que de rendre une valeur qui
     *         ressemblerait à une preuve.
     */
    public static function calculer(string $algorithme, string $contenu): string
    {
        return match ($algorithme) {
            'git-sha1' => sha1('blob ' . strlen($contenu) . "\0" . $contenu),
            'sha256'   => hash('sha256', $contenu),
            default    => throw new \InvalidArgumentException(
                "Algorithme inconnu : {$algorithme}. Aucune empreinte n'est rendue pour un algorithme non déclaré."
            ),
        };
    }

    /**
     * Calcule l'empreinte d'un fichier par l'algorithme nommé.
     *
     * Le contenu est relu depuis le disque à chaque appel : c'est INV-35. Une
     * valeur mise en cache serait la copie d'un constat ancien, non un constat.
     */
    public static function calculerFichier(string $algorithme, string $chemin): string
    {
        $contenu = @file_get_contents($chemin);
        if ($contenu === false) {
            throw new \RuntimeException("Lecture impossible : {$chemin}");
        }

        return self::calculer($algorithme, $contenu);
    }

    /**
     * Longueur hexadécimale attendue, utilisée pour interpréter une déclaration
     * nue. Sert INV-31 : reconnaître un algorithme plutôt que le présumer.
     */
    public static function algorithmeProbable(string $valeur): ?string
    {
        return match (strlen($valeur)) {
            40      => 'git-sha1',
            64      => 'sha256',
            default => null,
        };
    }
}
