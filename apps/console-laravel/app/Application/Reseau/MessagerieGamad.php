<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Validation\ValidationException;
use PDO;

final class MessagerieGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function lire(string $sujet, string $reference): array
    {
        $registre = Magasin::ouvrir();
        $cible = $this->cible($registre, $reference);
        $paire = [$sujet, $cible];
        sort($paire, SORT_STRING);
        $autre = $this->autoriser($registre, $sujet, $cible, $paire);
        $requete = $registre->prepare(
            'SELECT auteur_reference, texte, cree_le FROM message_reseau_gamad
             WHERE premiere_reference = ? AND seconde_reference = ? ORDER BY cree_le DESC, reference DESC LIMIT 100'
        );
        $requete->execute($paire);

        return ['nom' => $autre['nom'], 'messages' => array_reverse($requete->fetchAll(PDO::FETCH_ASSOC))];
    }

    public function envoyer(string $sujet, string $reference, string $texte): void
    {
        $texte = Normalizer::multiline($texte);
        if ($texte === '') {
            throw ValidationException::withMessages(['texte' => 'Saisissez un message.']);
        }

        $registre = Magasin::ouvrir();
        $cible = $this->cible($registre, $reference);
        PaireGamad::verrouiller($registre, $sujet, $cible, function (array $paire) use ($registre, $sujet, $cible, $texte): void {
            $this->autoriser($registre, $sujet, $cible, $paire);
            $registre->prepare(
                'INSERT INTO message_reseau_gamad (reference, premiere_reference, seconde_reference, auteur_reference, texte, cree_le)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([bin2hex(random_bytes(12)), ...$paire, $sujet, $texte, gmdate('c')]);
            NotificationsGamad::ajouter($registre, $cible, $sujet, 'message');
        });
    }

    private function cible(PDO $registre, string $reference): string
    {
        $requete = $registre->prepare('SELECT identite_reference FROM fiche_annuaire_gamad WHERE reference_publique = ?');
        $requete->execute([$reference]);
        $cible = $requete->fetchColumn();
        $requete->closeCursor();
        abort_unless(is_string($cible), 404);

        return $cible;
    }

    private function autoriser(PDO $registre, string $sujet, string $cible, array $paire): array
    {
        abort_if($sujet === $cible || $this->membres->lire($sujet, $registre) === null
            || isset($this->blocages->exclus($registre, $sujet)[$cible]), 404);
        $autre = $this->membres->lire($cible, $registre);
        $requete = $registre->prepare(
            "SELECT 1 FROM relation_personnes_gamad
             WHERE premiere_reference = ? AND seconde_reference = ? AND etat = 'accepted'"
        );
        $requete->execute($paire);
        abort_if($autre === null || $requete->fetchColumn() === false, 404);

        return $autre;
    }
}
