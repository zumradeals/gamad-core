<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use PDO;

final class EmetteurBesoinGamad
{
    public const JOINTURES = '
        LEFT JOIN emetteur_besoin_gamad e ON e.besoin_reference = p.reference
        LEFT JOIN projet_gamad pr ON pr.reference = e.projet_reference
        LEFT JOIN zumra_gamad z ON z.reference = COALESCE(e.zumra_reference, pr.zumra_reference)
        LEFT JOIN suivi_besoin_gamad sb ON sb.besoin_reference = p.reference';

    public const VISIBLE = "(e.projet_reference IS NULL OR (pr.accueil = 'accepte' AND pr.vitrine IS NOT NULL))";

    public const TYPE = "CASE WHEN e.projet_reference IS NOT NULL THEN 'projet' WHEN e.zumra_reference IS NOT NULL THEN 'zumra' ELSE 'personne' END";

    public static function peutPublier(PDO $registre, string $sujet, ?string $zumra, ?string $projet): bool
    {
        if ($projet !== null) {
            $requete = $registre->prepare(
                "SELECT 1 FROM projet_gamad WHERE reference = ? AND porteur_reference = ?
                 AND accueil = 'accepte' AND vitrine IS NOT NULL"
            );
            $requete->execute([$projet, $sujet]);

            return $requete->fetchColumn() !== false;
        }

        if ($zumra === null) {
            return true;
        }

        $requete = $registre->prepare('SELECT fondateur_reference FROM zumra_gamad WHERE reference = ?');
        $requete->execute([$zumra]);
        $fondateur = $requete->fetchColumn();

        return $fondateur === $sujet || MembresEspacesGamad::estDelegue($registre, $zumra, $sujet);
    }

    public static function peutGerer(PDO $registre, string $sujet, string $besoin): bool
    {
        return in_array($sujet, self::gestionnaires($registre, $besoin), true);
    }

    public static function gestionnaires(PDO $registre, string $besoin): array
    {
        $jointures = self::JOINTURES;
        $requete = $registre->prepare(
            "SELECT p.auteur_reference, e.zumra_reference, e.projet_reference, z.fondateur_reference, pr.porteur_reference
             FROM publication_gamad p {$jointures} WHERE p.reference = ? AND p.type IN ('need', 'learning_wish', 'offer')"
        );
        $requete->execute([$besoin]);
        $publication = $requete->fetch();
        $requete->closeCursor();
        if ($publication === false) {
            return [];
        }
        if ($publication['projet_reference'] !== null) {
            return [$publication['porteur_reference']];
        }
        if ($publication['zumra_reference'] === null) {
            return [$publication['auteur_reference']];
        }

        $requete = $registre->prepare(
            "SELECT r.membre_reference FROM responsabilite_zumra_gamad r
             JOIN adhesion_zumra_gamad a ON a.espace_reference = r.espace_reference AND a.membre_reference = r.membre_reference
             WHERE r.espace_reference = ? AND a.etat = 'accepted'"
        );
        $requete->execute([$publication['zumra_reference']]);

        return array_unique([$publication['fondateur_reference'], ...$requete->fetchAll(PDO::FETCH_COLUMN)]);
    }

    public static function voitInterne(PDO $registre, ?string $sujet, array $publication): bool
    {
        if ((int) ($publication['interne'] ?? 0) === 0) {
            return true;
        }
        if ($sujet === null) {
            return false;
        }
        if ($sujet === $publication['fondateur_reference']) {
            return true;
        }
        $requete = $registre->prepare(
            "SELECT 1 FROM adhesion_zumra_gamad WHERE espace_reference = ? AND membre_reference = ? AND etat = 'accepted'"
        );
        $requete->execute([$publication['zumra_interne'], $sujet]);

        return $requete->fetchColumn() !== false;
    }
}
