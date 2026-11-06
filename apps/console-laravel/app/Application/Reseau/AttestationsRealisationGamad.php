<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Illuminate\Validation\ValidationException;
use PDO;

final class AttestationsRealisationGamad
{
    public const ROLES = ['client', 'colleague', 'witness'];

    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function demander(string $sujet, string $publication, string $profil, string $affirmation, string $date): string
    {
        $registre = Magasin::ouvrir();
        $requete = $registre->prepare("SELECT identite_reference FROM fiche_annuaire_gamad WHERE reference_publique = ? AND visibilite <> 'prive'");
        $requete->execute([$profil]);
        $attestant = $requete->fetchColumn();
        $requete->closeCursor();
        abort_unless(is_string($attestant) && $attestant !== $sujet, 404);

        $affirmation = Normalizer::multiline($affirmation);
        if ($affirmation === '') {
            throw ValidationException::withMessages(['affirmation' => 'Décrivez la réalisation à attester.']);
        }

        return PaireGamad::verrouiller($registre, 'attestation', $publication, function () use ($registre, $sujet, $attestant, $publication, $affirmation, $date): string {
            $this->autoriser($registre, $sujet, $attestant, $publication);
            $compte = $registre->prepare("SELECT COUNT(*) FROM attestation_realisation_gamad WHERE publication_reference = ? AND etat IN ('pending', 'confirmed')");
            $compte->execute([$publication]);
            $nombre = (int) $compte->fetchColumn();
            $compte->closeCursor();
            abort_if($nombre >= 3, 409);

            $reference = bin2hex(random_bytes(12));
            $requete = $registre->prepare(
                "INSERT INTO attestation_realisation_gamad
                    (reference, publication_reference, auteur_reference, attestant_reference, affirmation, realise_le, etat, revision, modifie_le)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', 1, ?)
                 ON CONFLICT (publication_reference, attestant_reference) DO UPDATE
                 SET affirmation = excluded.affirmation, realise_le = excluded.realise_le, etat = 'pending',
                     role = NULL, assurance_evenement = NULL, revision = attestation_realisation_gamad.revision + 1, modifie_le = excluded.modifie_le
                 WHERE attestation_realisation_gamad.etat IN ('declined', 'withdrawn') AND attestation_realisation_gamad.modifie_le <= ?
                 RETURNING reference"
            );
            $maintenant = time();
            $requete->execute([$reference, $publication, $sujet, $attestant, $affirmation, $date, $maintenant, $maintenant - 7 * 86400]);
            $reference = $requete->fetchColumn();
            $requete->closeCursor();
            abort_unless(is_string($reference), 409);

            return $reference;
        });
    }

    public function lister(string $sujet): array
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $requete = $registre->prepare(
            'SELECT reference, publication_reference, affirmation, realise_le, etat, revision, role,
                    CASE WHEN attestant_reference = ? THEN 1 ELSE 0 END AS a_confirmer
             FROM attestation_realisation_gamad WHERE auteur_reference = ? OR attestant_reference = ?
             ORDER BY modifie_le DESC, reference DESC LIMIT 100'
        );
        $requete->execute([$sujet, $sujet, $sujet]);

