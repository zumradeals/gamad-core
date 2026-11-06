<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Validation\ValidationException;
use PDO;

final class ModerationAttestationsGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function lister(): array
    {
        $registre = Magasin::ouvrir();

        return $registre->query(
            "SELECT a.reference, a.publication_reference, a.revision, a.affirmation,
                    CASE WHEN s.attestation_reference IS NULL THEN a.etat ELSE 'revoked' END AS etat,
                    s.motif, s.recours, s.recours_clos
             FROM attestation_realisation_gamad a
             LEFT JOIN suspension_attestation_gamad s ON s.attestation_reference = a.reference
             WHERE a.etat IN ('pending', 'confirmed') OR s.attestation_reference IS NOT NULL
             ORDER BY CASE WHEN s.recours IS NOT NULL AND s.recours_clos = 0 THEN 0 ELSE 1 END, a.modifie_le DESC, a.reference DESC LIMIT 100"
        )->fetchAll();
    }

    public function contester(string $acteur, string $reference, int $revision, string $motif): void
    {
        $this->agir($acteur, $reference, $revision, 'contester', $motif);
    }

    public function decider(string $acteur, string $reference, int $revision, string $action, string $motif): void
    {
        abort_unless(in_array($action, ['revoquer', 'reexaminer', 'rejeter'], true), 422);
        $this->agir($acteur, $reference, $revision, $action, $motif);
    }

    private function agir(string $acteur, string $reference, int $revision, string $action, string $motif): void
    {
        $registre = Magasin::ouvrir();
        $motif = Normalizer::multiline($motif);
        if ($motif === '') {
            throw ValidationException::withMessages(['motif' => 'Indiquez un motif.']);
        }

        $requete = $registre->prepare('SELECT publication_reference FROM attestation_realisation_gamad WHERE reference = ?');
        $requete->execute([$reference]);
        $publication = $requete->fetchColumn();
        $requete->closeCursor();
        abort_unless(is_string($publication), 404);

        PaireGamad::verrouiller($registre, 'attestation', $publication, function () use ($registre, $acteur, $reference, $revision, $action, $motif): void {
            $requete = $registre->prepare(
                'SELECT a.*, s.attestation_reference AS suspension, s.recours, s.recours_clos
                 FROM attestation_realisation_gamad a LEFT JOIN suspension_attestation_gamad s ON s.attestation_reference = a.reference
                 WHERE a.reference = ?'
            );
            $requete->execute([$reference]);
            $ligne = $requete->fetch();
            $requete->closeCursor();
            abort_if($ligne === false, 404);
            $participant = in_array($acteur, [$ligne['auteur_reference'], $ligne['attestant_reference']], true);
            if ($action === 'contester') {
                abort_if(!$participant || $this->membres->lire($acteur, $registre) === null, 404);
            } else {
                abort_if($participant, 403);
            }
            abort_if((int) $ligne['revision'] !== $revision, 409);

            $etat = $ligne['etat'];
            if ($action === 'revoquer') {
                abort_if($ligne['suspension'] !== null || !in_array($etat, ['pending', 'confirmed'], true), 409);
                $registre->prepare('INSERT INTO suspension_attestation_gamad (attestation_reference, motif) VALUES (?, ?)')
                    ->execute([$reference, $motif]);
            } elseif ($action === 'contester') {
                abort_if($ligne['suspension'] === null || $ligne['recours'] !== null, 409);
                $registre->prepare('UPDATE suspension_attestation_gamad SET recours = ? WHERE attestation_reference = ?')
                    ->execute([$motif, $reference]);
            } elseif ($action === 'rejeter') {
                abort_if($ligne['recours'] === null || (int) $ligne['recours_clos'] !== 0, 409);
                $registre->prepare('UPDATE suspension_attestation_gamad SET recours_clos = 1 WHERE attestation_reference = ?')
                    ->execute([$reference]);
            } else {
                abort_if($ligne['recours'] === null || (int) $ligne['recours_clos'] !== 0 || $etat === 'withdrawn'
                    || isset($this->blocages->exclus($registre, $ligne['auteur_reference'])[$ligne['attestant_reference']]), 409);
                $registre->prepare('DELETE FROM suspension_attestation_gamad WHERE attestation_reference = ?')->execute([$reference]);
                $etat = 'pending';
            }

            $miseAJour = $registre->prepare(
                'UPDATE attestation_realisation_gamad SET etat = ?, role = NULL, assurance_evenement = NULL,
                 revision = revision + 1, modifie_le = ? WHERE reference = ? AND revision = ?'
            );
            $miseAJour->execute([$etat, time(), $reference, $revision]);
            abort_if($miseAJour->rowCount() !== 1, 409);
            $registre->prepare(
                'INSERT INTO evenement_moderation_attestation_gamad (attestation_reference, revision, acteur_reference, action, motif, cree_le)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$reference, $revision + 1, $acteur, $action, $motif, gmdate('c')]);
        });
    }
}
