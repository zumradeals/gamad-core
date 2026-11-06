<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use App\Application\Reseau\BlocagesGamad;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class AnnuaireGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function fichePersonnelle(string $identite): array
    {
        $registre = Magasin::ouvrir();
        $requete = $registre->prepare(
            'SELECT f.visibilite, f.competences_apportees, f.competences_recherchees, d.titre, d.introduction, d.territoire
             FROM fiche_annuaire_gamad f LEFT JOIN details_annuaire_gamad d ON d.identite_reference = f.identite_reference
             WHERE f.identite_reference = ?'
        );
        $requete->execute([$identite]);

        $fiche = $requete->fetch(PDO::FETCH_ASSOC);
        $requete->closeCursor();

        return $fiche === false ? [] : $fiche + [
            'domaines' => $this->domaines($registre, $identite),
            'experiences' => ExperiencesProfilGamad::lister($registre, $identite),
        ];
    }

    public function rechercher(?string $session, ?string $referencePublique = null, ?string $territoire = null, ?string $domaine = null): array
    {
        $registre = Magasin::ouvrir();
        $membre = $this->membres->depuisSession($session, $registre);
        $sujet = $membre['identite_reference'] ?? null;
        $exclus = $this->blocages->exclus($registre, $sujet);
        $visibilites = $membre ? "'public', 'members'" : "'public'";
        $sql = "SELECT f.*, d.titre, d.introduction, d.territoire FROM fiche_annuaire_gamad f
                LEFT JOIN details_annuaire_gamad d ON d.identite_reference = f.identite_reference
                WHERE f.visibilite IN ({$visibilites})";
        $parametres = [];
        if ($referencePublique !== null) {
            $sql .= ' AND reference_publique = ?';
            $parametres[] = $referencePublique;
        }
        if ($territoire !== null) {
            $sql .= ' AND d.territoire = ?';
            $parametres[] = $territoire;
        }
        if ($domaine !== null) {
            $sql .= ' AND EXISTS (SELECT 1 FROM domaine_annuaire_gamad da WHERE da.identite_reference = f.identite_reference AND da.domaine = ?)';
            $parametres[] = $domaine;
        }

        $requete = $registre->prepare($sql.' ORDER BY reference_publique');
        $requete->execute($parametres);
        $profils = [];
        while ($fiche = $requete->fetch(PDO::FETCH_ASSOC)) {
            if (isset($exclus[$fiche['identite_reference']])) {
                continue;
            }
            $compte = $this->membres->lire($fiche['identite_reference'], $registre);
            if ($compte !== null) {
                $profils[] = [
                    'reference_publique' => $fiche['reference_publique'],
                    'peut_bloquer' => $sujet !== null && $sujet !== $fiche['identite_reference'],
                    'nom' => $compte['nom'],
                    'bio' => $compte['bio'],
                    'titre' => $fiche['titre'],
                    'introduction' => $fiche['introduction'],
                    'territoire' => $fiche['territoire'],
                    'domaines' => $this->domaines($registre, $fiche['identite_reference']),
                    'experiences' => $referencePublique === null ? [] : ExperiencesProfilGamad::lister($registre, $fiche['identite_reference']),
                    'apportees' => array_filter(explode("\n", $fiche['competences_apportees']), 'strlen'),
                    'recherchees' => array_filter(explode("\n", $fiche['competences_recherchees']), 'strlen'),
                ];
            }
        }

        return $profils;
    }

    private function domaines(PDO $registre, string $identite): array
    {
        $requete = $registre->prepare('SELECT domaine FROM domaine_annuaire_gamad WHERE identite_reference = ? ORDER BY domaine');
        $requete->execute([$identite]);

        return $requete->fetchAll(PDO::FETCH_COLUMN);
    }
}
