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

    public const TYPE_BESOIN = 'need';

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
            "SELECT p.* FROM publication_gamad p WHERE p.type <> 'need' AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference) ORDER BY cree_le DESC, reference DESC LIMIT 21 OFFSET ?"
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

        return $this->insererPublication($registre, $sujet, $type, $visibilite, $contenu);
    }

    public function publierBesoin(string $sujet, string $visibilite, string $contenu, ?string $domaine, ?string $territoire): ?string
    {
        $registre = Magasin::ouvrir();
        if ($this->membres->lire($sujet, $registre) === null) {
            return null;
        }

        $registre->beginTransaction();
        try {
            $reference = $this->insererPublication($registre, $sujet, self::TYPE_BESOIN, $visibilite, $contenu);
            if ($domaine !== null || $territoire !== null) {
                $registre->prepare(
                    'INSERT INTO classement_besoin_gamad (besoin_reference, domaine, territoire) VALUES (?, ?, ?)'
                )->execute([$reference, $domaine, $territoire]);
            }
            $registre->commit();

            return $reference;
        } catch (\Throwable $exception) {
            $registre->rollBack();
            throw $exception;
        }
    }

    private function insererPublication(PDO $registre, string $sujet, string $type, string $visibilite, string $contenu): string
    {
        $reference = bin2hex(random_bytes(12));
        $registre->prepare(
            'INSERT INTO publication_gamad (reference, auteur_reference, type, visibilite, contenu, cree_le, etat_besoin) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$reference, $sujet, $type, $visibilite, $this->texte($contenu), gmdate('c'), $type === self::TYPE_BESOIN ? 'open' : null]);

        return $reference;
    }

    public function besoins(?string $session, string $etat, string $recherche, int $page, ?string $domaine, ?string $territoire): array
    {
        $registre = Magasin::ouvrir();
        $membre = $this->membres->depuisSession($session, $registre);
        $exclus = $this->blocages->exclus($registre, $membre['identite_reference'] ?? null);
        $requete = $registre->prepare(
            "SELECT p.*, c.domaine, c.territoire FROM publication_gamad p
             LEFT JOIN classement_besoin_gamad c ON c.besoin_reference = p.reference
             WHERE (CAST(? AS TEXT) IS NULL OR c.domaine = ?)
               AND (CAST(? AS TEXT) IS NULL OR c.territoire = ?)
               AND p.type = 'need' AND (p.etat_besoin = ? OR ? = 'tous')
               AND (p.visibilite = 'public' OR CAST(? AS INTEGER) = 1)
               AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference)
             ORDER BY p.cree_le DESC, p.reference DESC"
        );
        $requete->execute([$domaine, $domaine, $territoire, $territoire, $etat, $etat, $membre === null ? 0 : 1]);
        $correspondances = (static function () use ($requete, $recherche): \Generator {
            foreach ($requete as $ligne) {
                if ($recherche === '' || str_contains(Normalizer::key($ligne['contenu']), $recherche)) {
                    yield $ligne;
                }
            }
        })();

        $besoins = [];
        $ignorer = ($page - 1) * 20;
        foreach ($this->parcourirAffichables($correspondances, $registre, $exclus) as $besoin) {
            if ($ignorer > 0) {
                --$ignorer;
                continue;
            }
            if (count($besoins) === 20) {
                return ['besoins' => $besoins, 'page_suivante' => true];
            }
            $besoins[] = $besoin;
        }

        return ['besoins' => $besoins, 'page_suivante' => false];
    }

    public function besoin(string $reference, ?string $session): ?array
    {
        $registre = Magasin::ouvrir();
        $membre = $this->membres->depuisSession($session, $registre);
        $exclus = $this->blocages->exclus($registre, $membre['identite_reference'] ?? null);
        $publication = $this->lirePublication($registre, $reference, $membre !== null, $exclus);

        return ($publication['type'] ?? null) === self::TYPE_BESOIN ? $publication : null;
    }

    public function terminerBesoin(string $sujet, string $reference, bool $resolu): bool
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);

        $requete = $registre->prepare(
            "UPDATE publication_gamad SET etat_besoin = ?
             WHERE reference = ? AND auteur_reference = ? AND type = 'need'
               AND etat_besoin IN ('open', 'in_progress')
               AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad WHERE categorie = 'publication' AND cible_reference = ?)"
        );
        $requete->execute([$resolu ? 'resolved' : 'closed', $reference, $sujet, $reference]);

        return $requete->rowCount() === 1;
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

    public function modifierCommentaire(string $sujet, string $publication, string $commentaire, string $contenu): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        abort_if($this->lirePublication($registre, $publication, true, $this->blocages->exclus($registre, $sujet)) === null, 404);

        $requete = $registre->prepare(
            "UPDATE commentaire_gamad SET contenu = ?
             WHERE reference = ? AND publication_reference = ? AND auteur_reference = ?
               AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad WHERE categorie = 'commentaire' AND cible_reference = ?)
               AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad WHERE categorie = 'publication' AND cible_reference = ?)"
        );
        $requete->execute([$this->texte($contenu), $commentaire, $publication, $sujet, $commentaire, $publication]);
        abort_if($requete->rowCount() === 0, 404);
    }

    private function lirePublication(PDO $registre, string $reference, bool $membre, array $exclus): ?array
    {
        $requete = $registre->prepare("SELECT p.*, c.domaine, c.territoire FROM publication_gamad p LEFT JOIN classement_besoin_gamad c ON c.besoin_reference = p.reference WHERE p.reference = ? AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference)");
        $requete->execute([$reference]);
        $publication = $requete->fetch(PDO::FETCH_ASSOC);
        if ($publication === false || (!$membre && $publication['visibilite'] !== 'public')) {
            return null;
        }

        return $this->affichables([$publication], $registre, $exclus)[0] ?? null;
    }

    private function affichables(array $lignes, PDO $registre, array $exclus): array
    {
        return iterator_to_array($this->parcourirAffichables($lignes, $registre, $exclus), false);
    }

    private function parcourirAffichables(iterable $lignes, PDO $registre, array $exclus): \Generator
    {
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
                yield $ligne + ['auteur' => $auteurs[$reference]['nom']];
            }
        }
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
