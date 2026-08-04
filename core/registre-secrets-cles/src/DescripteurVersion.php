<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Désigne une version d'un secret sans jamais transporter sa valeur.
 *
 * `$handle` porte la référence opaque déclarée par le registre
 * (`secret_version.handle_fournisseur` — un chemin, un nom de credential
 * systemd, une empreinte GPG…) : chaque fournisseur sait comment
 * l'interpréter, aucun ne peut en déduire le matériel secret lui-même.
 */
final class DescripteurVersion
{
    public function __construct(
        public readonly string $secretReference,
        public readonly string $version,
        public readonly ?string $handle = null,
    ) {
    }
}