        return $requete->fetchAll();
    }

    public function decider(string $sujet, string $reference, int $revision, string $action, ?string $role, bool $consentement): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $requete = $registre->prepare(
            'SELECT publication_reference FROM attestation_realisation_gamad
             WHERE reference = ? AND (auteur_reference = ? OR attestant_reference = ?)'
        );
        $requete->execute([$reference, $sujet, $sujet]);
        $publication = $requete->fetchColumn();
        $requete->closeCursor();
        abort_unless(is_string($publication), 404);

        PaireGamad::verrouiller($registre, 'attestation', $publication, function () use ($registre, $sujet, $reference, $revision, $action, $role, $consentement): void {
            $requete = $registre->prepare('SELECT * FROM attestation_realisation_gamad WHERE reference = ?');
            $requete->execute([$reference]);
            $ligne = $requete->fetch();
            $requete->closeCursor();
            abort_if($ligne === false, 404);
            abort_if((int) $ligne['revision'] !== $revision, 409);

            $assurance = null;
            if ($action === 'retirer') {
                abort_unless(in_array($ligne['etat'], ['pending', 'confirmed'], true), 409);
                $etat = 'withdrawn';
            } else {
                abort_unless($ligne['attestant_reference'] === $sujet, 404);
                $assurance = $this->autoriser($registre, $ligne['auteur_reference'], $sujet, $ligne['publication_reference']);
                $etat = $action === 'confirmer' ? 'confirmed' : 'declined';
                $renouvellement = $etat === 'confirmed' && $ligne['etat'] === 'confirmed'
                    && (int) $ligne['assurance_evenement'] !== $assurance;
                abort_unless($ligne['etat'] === 'pending' || $renouvellement, 409);
                if ($etat === 'confirmed' && (!$consentement || !in_array($role, self::ROLES, true))) {
                    throw ValidationException::withMessages(['consentement' => 'Choisissez votre rôle et confirmez explicitement.']);
                }
            }

            $requete = $registre->prepare(
                'UPDATE attestation_realisation_gamad SET etat = ?, role = ?, assurance_evenement = ?, revision = revision + 1, modifie_le = ?
                 WHERE reference = ? AND revision = ?'
            );
            $requete->execute([$etat, $etat === 'confirmed' ? $role : null, $etat === 'confirmed' ? $assurance : null, time(), $reference, $revision]);
            abort_if($requete->rowCount() !== 1, 409);
        });
    }

    public function publiques(string $publication, ?string $session, ContenusGamad $contenus): array
    {
        $fiche = $contenus->publication($publication, $session);
        abort_unless(($fiche['publication']['type'] ?? null) === 'personal_work', 404);
        $registre = Magasin::ouvrir();
        $lecteur = $this->membres->depuisSession($session, $registre)['identite_reference'] ?? null;
        $visibilites = $lecteur === null ? "'public'" : "'public', 'members'";
        $requete = $registre->prepare(
            "SELECT a.*, f.reference_publique FROM attestation_realisation_gamad a
             JOIN fiche_annuaire_gamad f ON f.identite_reference = a.attestant_reference
             JOIN fiche_annuaire_gamad auteur ON auteur.identite_reference = a.auteur_reference
             WHERE a.publication_reference = ? AND a.etat = 'confirmed'
               AND f.visibilite IN ({$visibilites}) AND auteur.visibilite IN ({$visibilites})
             ORDER BY a.modifie_le DESC, a.reference DESC"
        );
        $requete->execute([$publication]);
        $exclus = $this->blocages->exclus($registre, $lecteur);
        $resultat = [];
        foreach ($requete->fetchAll() as $ligne) {
            $attestant = $ligne['attestant_reference'];
            $membre = isset($exclus[$attestant]) ? null : $this->membres->lire($attestant, $registre);
            if ($membre === null || $this->assurance($registre, $attestant) !== (int) $ligne['assurance_evenement']
                || isset($this->blocages->exclus($registre, $ligne['auteur_reference'])[$attestant])) {
                continue;
            }
            $resultat[] = [
                'affirmation' => $ligne['affirmation'], 'realise_le' => $ligne['realise_le'], 'role' => $ligne['role'],
                'attestant' => $membre['nom'], 'profil' => $ligne['reference_publique'],
            ];
        }

        return $resultat;
    }

    private function autoriser(PDO $registre, string $auteur, string $attestant, string $publication): int
    {
        abort_if($this->membres->lire($auteur, $registre) === null || $this->membres->lire($attestant, $registre) === null, 404);
        $paire = [$auteur, $attestant];
        sort($paire, SORT_STRING);
        $verrou = $registre->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? ' FOR UPDATE' : '';
        $relation = $registre->prepare(
            "SELECT 1 FROM relation_personnes_gamad
             WHERE premiere_reference = ? AND seconde_reference = ? AND etat = 'accepted'".$verrou
        );
        $relation->execute($paire);
        $acceptee = $relation->fetchColumn() !== false;
        $relation->closeCursor();
        abort_if(!$acceptee || isset($this->blocages->exclus($registre, $auteur)[$attestant]), 404);

        $requete = $registre->prepare(
            "SELECT 1 FROM publication_gamad p WHERE p.reference = ? AND p.auteur_reference = ? AND p.type = 'personal_work'
             AND NOT EXISTS (SELECT 1 FROM masquage_reseau_gamad m WHERE m.categorie = 'publication' AND m.cible_reference = p.reference)"
        );
        $requete->execute([$publication, $auteur]);
        abort_if($requete->fetchColumn() === false, 404);
        $assurance = $this->assurance($registre, $attestant);
        abort_if($assurance === null, 403);

        return $assurance;
    }

    private function assurance(PDO $registre, string $attestant): ?int
    {
        $requete = $registre->prepare(
            'SELECT id, niveau FROM evenement_assurance_identite WHERE identite_reference = ? AND date_effet <= ?
             ORDER BY date_effet DESC, id DESC LIMIT 1'
        );
        $requete->execute([$attestant, gmdate('Y-m-d')]);
        $evenement = $requete->fetch();

        return $evenement !== false && PolitiqueInscription::auMoins($evenement['niveau'], PolitiqueInscription::FINALITES['ACTION_ENGAGEANTE'])
            ? (int) $evenement['id']
            : null;
    }
}
