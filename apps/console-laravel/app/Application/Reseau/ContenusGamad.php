<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Validation\ValidationException;
use PDO;

final class ContenusGamad
{
    public const TYPES = [
        'share' => 'Je partage',
        'knowledge' => 'Je partage un savoir',
        'personal_work' => 'Ma réalisation',
    ];

    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function fil(string $sujet, int $page): ?array
    {
        $registre = Magasin::ouvrir();
        if ($this->membres->lire($sujet, $registre) === null) {
            return null;
        }

        $requete = $registre->prepare(
            "SELECT p.* FROM publication_gamad p WHERE NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference) ORDER BY cree_le DESC, reference DESC LIMIT 21 OFFSET ?"
        );
        $requete->bindValue(1, ($page - 1) * 20, PDO::PARAM_INT);
        $requete->execute();
        $publications = $requete->fetchAll(PDO::FETCH_ASSOC);

        return [
            'publications' => $this->affichables(array_slice($publications, 0, 20), $registre, $this->blocages->exclus($registre, $sujet)),
            'page_suivante' => count($publications) > 20,
        ];
    }

    public function publication(string $reference, ?string $session): ?array
    {
        $registre = Magasin::ouvrir();
        $membre = $this->membres->depuisSession($session, $registre);
        $exclus = $this->blocages->exclus($registre, $membre['identite_reference'] ?? null);
        $publication = $this->lirePublication($registre, $reference, $membre !== null, $exclus);
        if ($publication === null) {
            return null;
        }

        $requete = $registre->prepare(
            "SELECT c.* FROM commentaire_gamad c WHERE publication_reference = ? AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'commentaire' AND m.cible_reference = c.reference) ORDER BY cree_le DESC, reference DESC LIMIT 50"
        );
        $requete->execute([$reference]);

        return [
            'publication' => $publication,
            'commentaires' => $this->affichables($requete->fetchAll(PDO::FETCH_ASSOC), $registre, $exclus),
            'peut_commenter' => $membre !== null,
        ];
    }

    public function publier(string $sujet, string $type, string $visibilite, string $contenu): ?string
    {
        $registre = Magasin::ouvrir();
        if ($this->membres->lire($sujet, $registre) === null) {
            return null;
        }

        $reference = bin2hex(random_bytes(12));
        $registre->prepare(
            'INSERT INTO publication_gamad (reference, auteur_reference, type, visibilite, contenu, cree_le) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$reference, $sujet, $type, $visibilite, $this->texte($contenu), gmdate('c')]);

        return $reference;
    }

    public function commenter(string $sujet, string $publication, string $contenu): bool
    {
        $registre = Magasin::ouvrir();
        if ($this->membres->lire($sujet, $registre) === null
            || $this->lirePublication($registre, $publication, true, $this->blocages->exclus($registre, $sujet)) === null) {
            return false;
        }

        return $registre->prepare(
            'INSERT INTO commentaire_gamad (reference, publication_reference, auteur_reference, contenu, cree_le) VALUES (?, ?, ?, ?, ?)'
        )->execute([bin2hex(random_bytes(12)), $publication, $sujet, $this->texte($contenu), gmdate('c')]);
    }

    private function lirePublication(PDO $registre, string $reference, bool $membre, array $exclus): ?array
    {
        $requete = $registre->prepare("SELECT p.* FROM publication_gamad p WHERE p.reference = ? AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference)");
        $requete->execute([$reference]);
        $publication = $requete->fetch(PDO::FETCH_ASSOC);
        if ($publication === false || (!$membre && $publication['visibilite'] !== 'public')) {
            return null;
        }

        return $this->affichables([$publication], $registre, $exclus)[0] ?? null;
    }

    private function affichables(array $lignes, PDO $registre, array $exclus): array
    {
        $resultat = [];
        $auteurs = [];
        foreach ($lignes as $ligne) {
            $reference = $ligne['auteur_reference'];
            if (isset($exclus[$reference])) {
                continue;
            }
            if (!array_key_exists($reference, $auteurs)) {
                $auteurs[$reference] = $this->membres->lire($reference, $registre);
            }
            if ($auteurs[$reference] !== null) {
                unset($ligne['auteur_reference']);
                $resultat[] = $ligne + ['auteur' => $auteurs[$reference]['nom']];
            }
        }

        return $resultat;
    }

    private function texte(string $contenu): string
    {
        $texte = Normalizer::multiline($contenu);
        if ($texte === '') {
            throw ValidationException::withMessages(['contenu' => 'Saisissez un texte.']);
        }

        return $texte;
    }
}
