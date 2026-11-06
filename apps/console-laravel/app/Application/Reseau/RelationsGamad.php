<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PDO;

final class RelationsGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function lister(string $sujet): array
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $requete = $registre->prepare(
            "SELECT r.*, i.libelle AS nom, f.reference_publique
             FROM relation_personnes_gamad r
             JOIN identite_inscrite i ON i.reference =
                CASE WHEN r.premiere_reference = ? THEN r.seconde_reference ELSE r.premiere_reference END
             JOIN fiche_annuaire_gamad f ON f.identite_reference = i.reference
             WHERE (r.premiere_reference = ? OR r.seconde_reference = ?)
               AND r.etat IN ('pending', 'accepted') ORDER BY r.modifie_le DESC"
        );
        $requete->execute([$sujet, $sujet, $sujet]);
        $relations = $requete->fetchAll(PDO::FETCH_ASSOC);
        $exclus = $this->blocages->exclus($registre, $sujet);

        return array_values(array_filter($relations, function (array $relation) use ($sujet, $exclus, $registre): bool {
            $cible = $relation['premiere_reference'] === $sujet ? $relation['seconde_reference'] : $relation['premiere_reference'];

            return !isset($exclus[$cible]) && $this->membres->lire($cible, $registre) !== null;
        }));
    }

    public function demander(string $sujet, string $reference, string $note): void
    {
        $note = Normalizer::multiline($note);
        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Expliquez votre envie de faire connaissance.']);
        }

        $limite = 'demande-relation:'.$sujet;
        abort_if(RateLimiter::tooManyAttempts($limite, 20), 429);

        $this->avecPaire($sujet, $reference, function (PDO $registre, array $paire, array $fiche) use ($sujet, $note): void {
            $this->autoriser($registre, $sujet, $fiche);
            $registre->prepare(
                'INSERT INTO profil_compte_gamad (identite_reference) VALUES (?)
                 ON CONFLICT (identite_reference) DO NOTHING'
            )->execute([$sujet]);
            $registre->prepare(
                "INSERT INTO fiche_annuaire_gamad
                    (identite_reference, reference_publique, visibilite, competences_apportees, competences_recherchees)
                 VALUES (?, ?, 'prive', '', '') ON CONFLICT (identite_reference) DO NOTHING"
            )->execute([$sujet, bin2hex(random_bytes(12))]);
            $requete = $registre->prepare(
                "INSERT INTO relation_personnes_gamad
                    (premiere_reference, seconde_reference, expediteur_reference, etat, note, revision, modifie_le)
                 VALUES (?, ?, ?, 'pending', ?, 1, ?)
                 ON CONFLICT (premiere_reference, seconde_reference) DO UPDATE SET
                    expediteur_reference = excluded.expediteur_reference, etat = 'pending', note = excluded.note,
                    revision = relation_personnes_gamad.revision + 1, modifie_le = excluded.modifie_le
                 WHERE relation_personnes_gamad.etat NOT IN ('pending', 'accepted')
                   AND relation_personnes_gamad.modifie_le <= ?"
            );
            $requete->execute([...$paire, $sujet, $note, time(), time() - 7 * 86400]);
            abort_if($requete->rowCount() === 0, 409, 'Une demande existe déjà ou a été traitée récemment.');
            NotificationsGamad::ajouter($registre, $fiche['identite_reference'], $sujet, 'demande');
        });
        RateLimiter::hit($limite, 86400);
    }

    public function decider(string $sujet, string $reference, string $action, int $revision): void
    {
        $this->avecPaire($sujet, $reference, function (PDO $registre, array $paire, array $fiche) use ($sujet, $action, $revision): void {
            $requete = $registre->prepare(
                'SELECT etat, expediteur_reference, revision FROM relation_personnes_gamad
                 WHERE premiere_reference = ? AND seconde_reference = ?'
            );
            $requete->execute($paire);
            $relation = $requete->fetch(PDO::FETCH_ASSOC);
            abort_if($relation === false, 404);
            abort_unless((int) $relation['revision'] === $revision, 409);
            if ($action === 'remove') {
                abort_unless($relation['etat'] === 'accepted', 409);
            } else {
                abort_unless($relation['etat'] === 'pending', 409);
                abort_unless(($action === 'withdraw') === ($relation['expediteur_reference'] === $sujet), 403);
            }

            if ($action === 'accept') {
                $this->autoriser($registre, $sujet, $fiche, exigerVisibilite: false);
            }
            $etat = match ($action) {
                'accept' => 'accepted',
                'decline' => 'declined',
                'withdraw' => 'withdrawn',
                'remove' => 'removed',
            };
            $registre->prepare(
                'UPDATE relation_personnes_gamad SET etat = ?, note = NULL, revision = revision + 1, modifie_le = ?
                 WHERE premiere_reference = ? AND seconde_reference = ?'
            )->execute([$etat, time(), ...$paire]);
            if ($etat === 'accepted') {
                NotificationsGamad::ajouter($registre, $fiche['identite_reference'], $sujet, 'acceptation');
            }
        });
    }

    private function avecPaire(string $sujet, string $reference, callable $operation): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $requete = $registre->prepare(
            'SELECT identite_reference, visibilite FROM fiche_annuaire_gamad WHERE reference_publique = ?'
        );
        $requete->execute([$reference]);
        $fiche = $requete->fetch(PDO::FETCH_ASSOC);
        $requete->closeCursor();
        abort_if($fiche === false || $fiche['identite_reference'] === $sujet, 404);

        PaireGamad::verrouiller($registre, $sujet, $fiche['identite_reference'], function (array $paire) use ($registre, $reference, $requete, $operation): void {
            $requete->execute([$reference]);
            $fiche = $requete->fetch(PDO::FETCH_ASSOC);
            $requete->closeCursor();
            abort_if($fiche === false, 404);
            $operation($registre, $paire, $fiche);
        });
    }

    private function autoriser(PDO $registre, string $sujet, array $fiche, bool $exigerVisibilite = true): void
    {
        $cible = $fiche['identite_reference'];
        abort_if(
            ($exigerVisibilite && $fiche['visibilite'] === 'prive')
            || isset($this->blocages->exclus($registre, $sujet)[$cible])
            || $this->membres->lire($cible, $registre) === null,
            404,
        );
    }
}
