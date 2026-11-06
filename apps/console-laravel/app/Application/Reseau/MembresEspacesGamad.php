<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class MembresEspacesGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function adhesion(string $sujet, string $zumra, bool $rejoindre): void
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $fondateur = $this->responsable($registre, $zumra, true);
        abort_if($fondateur === $sujet, 403);
        if ($rejoindre) {
            $this->ajouter($registre, 'adhesion_zumra_gamad', $zumra, $sujet);
        } else {
            $this->retirer($registre, 'adhesion_zumra_gamad', $zumra, $sujet);
        }
    }

    public function inviter(string $sujet, string $projet, string $reference): void
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        abort_unless($this->responsable($registre, $projet, false) === $sujet, 404);
        $requete = $registre->prepare("SELECT identite_reference FROM fiche_annuaire_gamad WHERE reference_publique = ? AND visibilite <> 'prive'");
        $requete->execute([$reference]);
        $membre = $requete->fetchColumn();
        $requete->closeCursor();
        abort_if(!is_string($membre) || $membre === $sujet || $this->membres->lire($membre, $registre) === null
            || isset($this->blocages->exclus($registre, $sujet)[$membre]), 404);
        $this->ajouter($registre, 'equipe_projet_gamad', $projet, $membre);
    }

    public function decider(string $sujet, string $espace, ?string $cible, bool $zumra, string $action): void
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $responsable = $this->responsable($registre, $espace, $zumra);
        $table = $zumra ? 'adhesion_zumra_gamad' : 'equipe_projet_gamad';

        if ($zumra || $cible !== null) {
            abort_unless($responsable === $sujet && $cible !== null, 404);
            $requete = $registre->prepare("SELECT membre_reference FROM {$table} WHERE espace_reference = ? AND membre_reference = ?");
            $requete->execute([$espace, $cible]);
            abort_unless($requete->fetchColumn() !== false, 404);
            // Le porteur peut retirer une invitation ou un membre, mais seul l’invité peut accepter.
            abort_if(!$zumra && $action === 'accepter', 403);
        }
        $membre = $cible ?? $sujet;

        if ($action === 'retirer') {
            $this->retirer($registre, $table, $espace, $membre);
            return;
        }

        $operation = $action === 'accepter' ? "UPDATE {$table} SET etat = 'accepted'" : "DELETE FROM {$table}";
        if ($action === 'accepter') {
            abort_if($this->membres->lire($membre, $registre) === null
                || (!$zumra && isset($this->blocages->exclus($registre, $sujet)[$responsable])), 404);
        }
        $requete = $registre->prepare($operation." WHERE espace_reference = ? AND membre_reference = ? AND etat = 'pending'");
        $requete->execute([$espace, $membre]);
        abort_if($requete->rowCount() === 0, 409);
    }

    public static function liste(PDO $registre, string $espace, bool $zumra): array
    {
        $table = $zumra ? 'adhesion_zumra_gamad' : 'equipe_projet_gamad';
        $requete = $registre->prepare(
            "SELECT m.membre_reference, m.etat, i.libelle AS nom FROM {$table} m
             JOIN identite_inscrite i ON i.reference = m.membre_reference WHERE m.espace_reference = ? ORDER BY i.libelle"
        );
        $requete->execute([$espace]);

        return $requete->fetchAll();
    }

    private function responsable(PDO $registre, string $espace, bool $zumra): string
    {
        $sql = $zumra ? 'SELECT fondateur_reference FROM zumra_gamad' : 'SELECT porteur_reference FROM projet_gamad';
        $requete = $registre->prepare($sql.' WHERE reference = ?');
        $requete->execute([$espace]);
        $responsable = $requete->fetchColumn();
        $requete->closeCursor();
        abort_if(!is_string($responsable) || $this->membres->lire($responsable, $registre) === null, 404);

        return $responsable;
    }

    private function ajouter(PDO $registre, string $table, string $espace, string $membre): void
    {
        $requete = $registre->prepare(
            "INSERT INTO {$table} (espace_reference, membre_reference, etat) VALUES (?, ?, 'pending')
             ON CONFLICT (espace_reference, membre_reference) DO NOTHING"
        );
        $requete->execute([$espace, $membre]);
        abort_if($requete->rowCount() === 0, 409);
    }

    private function retirer(PDO $registre, string $table, string $espace, string $membre): void
    {
        $requete = $registre->prepare("DELETE FROM {$table} WHERE espace_reference = ? AND membre_reference = ?");
        $requete->execute([$espace, $membre]);
        abort_if($requete->rowCount() === 0, 404);
    }

    private function exigerMembre(PDO $registre, string $sujet): void
    {
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
    }
}
