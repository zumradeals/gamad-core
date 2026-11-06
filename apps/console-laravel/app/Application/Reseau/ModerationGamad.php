<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class ModerationGamad
{
    public const MOTIFS = [
        'offensive' => 'Contenu offensant ou haineux',
        'scam' => 'Arnaque ou demande d’argent suspecte',
        'misinformation' => 'Fausse information',
        'spam' => 'Publicité ou spam',
        'privacy' => 'Atteinte à la vie privée',
        'other' => 'Autre',
    ];

    public function __construct(private ContenusGamad $contenus, private MembreGamad $membres)
    {
    }

    public function signaler(string $sujet, ?string $session, string $publication, ?string $commentaire, string $motif): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $fiche = $this->contenus->publication($publication, $session);
        abort_if($fiche === null, 404);

        $categorie = $commentaire === null ? 'publication' : 'commentaire';
        $cible = $commentaire ?? $publication;
        if ($commentaire !== null) {
            abort_unless(in_array($commentaire, array_column($fiche['commentaires'], 'reference'), true), 404);
        }

        $table = $commentaire === null ? 'publication_gamad' : 'commentaire_gamad';
        $requete = $registre->prepare("SELECT auteur_reference FROM {$table} WHERE reference = ?");
        $requete->execute([$cible]);
        $auteur = $requete->fetchColumn();
        abort_unless(is_string($auteur), 404);
        abort_if($auteur === $sujet, 403);

        $requete = $registre->prepare(
            "INSERT INTO signalement_reseau_gamad (reference, categorie, cible_reference, auteur_reference, motif, etat, cree_le)
             VALUES (?, ?, ?, ?, ?, 'ouvert', ?)
             ON CONFLICT (categorie, cible_reference, auteur_reference) DO NOTHING"
        );
        $requete->execute([bin2hex(random_bytes(12)), $categorie, $cible, $sujet, $motif, gmdate('c')]);
        abort_if($requete->rowCount() === 0, 409);
    }

    public function lister(): array
    {
        return Magasin::ouvrir()->query(
            "SELECT s.*, COALESCE(p.contenu, c.contenu) AS contenu
             FROM signalement_reseau_gamad s
             LEFT JOIN publication_gamad p ON s.categorie = 'publication' AND p.reference = s.cible_reference
             LEFT JOIN commentaire_gamad c ON s.categorie = 'commentaire' AND c.reference = s.cible_reference
             WHERE s.etat = 'ouvert' ORDER BY s.cree_le LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function traiter(string $acteur, string $reference, bool $masquer): void
    {
        $registre = Magasin::ouvrir();
        $registre->beginTransaction();
        try {
            $requete = $registre->prepare(
                "UPDATE signalement_reseau_gamad SET etat = ?, decision_par = ?, decision_le = ?
                 WHERE reference = ? AND etat = 'ouvert'"
            );
            $requete->execute([$masquer ? 'masque' : 'classe', $acteur, gmdate('c'), $reference]);
            abort_if($requete->rowCount() === 0, 404);
            if ($masquer) {
                $registre->prepare(
                    'INSERT INTO masquage_reseau_gamad (categorie, cible_reference)
                     SELECT categorie, cible_reference FROM signalement_reseau_gamad WHERE reference = ?
                     ON CONFLICT (categorie, cible_reference) DO NOTHING'
                )->execute([$reference]);
            }
            $registre->commit();
        } catch (\Throwable $exception) {
            $registre->rollBack();
            throw $exception;
        }
    }
}
