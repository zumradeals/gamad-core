<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;

final class ModifierProfilGamad
{
    public const BIO_MAX = 280;

    public function __construct(private LireCompteGamad $lecture)
    {
    }

    public function executer(string $identite, ?string $bio): bool
    {
        $registre = Magasin::ouvrir();
        if ($this->lecture->executer($identite, $registre) === null) {
            return false;
        }

        $requete = $registre->prepare(
            'INSERT INTO profil_compte_gamad (identite_reference, bio) VALUES (?, ?)
             ON CONFLICT (identite_reference) DO UPDATE SET bio = excluded.bio'
        );

        $presentation = Normalizer::multiline($bio ?? '');

        return $requete->execute([$identite, $presentation === '' ? null : $presentation]);
    }
}
