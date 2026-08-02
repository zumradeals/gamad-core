<?php

declare(strict_types=1);

namespace Gamad\RegistreContrats;

/**
 * Analyse structurelle de compatibilité entre deux jeux d'opérations,
 * schémas, erreurs et consommateurs déjà persistés par le registre.
 *
 * L'analyse ne lit jamais de code PHP ni de route Laravel : elle compare des
 * enregistrements déjà stockés, liés aux empreintes exactes des versions
 * concernées (`RegistreContrats::analyserCompatibilite` ne l'invoque que sur
 * une version dont le contenu est déjà figé). Absence de version précédente
 * (première version d'un contrat) : toujours `COMPATIBLE`, rien à rompre.
 */
final class AnalyseurCompatibilite
{
    /**
     * @param list<array<string,mixed>> $operationsAvant
     * @param list<array<string,mixed>> $operationsApres
     * @param list<array<string,mixed>> $schemasAvant
     * @param list<array<string,mixed>> $schemasApres
     * @param list<array<string,mixed>> $erreursAvant
     * @param list<array<string,mixed>> $erreursApres
     * @param list<array<string,mixed>> $consommateursAvant
     * @param list<array<string,mixed>> $consommateursApres
     * @return array{resultat:string,divergences:list<array<string,mixed>>}
     */
    public static function analyser(
        array $operationsAvant,
        array $operationsApres,
        array $schemasAvant,
        array $schemasApres,
        array $erreursAvant,
        array $erreursApres,
        array $consommateursAvant,
        array $consommateursApres,
    ): array {
        if ($operationsAvant === [] && $schemasAvant === [] && $erreursAvant === [] && $consommateursAvant === []) {
            return ['resultat' => 'COMPATIBLE', 'divergences' => []];
        }

        $divergences = [
            ...self::comparerOperations($operationsAvant, $operationsApres),
            ...self::comparerSchemas($schemasAvant, $schemasApres),
            ...self::comparerErreurs($erreursAvant, $erreursApres),
            ...self::comparerConsommateurs($consommateursAvant, $consommateursApres),
        ];

        $gravites = array_column($divergences, 'gravite');
        $resultat = match (true) {
            in_array('RUPTURE', $gravites, true) => 'RUPTURE',
            in_array('ADAPTATION_REQUISE', $gravites, true) => 'ADAPTATION_REQUISE',
            default => 'COMPATIBLE',
        };

        return ['resultat' => $resultat, 'divergences' => $divergences];
    }

    /** @return list<array<string,mixed>> */
    private static function comparerOperations(array $avant, array $apres): array
    {
        $divergences = [];
        $parRefAvant = self::indexer($avant, 'reference_operation');
        $parRefApres = self::indexer($apres, 'reference_operation');

        foreach ($parRefAvant as $ref => $ancienne) {
            if (!isset($parRefApres[$ref])) {
                $divergences[] = self::divergence('RUPTURE', 'operation_supprimee', $ref, "l'opération `{$ref}` a disparu");

                continue;
            }
            $nouvelle = $parRefApres[$ref];
            if ((string) $ancienne['methode_http'] !== (string) $nouvelle['methode_http']) {
                $divergences[] = self::divergence('RUPTURE', 'methode_modifiee', $ref, 'méthode HTTP modifiée');
            }
            if ((string) $ancienne['chemin_http'] !== (string) $nouvelle['chemin_http']) {
                $divergences[] = self::divergence('RUPTURE', 'chemin_modifie', $ref, 'chemin HTTP modifié');
            }
            if ((string) $ancienne['action_autorisation'] !== (string) $nouvelle['action_autorisation']) {
                $divergences[] = self::divergence('ADAPTATION_REQUISE', 'autorisation_renforcee', $ref, 'action d’autorisation modifiée — vérifier le sens du changement');
            }
            $dureeAvant = $ancienne['duree_secondes'] === null ? null : (int) $ancienne['duree_secondes'];
            $dureeApres = $nouvelle['duree_secondes'] === null ? null : (int) $nouvelle['duree_secondes'];
            if ($dureeAvant !== null && $dureeApres !== null && $dureeApres < $dureeAvant) {
                $divergences[] = self::divergence('ADAPTATION_REQUISE', 'duree_reduite', $ref, "durée réduite de {$dureeAvant}s à {$dureeApres}s");
            }
            if ((int) $ancienne['idempotente'] === 1 && (int) $nouvelle['idempotente'] === 0) {
                $divergences[] = self::divergence('RUPTURE', 'idempotence_perdue', $ref, 'opération devenue non idempotente');
            }
        }

        return $divergences;
    }

