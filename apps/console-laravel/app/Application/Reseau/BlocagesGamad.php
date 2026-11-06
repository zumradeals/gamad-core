<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class BlocagesGamad
{
    public function __construct(private MembreGamad $membres)
    {
    }

    public function exclus(PDO $registre, ?string $sujet): array
    {
        if ($sujet === null) {
            return [];
        }

        $requete = $registre->prepare(
            'SELECT cible_reference FROM blocage_gamad WHERE auteur_reference = ?
             UNION SELECT auteur_reference FROM blocage_gamad WHERE cible_reference = ?'
        );
        $requete->execute([$sujet, $sujet]);

        return array_fill_keys($requete->fetchAll(PDO::FETCH_COLUMN), true);
    }

    public function lister(string $sujet): ?array
    {
        $registre = Magasin::ouvrir();
        if ($this->membres->lire($sujet, $registre) === null) {
            return null;
        }

        $requete = $registre->prepare(
            'SELECT i.libelle AS nom, f.reference_publique
             FROM blocage_gamad b
             JOIN identite_inscrite i ON i.reference = b.cible_reference
             JOIN fiche_annuaire_gamad f ON f.identite_reference = b.cible_reference
             WHERE b.auteur_reference = ? ORDER BY i.libelle'
        );
        $requete->execute([$sujet]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    public function modifier(string $sujet, string $referencePublique, bool $bloquer): bool
    {
        $registre = Magasin::ouvrir();
        if ($this->membres->lire($sujet, $registre) === null) {
            return false;
        }

        $requete = $registre->prepare(
            'SELECT identite_reference, visibilite FROM fiche_annuaire_gamad WHERE reference_publique = ?'
        );
        $requete->execute([$referencePublique]);
        $fiche = $requete->fetch(PDO::FETCH_ASSOC);
        $requete->closeCursor();
        if ($fiche === false || $fiche['identite_reference'] === $sujet) {
            return false;
        }

        $cible = $fiche['identite_reference'];
        if (!$bloquer) {
            return PaireGamad::verrouiller($registre, $sujet, $cible, function () use ($registre, $sujet, $cible): bool {
                $suppression = $registre->prepare('DELETE FROM blocage_gamad WHERE auteur_reference = ? AND cible_reference = ?');
                $suppression->execute([$sujet, $cible]);

                return $suppression->rowCount() === 1;
            });
        }

        if ($fiche['visibilite'] === 'prive' || $this->membres->lire($cible, $registre) === null) {
            return false;
        }

        return PaireGamad::verrouiller($registre, $sujet, $cible, function (array $paire) use ($registre, $sujet, $cible): bool {
            $registre->prepare(
                'INSERT INTO blocage_gamad (auteur_reference, cible_reference) VALUES (?, ?)
                 ON CONFLICT (auteur_reference, cible_reference) DO NOTHING'
            )->execute([$sujet, $cible]);

            return $registre->prepare(
                "UPDATE relation_personnes_gamad SET etat = 'removed', note = NULL, revision = revision + 1, modifie_le = ?
                 WHERE premiere_reference = ? AND seconde_reference = ? AND etat IN ('pending', 'accepted')"
            )->execute([time(), ...$paire]);
        });
    }
}
