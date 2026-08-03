<?php

declare(strict_types=1);

namespace Gamad\RegistreVocabulaire;

/**
 * Analyse structurelle de compatibilité entre les termes d'une version de
 * vocabulaire et ceux de la version active courante.
 *
 * Les termes sont comparés par leur référence stable (`terme.reference`),
 * pas par leur code : un changement de code pour la même référence est
 * précisément ce qui doit être détecté comme une rupture. Absence de
 * version précédente (première version d'un vocabulaire) : toujours
 * `COMPATIBLE`, rien à rompre.
 *
 * `terme.reference` est une clé primaire globale — une ligne ne change
 * jamais de version. Faire évoluer un terme (`RegistreVocabulaire::
 * evoluerTerme()`) crée donc toujours une nouvelle ligne sous une nouvelle
 * référence, reliée à l'ancienne par `remplace_par_reference`. Quand une
 * référence de la version précédente n'a pas de jumeau direct dans la
 * nouvelle, cette lignée est le second chemin de correspondance : sans elle,
 * toute évolution légitime se lirait à tort comme une suppression pure.
 */
final class AnalyseurCompatibilite
{
    /**
     * @param list<array<string,mixed>> $termesAvant
     * @param list<array<string,mixed>> $termesApres
     * @return array{resultat:string,divergences:list<array<string,mixed>>}
     */
    public static function analyser(array $termesAvant, array $termesApres): array
    {
        if ($termesAvant === []) {
            return ['resultat' => 'COMPATIBLE', 'divergences' => []];
        }

        $parRefAvant = self::indexer($termesAvant);
        $parRefApres = self::indexer($termesApres);
        $divergences = [];
        $successeursReconnus = [];

        foreach ($parRefAvant as $reference => $ancien) {
            $successeur = (string) ($ancien['remplace_par_reference'] ?? '');
            $viaLignee = false;
            $nouveau = $parRefApres[$reference] ?? null;
            if ($nouveau === null && $successeur !== '' && isset($parRefApres[$successeur])) {
                $nouveau = $parRefApres[$successeur];
                $viaLignee = true;
            }
            if ($nouveau === null) {
                $divergences[] = self::divergence('RUPTURE', 'terme_supprime', $reference, "le terme `{$reference}` a disparu");

                continue;
            }
            if ($viaLignee) {
                $successeursReconnus[$successeur] = true;
            }
            if ((string) $ancien['code'] !== (string) $nouveau['code']) {
                $divergences[] = self::divergence(
                    'RUPTURE', 'code_modifie', $reference,
                    "code modifié de `{$ancien['code']}` à `{$nouveau['code']}`",
                );

                continue;
            }
            if ((string) $ancien['type_semantique'] !== (string) $nouveau['type_semantique']) {
                $divergences[] = self::divergence('RUPTURE', 'type_semantique_modifie', $reference, 'type sémantique modifié');

                continue;
            }
            if ((string) $ancien['definition'] !== (string) $nouveau['definition']) {
                $divergences[] = self::divergence('ADAPTATION_REQUISE', 'definition_modifiee', $reference, 'définition modifiée');
            }
        }

        foreach ($parRefApres as $reference => $nouveau) {
            if (isset($parRefAvant[$reference]) || isset($successeursReconnus[$reference])) {
                continue;
            }
            $divergences[] = self::divergence('ADAPTATION_REQUISE', 'terme_ajoute', $reference, "nouveau terme `{$nouveau['code']}`");
        }

        $gravites = array_column($divergences, 'gravite');
        $resultat = match (true) {
            in_array('RUPTURE', $gravites, true) => 'RUPTURE',
            in_array('ADAPTATION_REQUISE', $gravites, true) => 'ADAPTATION_REQUISE',
            default => 'COMPATIBLE',
        };

        return ['resultat' => $resultat, 'divergences' => $divergences];
    }

    /** @param list<array<string,mixed>> $termes @return array<string,array<string,mixed>> */
    private static function indexer(array $termes): array
    {
        $index = [];
        foreach ($termes as $terme) {
            $index[(string) $terme['reference']] = $terme;
        }

        return $index;
    }

    /** @return array<string,mixed> */
    private static function divergence(string $gravite, string $type, string $cible, string $detail): array
    {
        return ['gravite' => $gravite, 'type' => $type, 'cible' => $cible, 'detail' => $detail];
    }
}
