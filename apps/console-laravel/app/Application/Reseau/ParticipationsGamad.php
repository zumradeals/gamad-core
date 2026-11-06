<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class ParticipationsGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public static function demandes(PDO $registre, string $projet): array
    {
        $requete = $registre->prepare(
            "SELECT p.candidat_reference, p.message, i.libelle AS nom FROM participation_projet_gamad p
             JOIN identite_inscrite i ON i.reference = p.candidat_reference
             WHERE p.projet_reference = ? AND p.etat = 'pending' ORDER BY i.libelle"
        );
        $requete->execute([$projet]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function etat(PDO $registre, string $projet, string $sujet): ?string
    {
        $requete = $registre->prepare('SELECT etat FROM participation_projet_gamad WHERE projet_reference = ? AND candidat_reference = ?');
        $requete->execute([$projet, $sujet]);
        $etat = $requete->fetchColumn();

        return $etat === false ? null : $etat;
    }

    public function agir(string $sujet, string $projet, string $action, ?string $cible, ?string $message): void
    {
        $registre = Magasin::ouvrir();
        $candidat = $cible ?? $sujet;
        PaireGamad::verrouiller($registre, $candidat, 'participation:'.$projet, function () use ($registre, $sujet, $projet, $action, $candidat, $message): void {
            abort_if($this->membres->lire($sujet, $registre) === null, 403);
            $requete = $registre->prepare(
                'SELECT p.porteur_reference, p.accueil, p.vitrine, z.fondateur_reference
                 FROM projet_gamad p JOIN zumra_gamad z ON z.reference = p.zumra_reference WHERE p.reference = ?'
            );
            $requete->execute([$projet]);
            $fiche = $requete->fetch(PDO::FETCH_ASSOC);
            $requete->closeCursor();
            abort_if($fiche === false, 404);
            $porteur = $fiche['porteur_reference'];
            $decision = in_array($action, ['accepter', 'refuser'], true);
            abort_unless($decision ? $porteur === $sujet && $candidat !== $sujet : $candidat === $sujet && $porteur !== $sujet, 404);

            if (in_array($action, ['proposer', 'accepter'], true)) {
                abort_if($fiche['accueil'] !== 'accepte' || $fiche['vitrine'] === null
                    || $this->membres->lire($porteur, $registre) === null
                    || $this->membres->lire($fiche['fondateur_reference'], $registre) === null
                    || $this->membres->lire($candidat, $registre) === null
                    || isset($this->blocages->exclus($registre, $porteur)[$candidat]), 404);
            }

            if ($action === 'proposer') {
                $texte = Normalizer::multiline($message ?? '');
                $requete = $registre->prepare(
                    "INSERT INTO participation_projet_gamad (projet_reference, candidat_reference, message, etat)
                     SELECT ?, ?, ?, 'pending' WHERE NOT EXISTS (
                         SELECT 1 FROM equipe_projet_gamad WHERE espace_reference = ? AND membre_reference = ?
                     )
                     ON CONFLICT (projet_reference, candidat_reference) DO UPDATE SET etat = 'pending', message = excluded.message
                     WHERE participation_projet_gamad.etat = 'withdrawn'"
                );
                $requete->execute([$projet, $candidat, $texte === '' ? null : $texte, $projet, $candidat]);
            } else {
                $etat = match ($action) {
                    'accepter' => 'accepted',
                    'refuser' => 'declined',
                    'retirer' => 'withdrawn',
                };
                $requete = $registre->prepare(
                    "UPDATE participation_projet_gamad SET etat = ? WHERE projet_reference = ? AND candidat_reference = ? AND etat = 'pending'"
                );
                $requete->execute([$etat, $projet, $candidat]);
            }
            abort_if($requete->rowCount() === 0, 409);
            if ($action === 'accepter') {
                $registre->prepare(
                    "INSERT INTO equipe_projet_gamad (espace_reference, membre_reference, etat) VALUES (?, ?, 'accepted')
                     ON CONFLICT (espace_reference, membre_reference) DO UPDATE SET etat = 'accepted'"
                )->execute([$projet, $candidat]);
            }
        });
    }
}
