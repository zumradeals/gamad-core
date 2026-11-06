<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use PDO;

final class EmetteurBesoinGamad
{
    public const JOINTURES = '
        LEFT JOIN emetteur_besoin_gamad e ON e.besoin_reference = p.reference
        LEFT JOIN projet_gamad pr ON pr.reference = e.projet_reference
        LEFT JOIN zumra_gamad z ON z.reference = COALESCE(e.zumra_reference, pr.zumra_reference)';

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
        $requete = $registre->prepare(
            'SELECT p.auteur_reference, e.zumra_reference, e.projet_reference
             FROM publication_gamad p LEFT JOIN emetteur_besoin_gamad e ON e.besoin_reference = p.reference
             WHERE p.reference = ?'
        );
        $requete->execute([$besoin]);
        $publication = $requete->fetch();

        return $publication !== false && $publication['auteur_reference'] === $sujet
            && self::peutPublier($registre, $sujet, $publication['zumra_reference'], $publication['projet_reference']);
    }
}
