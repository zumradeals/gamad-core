<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use Gamad\RegistreIdentites\Magasin;
use PDO;

final class AnnuaireGamad
{
    public function __construct(private MembreGamad $membres)
    {
    }

    public function fichePersonnelle(string $identite): array
    {
        $requete = Magasin::ouvrir()->prepare(
            'SELECT visibilite, competences_apportees, competences_recherchees
             FROM fiche_annuaire_gamad WHERE identite_reference = ?'
        );
        $requete->execute([$identite]);

        return $requete->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function rechercher(?string $session, ?string $referencePublique = null): array
    {
        $registre = Magasin::ouvrir();
        $membre = $this->membres->depuisSession($session, $registre) !== null;
        $visibilites = $membre ? "'public', 'members'" : "'public'";
        $sql = "SELECT * FROM fiche_annuaire_gamad WHERE visibilite IN ({$visibilites})";
        $parametres = [];
        if ($referencePublique !== null) {
            $sql .= ' AND reference_publique = ?';
            $parametres[] = $referencePublique;
        } else {
            $sql .= " AND (competences_apportees <> '' OR competences_recherchees <> '')";
        }

        $requete = $registre->prepare($sql.' ORDER BY reference_publique');
        $requete->execute($parametres);
        $profils = [];
        while ($fiche = $requete->fetch(PDO::FETCH_ASSOC)) {
            $compte = $this->membres->lire($fiche['identite_reference'], $registre);
            if ($compte !== null) {
                $profils[] = [
                    'reference_publique' => $fiche['reference_publique'],
                    'nom' => $compte['nom'],
                    'bio' => $compte['bio'],
                    'apportees' => array_filter(explode("\n", $fiche['competences_apportees']), 'strlen'),
                    'recherchees' => array_filter(explode("\n", $fiche['competences_recherchees']), 'strlen'),
                ];
            }
        }

        return $profils;
    }
}
