<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Validation\ValidationException;
use PDO;

final class ExperiencesProfilGamad
{
    public const TYPES = [
        'personal' => 'Personnelle',
        'professional' => 'Professionnelle',
        'volunteer' => 'Bénévole',
        'learning' => 'Apprentissage',
    ];

    public function __construct(private LireCompteGamad $comptes)
    {
    }

    public static function lister(PDO $registre, string $identite): array
    {
        $requete = $registre->prepare(
            'SELECT reference, titre, type, periode, description FROM experience_profil_gamad
             WHERE identite_reference = ? ORDER BY cree_le, reference'
        );
        $requete->execute([$identite]);

        return $requete->fetchAll();
    }

    public function enregistrer(string $identite, ?string $reference, array $donnees): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->comptes->executer($identite, $registre) === null, 403);
        $titre = Normalizer::display($donnees['titre']);
        if ($titre === '') {
            throw ValidationException::withMessages(['titre' => 'Indiquez un titre d’expérience.']);
        }
        $valeurs = [
            $titre,
            $donnees['type'],
            Normalizer::display($donnees['periode'] ?? ''),
            Normalizer::multiline($donnees['description'] ?? ''),
        ];

        if ($reference !== null) {
            $requete = $registre->prepare(
                'UPDATE experience_profil_gamad SET titre = ?, type = ?, periode = ?, description = ?
                 WHERE reference = ? AND identite_reference = ?'
            );
            $requete->execute([...$valeurs, $reference, $identite]);
            abort_if($requete->rowCount() !== 1, 404);

            return;
        }

        $registre->beginTransaction();
        try {
            // Sérialise les créations d’expérience concurrentes sur la même fiche.
            $verrou = $registre->prepare('UPDATE fiche_annuaire_gamad SET visibilite = visibilite WHERE identite_reference = ?');
            $verrou->execute([$identite]);
            abort_if($verrou->rowCount() !== 1, 404);
            $compte = $registre->prepare('SELECT COUNT(*) FROM experience_profil_gamad WHERE identite_reference = ?');
            $compte->execute([$identite]);
            $nombre = (int) $compte->fetchColumn();
            $compte->closeCursor();
            if ($nombre >= 10) {
                throw ValidationException::withMessages(['titre' => 'Le profil peut contenir dix expériences au maximum.']);
            }
            $registre->prepare(
                'INSERT INTO experience_profil_gamad (reference, identite_reference, titre, type, periode, description, cree_le)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([bin2hex(random_bytes(12)), $identite, ...$valeurs, gmdate('c')]);
            $registre->commit();
        } catch (\Throwable $exception) {
            $registre->rollBack();
            throw $exception;
        }
    }

    public function retirer(string $identite, string $reference): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->comptes->executer($identite, $registre) === null, 403);
        $requete = $registre->prepare('DELETE FROM experience_profil_gamad WHERE reference = ? AND identite_reference = ?');
        $requete->execute([$reference, $identite]);
        abort_if($requete->rowCount() !== 1, 404);
    }
}
