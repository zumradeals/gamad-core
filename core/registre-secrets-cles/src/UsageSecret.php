<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Contexte obligatoire d'une résolution interne (fiche partie 2 §14).
 *
 * Transporté jusqu'au fournisseur pour que l'opération bornée sache ce
 * qu'elle sert à faire, sans jamais transporter la valeur elle-même.
 */
final class UsageSecret
{
    public function __construct(
        public readonly string $modeUsage,
        public readonly string $consommateurReference,
        public readonly string $realmReference,
        public readonly string $environnementReference,
        public readonly string $finaliteReference,
        public readonly string $operationReference,
        public readonly string $correlationId,
    ) {
    }
}
