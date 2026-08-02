<?php

declare(strict_types=1);

namespace Gamad\RegistreContrats;

/**
 * Projections dérivées d'une version de contrat, et contrôle de dérive avec
 * `openapi/core-v1.yaml`.
 *
 * OpenAPI 3.1 admet une représentation JSON strictement équivalente à YAML ;
 * ce générateur produit donc un fragment JSON plutôt que d'introduire une
 * dépendance YAML absente du projet (`symfony/yaml` n'est pas installé ici).
 *
 * `extraireOperationsDuFichier()` ne remplace pas un analyseur YAML général :
 * il lit `openapi/core-v1.yaml` selon la convention d'indentation déjà en
 * vigueur dans ce fichier (chemin à 2 espaces, méthode à 4, `operationId` à
 * 6) pour détecter une dérive sans dépendance nouvelle. Une convention
 * d'indentation différente romprait cette lecture — c'est un choix
 * délibérément local à ce fichier, pas un analyseur YAML général.
 */
final class GenerateurOpenApi
{
    /**
     * @param list<array<string,mixed>> $operations
     * @param list<array<string,mixed>> $schemas
     */
    public static function genererFragmentJson(string $reference, string $version, array $operations, array $schemas): string
    {
        $paths = [];
        foreach ($operations as $operation) {
            if ($operation['methode_http'] === null || $operation['chemin_http'] === null) {
                continue;
            }
            $chemin = (string) $operation['chemin_http'];
            $methode = strtolower((string) $operation['methode_http']);
            $paths[$chemin] ??= [];
            $paths[$chemin][$methode] = [
                'operationId' => $operation['reference_operation'],
                'x-contrat' => $reference,
                'x-contrat-version' => $version,
                'x-audit-obligatoire' => (bool) $operation['audit_obligatoire'],
                'x-idempotente' => (bool) $operation['idempotente'],
            ];
        }

        return json_encode(['openapi' => '3.1.0', 'paths' => $paths], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** @param list<array<string,mixed>> $operations */
    public static function genererInterfacePhp(string $reference, array $operations): string
    {
        $lignes = ["interface {$reference} {"];
        foreach ($operations as $operation) {
            $lignes[] = "    public function {$operation['reference_operation']}(): mixed;";
        }
        $lignes[] = '}';

        return implode("\n", $lignes);
    }

    /** @param list<array<string,mixed>> $operations */
    public static function genererDocumentation(string $reference, string $version, array $operations): string
    {
        $lignes = ["# {$reference} — version {$version}", ''];
        foreach ($operations as $operation) {
            $lignes[] = "- `{$operation['reference_operation']}` ({$operation['type_operation']})";
        }

        return implode("\n", $lignes);
    }

    /**
     * Extrait les triplets {methode, chemin, operationId} déclarés dans
     * `openapi/core-v1.yaml`, selon la convention d'indentation du fichier.
     *
     * @return list<array{methode:string,chemin:string,operationId:string}>
     */
    public static function extraireOperationsDuFichier(string $chemin): array
    {
        if (!is_file($chemin)) {
            return [];
        }
        $lignes = file($chemin, FILE_IGNORE_NEW_LINES) ?: [];
        $operations = [];
        $cheminCourant = null;
        $methodeCourante = null;
        $methodesHttp = ['get', 'post', 'put', 'patch', 'delete'];

        foreach ($lignes as $ligne) {
            if (preg_match('/^  (\/\S+):\s*$/', $ligne, $m) === 1) {
                $cheminCourant = $m[1];
                $methodeCourante = null;

                continue;
            }
            if ($cheminCourant === null) {
                continue;
            }
            if (preg_match('/^    (\w+):\s*$/', $ligne, $m) === 1 && in_array(strtolower($m[1]), $methodesHttp, true)) {
                $methodeCourante = strtolower($m[1]);

                continue;
            }
            if ($methodeCourante !== null && preg_match('/^      operationId:\s*(\S+)\s*$/', $ligne, $m) === 1) {
                $operations[] = ['methode' => strtoupper($methodeCourante), 'chemin' => $cheminCourant, 'operationId' => $m[1]];
            }
        }

        return $operations;
    }

    /**
     * Compare les opérations attendues (portées par le registre) à celles
     * réellement présentes dans `openapi/core-v1.yaml`.
     *
     * @param list<array{methode:string,chemin:string,operationId:string}> $attendues
     * @param list<array{methode:string,chemin:string,operationId:string}> $fichier
     * @return array{manquantes:list<string>,fantomes:list<string>,doublons:list<string>}
     */
    public static function comparer(array $attendues, array $fichier): array
    {
        $cleAttendues = array_map(static fn (array $o): string => "{$o['methode']} {$o['chemin']} {$o['operationId']}", $attendues);
        $cleFichier = array_map(static fn (array $o): string => "{$o['methode']} {$o['chemin']} {$o['operationId']}", $fichier);

        $operationIds = array_map(static fn (array $o): string => $o['operationId'], $fichier);
        $doublons = array_values(array_unique(array_diff_assoc($operationIds, array_unique($operationIds))));

        return [
            'manquantes' => array_values(array_diff($cleAttendues, $cleFichier)),
            'fantomes' => array_values(array_diff($cleFichier, $cleAttendues)),
            'doublons' => $doublons,
        ];
    }
}
