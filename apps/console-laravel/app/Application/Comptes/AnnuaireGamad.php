<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as MagasinAcces;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class AnnuaireGamad
{
    public function __construct(private LireCompteGamad $lecture)
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
        $membre = $this->estMembre($session, $registre);
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
            $compte = $this->lecture->executer($fiche['identite_reference'], $registre);
            if ($compte !== null && $this->emailVerifie($registre, $fiche['identite_reference'])) {
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

    private function estMembre(?string $session, PDO $registre): bool
    {
        if ($session === null) {
            return false;
        }

        $verdict = (new Ctr16(MagasinAcces::connecter()))->verifierSession($session);
        $reference = $verdict['entite'] ?? null;

        return ($verdict['valide'] ?? false) === true && is_string($reference)
            && $this->lecture->executer($reference, $registre) !== null
            && $this->emailVerifie($registre, $reference);
    }

    private function emailVerifie(PDO $registre, string $reference): bool
    {
        $requete = $registre->prepare(
            "SELECT 1 FROM identifiant_resolution WHERE identite_reference = ? AND type = 'EMAIL' AND etat = 'VERIFIE' LIMIT 1"
        );
        $requete->execute([$reference]);

        return $requete->fetchColumn() !== false;
    }
}
