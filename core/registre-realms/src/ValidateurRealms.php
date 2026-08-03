<?php

declare(strict_types=1);

namespace Gamad\RegistreRealms;

/**
 * Contrôle d'acyclicité structurelle de la hiérarchie des realms (fiche §16,
 * §25, §60).
 *
 * Un realm ne doit jamais devenir son propre ascendant. Ce module ne décide
 * rien d'autre : il répond seulement « cette relation `PARENT_DE` créerait-
 * elle un cycle ? ». La détection doit s'exécuter dans la même transaction
 * que l'ajout de la relation (fiche §60).
 */
final class ValidateurRealms
{
    /**
     * Ajouter une relation hiérarchique `$source PARENT_DE $cible`
     * créerait-elle un cycle ? `$aretes` liste les relations `PARENT_DE`
     * déjà déclarées et actives, sous la forme `[[source, cible], ...]`.
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

    /**
     * Diagnostic non destructif : la hiérarchie actuellement déclarée
     * (`$aretes`) contient-elle un cycle ? Utilisé par la readiness (fiche
     * §62) pour détecter une incohérence apparue par un autre chemin que
     * cette classe (import, restauration).
     *
     * @param list<array{0:string,1:string}> $aretes
     * @return list<string> les nœuds identifiés comme faisant partie d'un cycle
     */
    public static function detecterCycles(array $aretes): array
    {
        $graphe = [];
        foreach ($aretes as [$s, $c]) {
            $graphe[$s][] = $c;
        }

        $cycles = [];
        foreach (array_keys($graphe) as $depart) {
            $vus = [];
            $pile = $graphe[$depart];
            while ($pile !== []) {
                $noeud = array_pop($pile);
                if ($noeud === $depart) {
                    $cycles[] = $depart;

                    break;
                }
                if (isset($vus[$noeud])) {
                    continue;
                }
                $vus[$noeud] = true;
                foreach ($graphe[$noeud] ?? [] as $suivant) {
                    $pile[] = $suivant;
                }
            }
        }

        return array_values(array_unique($cycles));
    }
}
