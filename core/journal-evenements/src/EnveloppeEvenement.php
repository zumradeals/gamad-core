<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

/**
 * Utilitaires partagés de canonicalisation et de chaînage de CAP-CORE-014.
 *
 * Le JSON canonique (clés triées, séparateurs stables) garantit qu'une même
 * charge produit toujours la même empreinte SHA-256, indépendamment de
 * l'ordre d'assemblage côté producteur. Le chaînage prouve la cohérence
 * interne du journal, pas l'identité cryptographique du producteur — comme
 * pour CAP-CORE-013, la capacité annonce explicitement `signee: false` tant
 * que CAP-CORE-015 et CAP-CORE-016 ne sont pas livrées.
 */
final class EnveloppeEvenement
{
    public const RACINE = '0000000000000000000000000000000000000000000000000000000000000000';

    /** Champs obligatoires d'une intention d'événement (avant acceptation centrale). */
    public const CHAMPS_OBLIGATOIRES = [
        'type_evenement', 'contrat_reference', 'contrat_version',
        'source_reference', 'realm_reference', 'finalite_reference',
        'correlation_id', 'survenu_le', 'classification', 'idempotence_reference',
    ];

    public static function jsonCanonique(mixed $valeur): string
    {
        return json_encode(
            self::trier($valeur),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    public static function empreinteCharge(mixed $charge): string
    {
        return hash('sha256', self::jsonCanonique($charge));
    }

    public static function empreinteChainee(?string $precedente, array $contenu): string
    {
        return hash('sha256', ($precedente ?? self::RACINE) . "\n" . self::jsonCanonique($contenu));
    }

    private static function trier(mixed $valeur): mixed
    {
        if (!is_array($valeur)) {
            return $valeur;
        }
        if (!array_is_list($valeur)) {
            ksort($valeur);
        }
        foreach ($valeur as $k => $v) {
            $valeur[$k] = self::trier($v);
        }

        return $valeur;
    }
}
