<?php

declare(strict_types=1);

namespace Gamad\RegistreOrganisations;

/**
 * Contrôles d'acyclicité structurelle (fiche §24, §32).
 *
 * Une unité ne doit jamais devenir sa propre ascendante ; une relation
 * hiérarchique entre organisations ne doit jamais boucler. Ce module ne
 * décide rien d'autre : il répond seulement « cette structure formerait-elle
 * un cycle ? ».
 */
final class ValidateurStructure
{
    /**
     * Ajouter `$candidate` comme parente de `$unite` créerait-il un cycle ?
     * `$parents` associe chaque référence d'unité à sa référence parente
     * actuelle (ou `null`).
     *
     * @param array<string,?string> $parents
     */
    public static function uniteCreeraitCycle(
        string $unite,
        string $candidateParente,
        array $parents,
    ): bool {
        if ($unite === $candidateParente) {
            return true;
        }
        $courant = $candidateParente;
        $vus = [];
        while ($courant !== null) {
            if ($courant === $unite) {
                return true;
            }
            if (isset($vus[$courant])) {
                // Cycle préexistant détecté ailleurs : ne pas boucler indéfiniment.
                return true;
            }
            $vus[$courant] = true;
            $courant = $parents[$courant] ?? null;
        }

        return false;
    }

    /**
     * Ajouter une relation hiérarchique `$source -> $cible` créerait-il un
     * cycle ? `$aretes` liste les relations hiérarchiques déjà déclarées,
     * sous la forme `[[source, cible], ...]`.
     *
     * @param list<array{0:string,1:string}> $aretes
     */
    public static function relationCreeraitCycle(
        string $source,
        string $cible,
        array $aretes,
    ): bool {
        if ($source === $cible) {
            return true;
        }
        // Un cycle existerait si, en partant de $cible, on peut atteindre
        // $source en suivant les arêtes déjà déclarées plus celle proposée.
        $graphe = [];
        foreach ($aretes as [$s, $c]) {
            $graphe[$s][] = $c;
        }
        $graphe[$source][] = $cible;

        $pile = [$cible];
        $vus = [];
        while ($pile !== []) {
            $noeud = array_pop($pile);
            if ($noeud === $source) {
                return true;
            }
            if (isset($vus[$noeud])) {
                continue;
            }
            $vus[$noeud] = true;
            foreach ($graphe[$noeud] ?? [] as $suivant) {
                $pile[] = $suivant;
            }
        }

        return false;
    }
}
