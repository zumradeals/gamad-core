<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class PropositionsAideGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function lister(string $sujet, string $besoin): array
    {
        $registre = Magasin::ouvrir();
        $this->besoin($registre, $sujet, $besoin);
        $requete = $registre->prepare(
            'SELECT reference, proposant_reference, message, etat FROM proposition_aide_gamad
             WHERE besoin_reference = ? AND (proposant_reference = ? OR CAST(? AS INTEGER) = 1)
             ORDER BY reference'
        );
        $requete->execute([$besoin, $sujet, EmetteurBesoinGamad::peutGerer($registre, $sujet, $besoin) ? 1 : 0]);
        $exclus = $this->blocages->exclus($registre, $sujet);
        $resultat = [];
        foreach ($requete->fetchAll() as $ligne) {
            $proposant = $ligne['proposant_reference'];
            $membre = isset($exclus[$proposant]) ? null : $this->membres->lire($proposant, $registre);
            if ($membre !== null) {
                unset($ligne['proposant_reference']);
                $resultat[] = $ligne + ['proposant' => $membre['nom']];
            }
        }

        return $resultat;
    }

    public function proposer(string $sujet, string $besoin, string $message): string
    {
        $registre = Magasin::ouvrir();

        return PaireGamad::verrouiller($registre, 'besoin', $besoin, function () use ($registre, $sujet, $besoin, $message): string {
            $publication = $this->besoin($registre, $sujet, $besoin, verrouiller: true);
            abort_if($sujet === $publication['auteur_reference'] || EmetteurBesoinGamad::peutGerer($registre, $sujet, $besoin), 403);
            $this->exigerOuvert($publication);

            $requete = $registre->prepare(
                "INSERT INTO proposition_aide_gamad (reference, besoin_reference, proposant_reference, message, etat)
                 VALUES (?, ?, ?, ?, 'pending')
                 ON CONFLICT (besoin_reference, proposant_reference) DO UPDATE
                 SET reference = excluded.reference, message = excluded.message, etat = 'pending'
                 WHERE proposition_aide_gamad.etat = 'withdrawn'
                 RETURNING reference"
            );
            $requete->execute([bin2hex(random_bytes(12)), $besoin, $sujet, Normalizer::multiline($message)]);
            $reference = $requete->fetchColumn();
            $requete->closeCursor();
            abort_unless(is_string($reference), 409);

            foreach (EmetteurBesoinGamad::gestionnaires($registre, $besoin) as $gestionnaire) {
                if ($this->membres->lire($gestionnaire, $registre) !== null
                    && !isset($this->blocages->exclus($registre, $sujet)[$gestionnaire])) {
                    NotificationsGamad::ajouter($registre, $gestionnaire, $sujet, 'demande', $reference);
                }
            }

            return $reference;
        });
    }

    public function traiter(string $sujet, string $besoin, string $reference, string $action): void
    {
        $registre = Magasin::ouvrir();
        PaireGamad::verrouiller($registre, 'besoin', $besoin, function () use ($registre, $sujet, $besoin, $reference, $action): void {
            $publication = $this->besoin($registre, $sujet, $besoin, verrouiller: true);
            $requete = $registre->prepare('SELECT proposant_reference FROM proposition_aide_gamad WHERE besoin_reference = ? AND reference = ?');
            $requete->execute([$besoin, $reference]);
            $proposant = $requete->fetchColumn();
            $requete->closeCursor();
            abort_unless(is_string($proposant), 404);

            $etat = match ($action) {
                'retirer' => 'withdrawn',
                'accepter' => 'accepted',
                'refuser' => 'declined',
            };
            if ($etat === 'withdrawn') {
                abort_unless($sujet === $proposant, 404);
            } else {
                abort_unless(EmetteurBesoinGamad::peutGerer($registre, $sujet, $besoin), 404);
                abort_if(isset($this->blocages->exclus($registre, $sujet)[$proposant])
                    || $this->membres->lire($proposant, $registre) === null, 404);
                $this->exigerOuvert($publication);
            }

            $requete = $registre->prepare(
                "UPDATE proposition_aide_gamad SET etat = ? WHERE reference = ? AND etat = 'pending'"
            );
            $requete->execute([$etat, $reference]);
            abort_unless($requete->rowCount() === 1, 409);

            if ($etat === 'accepted') {
                $registre->prepare('INSERT INTO acceptation_aide_gamad (proposition_reference, acceptant_reference) VALUES (?, ?)')
                    ->execute([$reference, $sujet]);
                $registre->prepare("UPDATE publication_gamad SET etat_besoin = 'in_progress' WHERE reference = ? AND etat_besoin = 'open'")
                    ->execute([$besoin]);
                NotificationsGamad::ajouter($registre, $proposant, $sujet, 'acceptation', $reference);
            }
        });
    }

    private function besoin(PDO $registre, string $sujet, string $reference, bool $verrouiller = false): array
    {
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $jointures = EmetteurBesoinGamad::JOINTURES;
        $visible = EmetteurBesoinGamad::VISIBLE;
        $sql = "SELECT p.auteur_reference, p.etat_besoin, sb.interne, z.reference AS zumra_interne, z.fondateur_reference FROM publication_gamad p {$jointures}
                WHERE p.reference = ? AND p.type = 'need' AND {$visible}
                AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad WHERE categorie = 'publication' AND cible_reference = ?)";
        if ($verrouiller && $registre->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $sql .= ' FOR UPDATE OF p';
        }
        $requete = $registre->prepare($sql);
        $requete->execute([$reference, $reference]);
        $publication = $requete->fetch();
        $requete->closeCursor();
        abort_if($publication === false || isset($this->blocages->exclus($registre, $sujet)[$publication['auteur_reference']])
            || $this->membres->lire($publication['auteur_reference'], $registre) === null
            || !EmetteurBesoinGamad::voitInterne($registre, $sujet, $publication), 404);

        return $publication;
    }

    private function exigerOuvert(array $publication): void
    {
        abort_unless(in_array($publication['etat_besoin'], ['open', 'in_progress'], true), 409);
    }
}
