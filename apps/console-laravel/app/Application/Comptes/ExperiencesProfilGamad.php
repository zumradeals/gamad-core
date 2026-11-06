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

    private const REALISATION_VISIBLE = "p.type = 'personal_work'
        AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference)";

    public function __construct(private LireCompteGamad $comptes)
    {
    }

    public static function lister(PDO $registre, string $identite, bool $membre = true, bool $gestion = false): array
    {
        $visible = self::REALISATION_VISIBLE;
        $etatLien = $gestion ? ', CASE WHEN lien.experience_reference IS NULL THEN 0 ELSE 1 END AS preuve_liee' : '';
        $requete = $registre->prepare(
            "SELECT e.reference, e.titre, e.type, e.periode, e.description, p.reference AS preuve{$etatLien}
             FROM experience_profil_gamad e
             LEFT JOIN preuve_experience_profil_gamad lien ON lien.experience_reference = e.reference
             LEFT JOIN publication_gamad p ON p.reference = lien.publication_reference
               AND p.auteur_reference = e.identite_reference AND {$visible}
               AND (p.visibilite = 'public' OR CAST(? AS INTEGER) = 1)
             WHERE e.identite_reference = ? ORDER BY e.cree_le, e.reference"
        );
        $requete->execute([$membre ? 1 : 0, $identite]);

        return $requete->fetchAll();
    }

    public function realisations(string $identite): array
    {
        $registre = Magasin::ouvrir();
        $visible = self::REALISATION_VISIBLE;
        $requete = $registre->prepare(
            "SELECT p.reference, p.contenu FROM publication_gamad p
             WHERE p.auteur_reference = ? AND {$visible} ORDER BY p.cree_le DESC, p.reference DESC"
        );
        $requete->execute([$identite]);

        return $requete->fetchAll();
    }

    public function lierPreuve(string $identite, string $experience, string $publication): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->comptes->executer($identite, $registre) === null, 403);
        $visible = self::REALISATION_VISIBLE;
        $requete = $registre->prepare(
            "INSERT INTO preuve_experience_profil_gamad (experience_reference, publication_reference)
             SELECT e.reference, p.reference FROM experience_profil_gamad e
             JOIN publication_gamad p ON p.auteur_reference = e.identite_reference
             WHERE e.reference = ? AND e.identite_reference = ? AND p.reference = ? AND {$visible}
             ON CONFLICT (experience_reference) DO UPDATE SET publication_reference = excluded.publication_reference"
        );
        $requete->execute([$experience, $identite, $publication]);
        abort_if($requete->rowCount() !== 1, 404);
    }

    public function retirerPreuve(string $identite, string $experience): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->comptes->executer($identite, $registre) === null, 403);
        $requete = $registre->prepare(
            'DELETE FROM preuve_experience_profil_gamad WHERE experience_reference IN (
                SELECT reference FROM experience_profil_gamad WHERE reference = ? AND identite_reference = ?
            )'
        );
        $requete->execute([$experience, $identite]);
        abort_if($requete->rowCount() !== 1, 404);
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
