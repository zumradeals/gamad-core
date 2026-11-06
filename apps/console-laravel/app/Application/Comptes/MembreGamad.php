<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use PDO;

final class MembreGamad
{
    public function __construct(private LireCompteGamad $comptes)
    {
    }

    public function lire(string $reference, PDO $registre): ?array
    {
        $requete = $registre->prepare(
            "SELECT 1 FROM identifiant_resolution
             WHERE identite_reference = ? AND type = 'EMAIL' AND etat = 'VERIFIE' LIMIT 1"
        );
        $requete->execute([$reference]);

        return $requete->fetchColumn() === false ? null : $this->comptes->executer($reference, $registre);
    }

    public function depuisSession(?string $session, PDO $registre): ?array
    {
        if ($session === null) {
            return null;
        }

        $verdict = (new Ctr16(Magasin::connecter()))->verifierSession($session);
        $reference = $verdict['entite'] ?? null;

        return ($verdict['valide'] ?? false) === true && is_string($reference)
            ? $this->lire($reference, $registre)
            : null;
    }
}
