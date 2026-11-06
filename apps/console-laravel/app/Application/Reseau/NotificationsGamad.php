<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class NotificationsGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public static function ajouter(PDO $registre, string $destinataire, string $auteur, string $type): void
    {
        $registre->prepare(
            'INSERT INTO notification_reseau_gamad (reference, destinataire_reference, auteur_reference, type, cree_le)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([bin2hex(random_bytes(12)), $destinataire, $auteur, $type, gmdate('c')]);
    }

    public function lister(string $sujet): array
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $requete = $registre->prepare(
            'SELECT n.*, i.libelle AS nom, f.reference_publique
             FROM notification_reseau_gamad n JOIN identite_inscrite i ON i.reference = n.auteur_reference
             JOIN fiche_annuaire_gamad f ON f.identite_reference = i.reference
             WHERE n.destinataire_reference = ?
             ORDER BY n.cree_le DESC, n.reference DESC LIMIT 100'
        );
        $requete->execute([$sujet]);
        $exclus = $this->blocages->exclus($registre, $sujet);
        $resultat = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $notice) {
            if (!isset($exclus[$notice['auteur_reference']])
                && $this->membres->lire($notice['auteur_reference'], $registre) !== null) {
                $resultat[] = $notice;
            }
        }

        return $resultat;
    }

    public function lire(string $sujet, string $reference): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $requete = $registre->prepare(
            'UPDATE notification_reseau_gamad SET lue_le = COALESCE(lue_le, ?) WHERE reference = ? AND destinataire_reference = ?'
        );
        $requete->execute([gmdate('c'), $reference, $sujet]);
        abort_if($requete->rowCount() === 0, 404);
    }
}
