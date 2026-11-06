<?php

declare(strict_types=1);

namespace App\Support\Unification;

use InvalidArgumentException;

/**
 * Contrat de rapprochement hors base entre un UUID historique Réseau
 * et une référence du registre Core. Ne crée ni identité ni autorisation.
 */
final class RapprochementIdentites
{
    /**
     * @param list<array{reseau_user_id: string, core_reference: string}> $liens
     * @return array{valides: list<array{reseau_user_id: string, core_reference: string}>, conflits: list<string>}
     */
    public static function verifier(array $liens): array
    {
        $valides = [];
        $conflits = [];
        $parReseau = [];
        $parCore = [];

        foreach ($liens as $index => $lien) {
            $uuid = $lien['reseau_user_id'] ?? null;
            $reference = $lien['core_reference'] ?? null;
            if (! is_string($uuid) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)
                || ! is_string($reference) || trim($reference) === '' || trim($reference) !== $reference) {
                $conflits[] = 'Ligne '.($index + 1).' : identifiants invalides';
                continue;
            }

            $uuid = strtolower($uuid);
            if (isset($parReseau[$uuid])) {
                $conflits[] = 'Ligne '.($index + 1).' : UUID Réseau déjà rapproché';
                continue;
            }
            if (isset($parCore[$reference])) {
                $conflits[] = 'Ligne '.($index + 1).' : référence Core déjà rapprochée';
                continue;
            }

            $parReseau[$uuid] = true;
            $parCore[$reference] = true;
            $valides[] = ['reseau_user_id' => $uuid, 'core_reference' => $reference];
        }

        return ['valides' => $valides, 'conflits' => $conflits];
    }

    /**
     * Aucun rapprochement partiel ne peut être considéré comme applicable.
     * @param list<array{reseau_user_id: string, core_reference: string}> $liens
     * @return list<array{reseau_user_id: string, core_reference: string}>
     */
    public static function exigerSansConflit(array $liens): array
    {
        $resultat = self::verifier($liens);
        if ($resultat['conflits'] !== []) {
            throw new InvalidArgumentException(implode('; ', $resultat['conflits']));
        }

        return $resultat['valides'];
    }
}
