<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Validation\ValidationException;
use PDO;

final class EtapesProjetGamad
{
    public const ETATS = ['todo' => 'À faire', 'in_progress' => 'En cours', 'done' => 'Franchie'];

    public function __construct(private MembreGamad $membres)
    {
    }

    public static function lister(PDO $registre, string $projet): array
    {
        $requete = $registre->prepare('SELECT reference, titre, etat, position FROM etape_projet_gamad WHERE projet_reference = ? ORDER BY position');
        $requete->execute([$projet]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    public function modifier(string $sujet, string $projet, ?string $reference, array $donnees): void
    {
        $registre = Magasin::ouvrir();
        PaireGamad::verrouiller($registre, $sujet, 'projet:'.$projet, function () use ($registre, $sujet, $projet, $reference, $donnees): void {
            abort_if($this->membres->lire($sujet, $registre) === null, 403);
            $proprietaire = $registre->prepare('SELECT 1 FROM projet_gamad WHERE reference = ? AND porteur_reference = ?');
            $proprietaire->execute([$projet, $sujet]);
            abort_if($proprietaire->fetchColumn() === false, 404);
            $proprietaire->closeCursor();
            $etapes = self::lister($registre, $projet);
            if ($reference === null) {
                if (count($etapes) >= 20) {
                    throw ValidationException::withMessages(['titre' => 'Un projet comporte au plus vingt étapes.']);
                }
                $registre->prepare(
                    "INSERT INTO etape_projet_gamad (reference, projet_reference, titre, etat, position) VALUES (?, ?, ?, 'todo', ?)"
                )->execute([bin2hex(random_bytes(12)), $projet, $this->titre($donnees['titre']), count($etapes) + 1]);

                return;
            }

            $index = array_search($reference, array_column($etapes, 'reference'), true);
            abort_if($index === false, 404);
            $action = $donnees['action'];
            if ($action === 'enregistrer') {
                $registre->prepare('UPDATE etape_projet_gamad SET titre = ?, etat = ? WHERE reference = ?')
                    ->execute([$this->titre($donnees['titre']), $donnees['etat'], $reference]);

                return;
            }

            $deplacer = $registre->prepare('UPDATE etape_projet_gamad SET position = ? WHERE reference = ?');
            if ($action === 'retirer') {
                $registre->prepare('DELETE FROM etape_projet_gamad WHERE reference = ?')->execute([$reference]);
                foreach (array_slice($etapes, $index + 1) as $suivante) {
                    $deplacer->execute([(int) $suivante['position'] - 1, $suivante['reference']]);
                }

                return;
            }

            $voisine = $etapes[$index + ($action === 'monter' ? -1 : 1)] ?? null;
            if ($voisine !== null) {
                $deplacer->execute([0, $reference]);
                $deplacer->execute([$etapes[$index]['position'], $voisine['reference']]);
                $deplacer->execute([$voisine['position'], $reference]);
            }
        });
    }

    private function titre(string $saisie): string
    {
        $titre = Normalizer::display($saisie);
        if (mb_strlen($titre) < 2) {
            throw ValidationException::withMessages(['titre' => 'Le titre doit comporter au moins deux caractères.']);
        }

        return $titre;
    }
}