    /** @return list<array<string,mixed>> */
    private static function comparerSchemas(array $avant, array $apres): array
    {
        $divergences = [];
        $parCleAvant = self::indexerSchemas($avant);
        $parCleApres = self::indexerSchemas($apres);

        foreach ($parCleAvant as $cle => $ancien) {
            $nouveau = $parCleApres[$cle] ?? null;
            if ($nouveau === null) {
                continue;
            }
            if ($ancien['format'] !== 'JSON_SCHEMA' || $nouveau['format'] !== 'JSON_SCHEMA') {
                if ($ancien['empreinte'] !== $nouveau['empreinte']) {
                    $divergences[] = self::divergence('ADAPTATION_REQUISE', 'schema_modifie', $cle, 'contenu du schéma modifié (format non structuré, analyse fine impossible)');
                }

                continue;
            }
            $proprietesAvant = ValidateurContrat::proprietes('JSON_SCHEMA', $ancien['contenu']);
            $proprietesApres = ValidateurContrat::proprietes('JSON_SCHEMA', $nouveau['contenu']);

            foreach ($proprietesApres as $nom => $definition) {
                if (!isset($proprietesAvant[$nom]) && $definition['requis']) {
                    $divergences[] = self::divergence('RUPTURE', 'champ_obligatoire_ajoute', "{$cle}.{$nom}", 'nouveau champ obligatoire');
                }
            }
            foreach ($proprietesAvant as $nom => $definition) {
                if (!isset($proprietesApres[$nom])) {
                    $divergences[] = self::divergence('RUPTURE', 'champ_supprime', "{$cle}.{$nom}", 'champ supprimé');

                    continue;
                }
                $nouvelleDefinition = $proprietesApres[$nom];
                if ($definition['type'] !== null && $nouvelleDefinition['type'] !== null && $definition['type'] !== $nouvelleDefinition['type']) {
                    $divergences[] = self::divergence('RUPTURE', 'type_modifie', "{$cle}.{$nom}", "type modifié de `{$definition['type']}` à `{$nouvelleDefinition['type']}`");
                }
                if ($definition['enum'] !== null && $nouvelleDefinition['enum'] !== null) {
                    $perdues = array_diff($definition['enum'], $nouvelleDefinition['enum']);
                    if ($perdues !== []) {
                        $divergences[] = self::divergence('RUPTURE', 'enum_reduit', "{$cle}.{$nom}", 'valeur(s) d’énumération retirée(s) : ' . implode(',', $perdues));
                    }
                }
            }
        }

        return $divergences;
    }

    /** @return list<array<string,mixed>> */
    private static function comparerErreurs(array $avant, array $apres): array
    {
        $divergences = [];
        $parCodeAvant = self::indexer($avant, 'code');
        $parCodeApres = self::indexer($apres, 'code');

        foreach ($parCodeAvant as $code => $ancienne) {
            if (!isset($parCodeApres[$code])) {
                $divergences[] = self::divergence('RUPTURE', 'erreur_supprimee', $code, "le code d'erreur `{$code}` a disparu");

                continue;
            }
            $nouvelle = $parCodeApres[$code];
            if ((int) $ancienne['retentable'] === 1 && (int) $nouvelle['retentable'] === 0) {
                $divergences[] = self::divergence('ADAPTATION_REQUISE', 'retentabilite_modifiee', $code, 'erreur devenue non retentable');
            }
        }

        return $divergences;
    }

    /** @return list<array<string,mixed>> */
    private static function comparerConsommateurs(array $avant, array $apres): array
    {
        $divergences = [];
        $referencesAvant = array_map(static fn (array $p): string => (string) $p['partie_reference'], $avant);
        $referencesApres = array_map(static fn (array $p): string => (string) $p['partie_reference'], $apres);

        foreach (array_diff($referencesAvant, $referencesApres) as $reference) {
            $divergences[] = self::divergence('RUPTURE', 'consommateur_retire', $reference, "le consommateur `{$reference}` n'est plus déclaré");
        }

        return $divergences;
    }

    /** @param list<array<string,mixed>> $lignes @return array<string,array<string,mixed>> */
    private static function indexer(array $lignes, string $cle): array
    {
        $index = [];
        foreach ($lignes as $ligne) {
            $index[(string) $ligne[$cle]] = $ligne;
        }

        return $index;
    }

    /** @param list<array<string,mixed>> $schemas @return array<string,array<string,mixed>> */
    private static function indexerSchemas(array $schemas): array
    {
        $index = [];
        foreach ($schemas as $schema) {
            $cle = ($schema['operation_reference'] ?? '(contrat)') . ':' . $schema['sens'];
            $index[$cle] = $schema;
        }

        return $index;
    }

    /** @return array<string,mixed> */
    private static function divergence(string $gravite, string $type, string $cible, string $detail): array
    {
        return ['gravite' => $gravite, 'type' => $type, 'cible' => $cible, 'detail' => $detail];
    }
}
