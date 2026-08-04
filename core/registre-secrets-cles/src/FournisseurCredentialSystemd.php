<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Consomme un credential injecté par systemd (fiche partie 2 §12.2).
 *
 * Lit depuis `$CREDENTIALS_DIRECTORY/<handle>`, le répertoire que systemd
 * fournit au processus via `LoadCredential=`/`SetCredential=`. Aucune valeur
 * ne vit dans l'unité ni dans le dépôt ; l'indisponibilité du répertoire ou
 * du fichier ferme l'opération, sans repli.
 */
final class FournisseurCredentialSystemd implements FournisseurSecret
{
    private const TAILLE_MAX_OCTETS = 65_536;

    public function __construct(
        private readonly ?string $repertoireCredentials = null,
    ) {
    }

    public function verifierDisponibilite(DescripteurVersion $version): DiagnosticFournisseur
    {
        $motif = $this->diagnostiquer($version);

        return new DiagnosticFournisseur($motif === null, $motif);
    }

    public function avecSecret(DescripteurVersion $version, UsageSecret $usage, callable $operation): mixed
    {
        $motif = $this->diagnostiquer($version);
        if ($motif !== null) {
            throw new ExceptionSecret("credential systemd indisponible : {$motif}");
        }
        $contenu = @file_get_contents($this->chemin($version));
        if ($contenu === false) {
            throw new ExceptionSecret('lecture du credential échouée');
        }
        try {
            return $operation(new SensitiveValue(rtrim($contenu, "\n")));
        } finally {
            $contenu = str_repeat("\0", strlen($contenu));
            unset($contenu);
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
            'un credential systemd se retire par déploiement, jamais par ce fournisseur',
        );
    }

    private function repertoire(): ?string
    {
        return $this->repertoireCredentials ?? (getenv('CREDENTIALS_DIRECTORY') ?: null);
    }

    private function chemin(DescripteurVersion $version): string
    {
        return rtrim((string) $this->repertoire(), '/') . '/' . (string) $version->handle;
    }

    private function diagnostiquer(DescripteurVersion $version): ?string
    {
        $repertoire = $this->repertoire();
        if ($repertoire === null || $repertoire === '') {
            return 'CREDENTIALS_DIRECTORY absent — processus non lancé sous systemd credentials';
        }
        $handle = (string) $version->handle;
        if ($handle === '' || str_contains($handle, '/') || str_contains($handle, '..')) {
            return 'nom de credential invalide';
        }
        $chemin = $this->chemin($version);
        if (!is_file($chemin)) {
            return 'credential absent';
        }
        $taille = @filesize($chemin);
        if ($taille === false || $taille > self::TAILLE_MAX_OCTETS) {
            return 'taille de credential invalide';
        }

        return null;
    }
}
