<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Contrat commun à tout fournisseur de matériel secret (fiche partie 2 §13).
 *
 * Volontairement sans méthode d'export général : aucune implémentation ne
 * doit exposer `exporterTousLesSecrets()` ni retourner le matériel à une
 * couche HTTP. `avecSecret()` transmet la valeur uniquement au callback
 * interne fourni, jamais à son appelant.
 */
interface FournisseurSecret
{
    public function verifierDisponibilite(DescripteurVersion $version): DiagnosticFournisseur;

    /**
     * @template T
     * @param callable(SensitiveValue):T $operation
     * @return T
     */
    public function avecSecret(DescripteurVersion $version, UsageSecret $usage, callable $operation): mixed;

    public function empreintePublique(DescripteurVersion $version): ?string;

    public function detruire(DescripteurVersion $version): ResultatDestruction;
}
