<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Validation\ValidationException;
use PDO;

final class ConversationAideGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function lire(string $sujet, string $besoin, string $proposition): array
    {
        $registre = Magasin::ouvrir();
        $cible = $this->interlocuteur($registre, $sujet, $besoin, $proposition);
        $this->autoriser($registre, $sujet, $cible);
        $requete = $registre->prepare(
            'SELECT reference, auteur_reference, texte, cree_le FROM message_aide_gamad
             WHERE proposition_reference = ? ORDER BY cree_le DESC, reference DESC LIMIT 100'
        );
        $requete->execute([$proposition]);

        return array_reverse(array_map(static fn (array $ligne): array => [
            'reference' => $ligne['reference'],
            'de_moi' => $ligne['auteur_reference'] === $sujet,
            'texte' => $ligne['texte'],
            'cree_le' => $ligne['cree_le'],
        ], $requete->fetchAll()));
    }

    public function envoyer(string $sujet, string $besoin, string $proposition, string $texte): void
    {
        $texte = Normalizer::multiline($texte);
        if ($texte === '' || mb_strlen($texte) > 1000) {
            throw ValidationException::withMessages(['texte' => 'Saisissez un message de 1 à 1 000 caractères.']);
        }

        $registre = Magasin::ouvrir();
        $cible = $this->interlocuteur($registre, $sujet, $besoin, $proposition);
        PaireGamad::verrouiller($registre, $sujet, $cible, function () use ($registre, $sujet, $cible, $proposition, $texte): void {
            $this->autoriser($registre, $sujet, $cible);
            $registre->prepare(
                'INSERT INTO message_aide_gamad (reference, proposition_reference, auteur_reference, texte, cree_le)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([bin2hex(random_bytes(12)), $proposition, $sujet, $texte, gmdate('c')]);
        });
    }

    private function interlocuteur(PDO $registre, string $sujet, string $besoin, string $proposition): string
    {
        $requete = $registre->prepare(
            "SELECT p.auteur_reference, a.proposant_reference
             FROM proposition_aide_gamad a JOIN publication_gamad p ON p.reference = a.besoin_reference
             WHERE a.reference = ? AND a.besoin_reference = ? AND a.etat = 'accepted'
               AND (? = p.auteur_reference OR ? = a.proposant_reference)"
        );
        $requete->execute([$proposition, $besoin, $sujet, $sujet]);
        $participants = $requete->fetch();
        $requete->closeCursor();
        abort_if($participants === false, 404);

        return $participants['auteur_reference'] === $sujet
            ? $participants['proposant_reference']
            : $participants['auteur_reference'];
    }

    private function autoriser(PDO $registre, string $sujet, string $cible): void
    {
        abort_if($this->membres->lire($sujet, $registre) === null
            || $this->membres->lire($cible, $registre) === null
            || isset($this->blocages->exclus($registre, $sujet)[$cible]), 404);
    }
}
