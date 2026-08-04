<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Lit un secret depuis un fichier dédié, protégé (fiche partie 2 §12.1).
 *
 * Le handle déclaré dans `secret_version.handle_fournisseur` est le chemin
 * absolu du fichier — jamais son contenu. La lecture est à la demande, sans
 * mise en cache durable, et n'écrit jamais la valeur dans un log.
 */
final class FournisseurFichier0600 implements FournisseurSecret
{
    private const TAILLE_MAX_OCTETS = 65_536;

    public function __construct(
        private readonly string $proprietaireAttendu,
        private readonly int $groupeAttendu,
    ) {
    }

    public function verifierDisponibilite(DescripteurVersion $version): DiagnosticFournisseur
    {
        $diagnostic = $this->diagnostiquerChemin((string) $version->handle);

        return new DiagnosticFournisseur($diagnostic === null, $diagnostic);
    }

    public function avecSecret(DescripteurVersion $version, UsageSecret $usage, callable $operation): mixed
    {
        $motif = $this->diagnostiquerChemin((string) $version->handle);
        if ($motif !== null) {
            throw new ExceptionSecret("fournisseur fichier indisponible : {$motif}");
        }
        $contenu = @file_get_contents((string) $version->handle);
        if ($contenu === false) {
            throw new ExceptionSecret('lecture du fichier échouée');
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
        $chemin = (string) $version->handle;
        if (!is_file($chemin)) {
            return new ResultatDestruction(true, 'fichier déjà absent');
        }
        $reussi = @unlink($chemin);

        return new ResultatDestruction($reussi, $reussi ? null : 'suppression refusée par le système de fichiers');
    }

    private function diagnostiquerChemin(string $chemin): ?string
    {
        if ($chemin === '' || $chemin[0] !== '/') {
            return 'chemin non absolu';
        }
        if (is_link($chemin)) {
            return 'lien symbolique refusé';
        }
        if (!is_file($chemin)) {
            return 'fichier absent ou irrégulier';
        }
        $stat = @stat($chemin);
        if ($stat === false) {
            return 'stat impossible';
        }
        $mode = $stat['mode'] & 0777;
        if ($mode > 0600) {
            return "permissions trop larges ({$this->octal($mode)})";
        }
        if ((int) $stat['size'] > self::TAILLE_MAX_OCTETS) {
            return 'fichier trop volumineux';
        }
        if ($this->proprietaireAttendu !== '' && (function_exists('posix_getpwuid'))) {
            $info = @posix_getpwuid($stat['uid']);
            if ($info !== false && $info['name'] !== $this->proprietaireAttendu) {
                return "propriétaire inattendu ({$info['name']})";
            }
        }
        if ($this->groupeAttendu > 0 && (int) $stat['gid'] !== $this->groupeAttendu) {
            return 'groupe inattendu';
        }
        $parent = dirname($chemin);
        $statParent = @stat($parent);
        if ($statParent !== false && ($statParent['mode'] & 0007) !== 0) {
            return 'répertoire parent accessible au monde';
        }

        return null;
    }

    private function octal(int $mode): string
    {
        return '0' . decoct($mode);
    }
}
