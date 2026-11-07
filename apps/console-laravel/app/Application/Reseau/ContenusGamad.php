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
        'learning_wish' => 'Je veux apprendre',
        'offer' => 'Je propose mon aide',
    ];

    public const TYPE_BESOIN = 'need';

    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    /** Lecture privée des publications personnelles, sans contenu masqué par la modération. */
    public function personnelles(string $sujet): array
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $filtre = "auteur_reference = ? AND type <> 'need' AND NOT EXISTS (
            SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = publication_gamad.reference)";
        $total = $registre->prepare("SELECT COUNT(*) FROM publication_gamad WHERE {$filtre}");
        $total->execute([$sujet]);
        $nombre = (int) $total->fetchColumn();
        $requete = $registre->prepare("SELECT reference, type, contenu, visibilite, cree_le FROM publication_gamad WHERE {$filtre} ORDER BY cree_le DESC, reference DESC LIMIT 20");
        $requete->execute([$sujet]);

        return ['total' => $nombre, 'publications' => $requete->fetchAll(PDO::FETCH_ASSOC)];
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
        $publication = $this->lirePublication($registre, $reference, $membre['identite_reference'] ?? null, $exclus);
        if ($publication === null) {
            return null;
        }

        $requete = $registre->prepare(
            "SELECT c.*, r.parent_reference, parent.auteur_reference AS auteur_parent, retrait.retire_le
             FROM commentaire_gamad c
             LEFT JOIN reponse_commentaire_gamad r ON r.reponse_reference = c.reference
             LEFT JOIN commentaire_gamad parent ON parent.reference = r.parent_reference
             LEFT JOIN retrait_commentaire_gamad retrait ON retrait.commentaire_reference = c.reference
             WHERE c.publication_reference = ?
               AND NOT EXISTS (
                   SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'commentaire'
                   AND (m.cible_reference = c.reference OR m.cible_reference = r.parent_reference)
               )
             ORDER BY COALESCE(parent.cree_le, c.cree_le) DESC,
                      COALESCE(r.parent_reference, c.reference) DESC,
                      CASE WHEN r.parent_reference IS NULL THEN 0 ELSE 1 END,
                      c.cree_le, c.reference"
        );
        $requete->execute([$reference]);

        $commentaires = [];
        foreach ($this->parcourirAffichables($requete, $registre, $exclus) as $commentaire) {
            $parent = $commentaire['auteur_parent'];
            if ($parent !== null && (isset($exclus[$parent]) || $this->membres->lire($parent, $registre) === null)) {
                continue;
            }
            unset($commentaire['auteur_parent']);
            if ($commentaire['retire_le'] !== null) {
                $commentaire['contenu'] = null;
            }
            $commentaires[] = $commentaire;
            if (count($commentaires) === 50) {
                break;
            }
        }

        return [
            'publication' => $publication,
            'commentaires' => $commentaires,
            'peut_commenter' => $membre !== null,
            'sujet_besoin' => $membre['identite_reference'] ?? null,
            'gere_besoin' => $publication['type'] === self::TYPE_BESOIN && $membre !== null
                && EmetteurBesoinGamad::peutGerer($registre, $membre['identite_reference'], $reference),
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

    public function publierBesoin(string $sujet, string $visibilite, string $contenu, ?string $domaine, ?string $territoire, ?string $zumra, ?string $projet): ?string
    {
        $registre = Magasin::ouvrir();
        if ($this->membres->lire($sujet, $registre) === null) {
            return null;
        }

        $registre->beginTransaction();
        try {
            abort_unless(EmetteurBesoinGamad::peutPublier($registre, $sujet, $zumra, $projet), 404);
            $interne = $visibilite === 'zumra';
            abort_if($interne && $zumra === null && $projet === null, 422);
            $reference = $this->insererPublication($registre, $sujet, self::TYPE_BESOIN, $interne ? 'members' : $visibilite, $contenu);
            if ($interne) {
                $registre->prepare('INSERT INTO suivi_besoin_gamad (besoin_reference, interne) VALUES (?, 1)')->execute([$reference]);
            }
            if ($domaine !== null || $territoire !== null) {
                $registre->prepare(
                    'INSERT INTO classement_besoin_gamad (besoin_reference, domaine, territoire) VALUES (?, ?, ?)'
                )->execute([$reference, $domaine, $territoire]);
            }
            if ($zumra !== null || $projet !== null) {
                $registre->prepare(
                    'INSERT INTO emetteur_besoin_gamad (besoin_reference, zumra_reference, projet_reference) VALUES (?, ?, ?)'
                )->execute([$reference, $zumra, $projet]);
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

    public function besoins(?string $session, string $etat, string $recherche, int $page, ?string $domaine, ?string $territoire, ?string $emetteur): array
    {
        $registre = Magasin::ouvrir();
        $membre = $this->membres->depuisSession($session, $registre);
        $exclus = $this->blocages->exclus($registre, $membre['identite_reference'] ?? null);
        $jointures = EmetteurBesoinGamad::JOINTURES;
        $visible = EmetteurBesoinGamad::VISIBLE;
        $type = EmetteurBesoinGamad::TYPE;
        $requete = $registre->prepare(
            "SELECT p.*, c.domaine, c.territoire, {$type} AS emetteur,
                    COALESCE(e.projet_reference, e.zumra_reference) AS emetteur_reference,
                    COALESCE(pr.titre, z.nom) AS emetteur_nom, sb.interne, sb.bilan, z.reference AS zumra_interne, z.fondateur_reference FROM publication_gamad p
             {$jointures}
             LEFT JOIN classement_besoin_gamad c ON c.besoin_reference = p.reference
             WHERE {$visible} AND (CAST(? AS TEXT) IS NULL OR {$type} = ?)
               AND (CAST(? AS TEXT) IS NULL OR c.domaine = ?)
               AND (CAST(? AS TEXT) IS NULL OR c.territoire = ?)
               AND p.type = 'need' AND (p.etat_besoin = ? OR ? = 'tous')
               AND (p.visibilite = 'public' OR CAST(? AS INTEGER) = 1)
               AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference)
             ORDER BY p.cree_le DESC, p.reference DESC"
        );
        $requete->execute([$emetteur, $emetteur, $domaine, $domaine, $territoire, $territoire, $etat, $etat, $membre === null ? 0 : 1]);
        $correspondances = (static function () use ($requete, $recherche, $registre, $membre): \Generator {
            foreach ($requete as $ligne) {
                if (($recherche === '' || str_contains(Normalizer::key($ligne['contenu']), $recherche))
                    && EmetteurBesoinGamad::voitInterne($registre, $membre['identite_reference'] ?? null, $ligne)) {
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
        $publication = $this->lirePublication($registre, $reference, $membre['identite_reference'] ?? null, $exclus);

        return ($publication['type'] ?? null) === self::TYPE_BESOIN ? $publication : null;
    }

    public function terminerBesoin(string $sujet, string $reference, bool $resolu): bool
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);

        return PaireGamad::verrouiller($registre, 'besoin', $reference, function () use ($registre, $sujet, $reference, $resolu): bool {
            if (!EmetteurBesoinGamad::peutGerer($registre, $sujet, $reference)) {
                return false;
            }

            $requete = $registre->prepare(
                "UPDATE publication_gamad SET etat_besoin = ?
                 WHERE reference = ? AND type = 'need' AND etat_besoin IN ('open', 'in_progress')
                   AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad WHERE categorie = 'publication' AND cible_reference = ?)"
            );
            $requete->execute([$resolu ? 'resolved' : 'closed', $reference, $reference]);
            if ($requete->rowCount() !== 1) {
                return false;
            }

            $registre->prepare("UPDATE proposition_aide_gamad SET etat = 'declined' WHERE besoin_reference = ? AND etat = 'pending'")
                ->execute([$reference]);

            return true;
        });
    }

    public function enregistrerBilan(string $sujet, string $reference, ?string $bilan): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        PaireGamad::verrouiller($registre, 'besoin', $reference, function () use ($registre, $sujet, $reference, $bilan): void {
            abort_unless(EmetteurBesoinGamad::peutGerer($registre, $sujet, $reference), 404);
            $bilan = Normalizer::multiline($bilan ?? '');
            $requete = $registre->prepare(
                "INSERT INTO suivi_besoin_gamad (besoin_reference, bilan)
                 SELECT reference, ? FROM publication_gamad
                 WHERE reference = ? AND etat_besoin = 'resolved'
                   AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad WHERE categorie = 'publication' AND cible_reference = ?)
                 ON CONFLICT (besoin_reference) DO UPDATE SET bilan = excluded.bilan"
            );
            $requete->execute([$bilan === '' ? null : $bilan, $reference, $reference]);
            abort_unless($requete->rowCount() === 1, 404);
        });
    }

    public function commenter(string $sujet, string $publication, string $contenu, ?string $parent = null): bool
    {
        $registre = Magasin::ouvrir();
        $exclus = $this->blocages->exclus($registre, $sujet);
        if ($this->membres->lire($sujet, $registre) === null
            || $this->lirePublication($registre, $publication, $sujet, $exclus) === null) {
            return false;
        }

        $reference = bin2hex(random_bytes(12));
        $contenu = $this->texte($contenu);
        return PaireGamad::verrouiller($registre, 'commentaire', $publication, function () use ($registre, $reference, $sujet, $publication, $contenu, $parent, $exclus): bool {
            if ($parent !== null) {
                $requete = $registre->prepare(
                    "SELECT c.auteur_reference FROM commentaire_gamad c
                     WHERE c.reference = ? AND c.publication_reference = ?
                       AND NOT EXISTS (SELECT 1 FROM reponse_commentaire_gamad r WHERE r.reponse_reference = c.reference)
                       AND NOT EXISTS (SELECT 1 FROM retrait_commentaire_gamad retrait WHERE retrait.commentaire_reference = c.reference)
                       AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'commentaire' AND m.cible_reference = c.reference)"
                );
                $requete->execute([$parent, $publication]);
                $auteur = $requete->fetchColumn();
                $requete->closeCursor();
                if (!is_string($auteur) || isset($exclus[$auteur]) || $this->membres->lire($auteur, $registre) === null) {
                    return false;
                }
            }

            $registre->prepare(
                'INSERT INTO commentaire_gamad (reference, publication_reference, auteur_reference, contenu, cree_le) VALUES (?, ?, ?, ?, ?)'
            )->execute([$reference, $publication, $sujet, $contenu, gmdate('c')]);
            if ($parent !== null) {
                $registre->prepare('INSERT INTO reponse_commentaire_gamad (reponse_reference, parent_reference) VALUES (?, ?)')
                    ->execute([$reference, $parent]);
            }

            return true;
        });
    }

    public function modifierCommentaire(string $sujet, string $publication, string $commentaire, string $contenu): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        abort_if($this->lirePublication($registre, $publication, $sujet, $this->blocages->exclus($registre, $sujet)) === null, 404);

        PaireGamad::verrouiller($registre, 'commentaire', $publication, function () use ($registre, $sujet, $publication, $commentaire, $contenu): void {
            $requete = $registre->prepare(
                "UPDATE commentaire_gamad SET contenu = ?
                 WHERE reference = ? AND publication_reference = ? AND auteur_reference = ?
                   AND NOT EXISTS (SELECT 1 FROM retrait_commentaire_gamad WHERE commentaire_reference = ?)
                   AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad WHERE categorie = 'commentaire' AND cible_reference = ?)
                   AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad WHERE categorie = 'publication' AND cible_reference = ?)"
            );
            $requete->execute([$this->texte($contenu), $commentaire, $publication, $sujet, $commentaire, $commentaire, $publication]);
            abort_if($requete->rowCount() === 0, 404);
        });
    }

    public function retirerCommentaire(string $sujet, string $publication, string $commentaire): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        abort_if($this->lirePublication($registre, $publication, $sujet, $this->blocages->exclus($registre, $sujet)) === null, 404);

        PaireGamad::verrouiller($registre, 'commentaire', $publication, function () use ($registre, $sujet, $publication, $commentaire): void {
            $requete = $registre->prepare(
                'INSERT INTO retrait_commentaire_gamad (commentaire_reference, retire_le)
                 SELECT reference, ? FROM commentaire_gamad
                 WHERE reference = ? AND publication_reference = ? AND auteur_reference = ?
                 ON CONFLICT (commentaire_reference) DO NOTHING'
            );
            $requete->execute([gmdate('c'), $commentaire, $publication, $sujet]);
            abort_if($requete->rowCount() === 0, 404);
            $registre->prepare('UPDATE commentaire_gamad SET contenu = ? WHERE reference = ?')
                ->execute(['Commentaire retiré.', $commentaire]);
        });
    }

    public function favoris(string $sujet): array
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $requete = $registre->prepare(
            "SELECT p.* FROM favori_publication_gamad f JOIN publication_gamad p ON p.reference = f.publication_reference
             WHERE f.membre_reference = ?
               AND NOT EXISTS (SELECT 1 FROM emetteur_besoin_gamad e WHERE e.besoin_reference = p.reference)
               AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference)
             ORDER BY f.cree_le DESC, f.publication_reference DESC"
        );
        $requete->execute([$sujet]);
        $exclus = $this->blocages->exclus($registre, $sujet);
        $favoris = [];
        foreach ($this->parcourirAffichables($requete, $registre, $exclus) as $publication) {
            $favoris[] = $publication;
            if (count($favoris) === 100) {
                break;
            }
        }

        return $favoris;
    }

    public function modifierFavori(string $sujet, string $reference, bool $ajouter): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        if (!$ajouter) {
            $registre->prepare('DELETE FROM favori_publication_gamad WHERE membre_reference = ? AND publication_reference = ?')
                ->execute([$sujet, $reference]);

            return;
        }

        $publication = $this->lirePublication($registre, $reference, $sujet, $this->blocages->exclus($registre, $sujet));
        abort_if($publication === null || $publication['emetteur'] !== 'personne', 404);
        $registre->prepare(
            'INSERT INTO favori_publication_gamad (membre_reference, publication_reference, cree_le)
             VALUES (?, ?, ?) ON CONFLICT (membre_reference, publication_reference) DO NOTHING'
        )->execute([$sujet, $reference, gmdate('c')]);
    }

    private function lirePublication(PDO $registre, string $reference, ?string $sujet, array $exclus): ?array
    {
        $jointures = EmetteurBesoinGamad::JOINTURES;
        $visible = EmetteurBesoinGamad::VISIBLE;
        $type = EmetteurBesoinGamad::TYPE;
        $requete = $registre->prepare("SELECT p.*, c.domaine, c.territoire, {$type} AS emetteur,
            COALESCE(e.projet_reference, e.zumra_reference) AS emetteur_reference,
            COALESCE(pr.titre, z.nom) AS emetteur_nom, sb.interne, sb.bilan, z.reference AS zumra_interne, z.fondateur_reference FROM publication_gamad p {$jointures}
            LEFT JOIN classement_besoin_gamad c ON c.besoin_reference = p.reference
            WHERE p.reference = ? AND {$visible} AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference)");
        $requete->execute([$reference]);
        $publication = $requete->fetch(PDO::FETCH_ASSOC);
        if ($publication === false || ($sujet === null && $publication['visibilite'] !== 'public')
            || !EmetteurBesoinGamad::voitInterne($registre, $sujet, $publication)) {
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
                if ((int) ($ligne['interne'] ?? 0) === 1) {
                    $ligne['visibilite'] = 'zumra';
                }
                unset($ligne['auteur_reference'], $ligne['interne'], $ligne['zumra_interne'], $ligne['fondateur_reference']);
                yield $ligne + ['auteur' => $ligne['emetteur_nom'] ?? $auteurs[$reference]['nom']];
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
