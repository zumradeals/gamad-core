<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/** Résultat borné d'une destruction de matériel — jamais de valeur. */
final class ResultatDestruction
{
    public function __construct(
        public readonly bool $reussie,
        public readonly ?string $motif = null,
    ) {
    }
}
