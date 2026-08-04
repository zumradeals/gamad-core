<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Résout l'adaptateur borné correspondant à un type de fournisseur déclaré.
 *
 * Centralise ce qui serait sinon dupliqué entre la couche HTTP
 * (`AccesSecrets`) et la CLI d'exploitation — les deux doivent instancier
 * exactement le même adaptateur pour un même type de fournisseur, jamais des
 * variantes divergentes.
 */
final class AdaptateurParType
{
    public static function resoudre(string $typeFournisseur): ?FournisseurSecret
    {
        return match ($typeFournisseur) {
            'FICHIER_0600' => new FournisseurFichier0600('', 0),
            'CREDENTIAL_SYSTEMD' => new FournisseurCredentialSystemd(),
            'VARIABLE_ENVIRONNEMENT_TRANSITION' => new FournisseurEnvironnementTransition(),
            default => null,
        };
    }
}
