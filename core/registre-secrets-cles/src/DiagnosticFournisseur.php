<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/** Résultat borné d'une vérification de disponibilité — jamais de valeur. */
final class DiagnosticFournisseur
{
    public function __construct(
        public readonly bool $disponible,
        public readonly ?string $motif = null,
    ) {
    }
}
