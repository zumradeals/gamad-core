<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Applique les exceptions d'intégrité réservées aux fichiers de conduite
 * opérationnelle. Une configuration absente ou invalide ferme par défaut :
 * aucune divergence n'est neutralisée.
 */
final class CheminsOperationnels
{
    /**
     * @return array<string,string>
     */
    public static function charger(string $racine): array
    {
        $fichier = rtrim($racine, '/').'/config/integrite-operationnelle.json';
        if (! is_file($fichier)) {
            return [];
        }

        try {
            $contenu = file_get_contents($fichier);
            if (! is_string($contenu)) {
                return [];
            }
            $donnees = json_decode($contenu, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        $chemins = $donnees['paths'] ?? null;
        if (! is_array($chemins)) {
            return [];
        }

        $resultat = [];
        foreach ($chemins as $chemin => $motif) {
            if (is_string($chemin) && $chemin !== '' && is_string($motif)) {
                $resultat[$chemin] = $motif;
            }
        }

        return $resultat;
    }

    /**
     * @param  list<array<string,mixed>>  $lignes
     * @return list<array<string,mixed>>
     */
    public static function appliquer(array $lignes, string $racine): array
    {
        $decouples = self::charger($racine);

        return array_map(
            static function (array $ligne) use ($decouples): array {
                $chemin = (string) ($ligne['chemin'] ?? '');
                if (! array_key_exists($chemin, $decouples)) {
                    return $ligne;
                }

                $present = (bool) ($ligne['fichier_present'] ?? false);
                $ligne['concorde'] = $present;
                $ligne['operationnel_decouple'] = true;
                $ligne['motif_decouplage'] = $decouples[$chemin];

                return $ligne;
            },
            $lignes,
        );
    }
}
