<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Fournisseur de migration progressive (fiche partie 2 §12.3).
 *
 * Toléré pour migrer les usages actuels dispersés dans l'environnement de
 * processus, jamais comme cible finale générale. Le registre n'enregistre
 * que le *nom* de la variable — jamais sa valeur — et cette classe refuse de
 * fonctionner sans une date de retrait explicitement déclarée pour la
 * version, afin qu'elle ne devienne jamais un repli silencieux permanent
 * (fiche partie 2 §12.3 et partie 5 §22).
 */
final class FournisseurEnvironnementTransition implements FournisseurSecret
{
    public function verifierDisponibilite(DescripteurVersion $version): DiagnosticFournisseur
    {
        $motif = $this->diagnostiquer($version);

        return new DiagnosticFournisseur($motif === null, $motif);
    }

    public function avecSecret(DescripteurVersion $version, UsageSecret $usage, callable $operation): mixed
    {
        $motif = $this->diagnostiquer($version);
        if ($motif !== null) {
            throw new ExceptionSecret("fournisseur de transition indisponible : {$motif}");
        }
        $valeur = getenv((string) $version->handle);
        if ($valeur === false || $valeur === '') {
            throw new ExceptionSecret('variable de transition absente');
        }
        try {
            return $operation(new SensitiveValue($valeur));
        } finally {
            $valeur = str_repeat("\0", strlen($valeur));
            unset($valeur);
        }
    }

    public function empreintePublique(DescripteurVersion $version): ?string
    {
        return null;
    }

    public function detruire(DescripteurVersion $version): ResultatDestruction
    {
        return new ResultatDestruction(
            false,
            'une variable d\'environnement se retire du déploiement, jamais par ce fournisseur',
        );
    }

    private function diagnostiquer(DescripteurVersion $version): ?string
    {
        $handle = (string) $version->handle;
        if ($handle === '' || str_contains($handle, '=')) {
            return 'nom de variable invalide';
        }
        if (getenv($handle) === false) {
            return 'variable absente de l\'environnement';
        }

        return null;
    }
}
