<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Validation\ValidationException;
use PDO;

final class EspacesGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public function zumras(): array
    {
        $registre = Magasin::ouvrir();

        return array_values(array_filter(
            $registre->query('SELECT * FROM zumra_gamad ORDER BY nom, reference LIMIT 100')->fetchAll(),
            fn (array $zumra): bool => $this->membres->lire($zumra['fondateur_reference'], $registre) !== null,
        ));
    }

    public function creerZumra(string $sujet, string $nom, string $presentation): string
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $reference = bin2hex(random_bytes(12));
        $registre->prepare('INSERT INTO zumra_gamad (reference, fondateur_reference, nom, presentation) VALUES (?, ?, ?, ?)')
            ->execute([$reference, $sujet, $this->texte($nom, 'nom'), $this->texte($presentation, 'presentation')]);

        return $reference;
    }

    public function zumra(string $reference, ?string $session): ?array
    {
        $registre = Magasin::ouvrir();
        $zumra = $this->chercherZumra($registre, $reference);
        if ($zumra === null) {
            return null;
        }

        $sujet = $this->membres->depuisSession($session, $registre)['identite_reference'] ?? null;
        $fondateur = $sujet === $zumra['fondateur_reference'];
        $responsable = $fondateur || MembresEspacesGamad::estDelegue($registre, $reference, $sujet);
        $demandes = [];
        if ($responsable) {
            // Le responsable reçoit uniquement le titre et la demande, sans accès au dossier.
            $requete = $registre->prepare(
                "SELECT reference, titre FROM projet_gamad WHERE zumra_reference = ? AND accueil = 'en_attente' ORDER BY titre"
            );
            $requete->execute([$reference]);
            $demandes = $requete->fetchAll();
        }

        $adhesion = $registre->prepare('SELECT etat FROM adhesion_zumra_gamad WHERE espace_reference = ? AND membre_reference = ?');
        $adhesion->execute([$reference, $sujet]);

        $mode = $registre->prepare('SELECT mode_adhesion FROM configuration_zumra_gamad WHERE zumra_reference = ?');
        $mode->execute([$reference]);

        return ['mode_adhesion' => $mode->fetchColumn() ?: 'approval', 'zumra' => $zumra, 'responsable' => $responsable, 'demandes' => $demandes,
            'fondateur' => $fondateur, 'connecte' => $sujet !== null, 'adhesion' => $adhesion->fetchColumn(),
            'membres' => $responsable ? array_map(
                fn (array $membre): array => $membre + ['delegue' => MembresEspacesGamad::estDelegue($registre, $reference, $membre['membre_reference'])],
                MembresEspacesGamad::liste($registre, $reference, true),
            ) : []];
    }

    public function projets(?string $session): array
    {
        $registre = Magasin::ouvrir();
        $sujet = $this->membres->depuisSession($session, $registre)['identite_reference'] ?? '';
        $requete = $registre->prepare(
            "SELECT reference, titre, porteur_reference, zumra_reference FROM projet_gamad
             WHERE porteur_reference = ? OR (accueil = 'accepte' AND vitrine IS NOT NULL)
             OR EXISTS (SELECT 1 FROM equipe_projet_gamad eq WHERE eq.espace_reference = projet_gamad.reference AND eq.membre_reference = ?)
             ORDER BY titre, reference LIMIT 100"
        );
        $requete->execute([$sujet, $sujet]);
        $exclus = $this->blocages->exclus($registre, $sujet === '' ? null : $sujet);

        return array_values(array_filter($requete->fetchAll(), fn (array $projet): bool =>
            $projet['porteur_reference'] === $sujet
            || (!isset($exclus[$projet['porteur_reference']])
                && $this->chercherZumra($registre, $projet['zumra_reference']) !== null
                && $this->membres->lire($projet['porteur_reference'], $registre) !== null)));
    }

    public function creerProjet(string $sujet, string $zumraReference, string $titre, string $dossier): string
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $zumra = $this->chercherZumra($registre, $zumraReference);
        abort_if($zumra === null, 404);
        $reference = bin2hex(random_bytes(12));
        $adhesion = $registre->prepare("SELECT 1 FROM adhesion_zumra_gamad WHERE espace_reference = ? AND membre_reference = ? AND etat = 'accepted'");
        $adhesion->execute([$zumraReference, $sujet]);
        $accueil = $sujet === $zumra['fondateur_reference'] || $adhesion->fetchColumn() !== false ? 'accepte' : 'en_attente';
        $registre->prepare(
            'INSERT INTO projet_gamad (reference, porteur_reference, zumra_reference, titre, dossier, accueil)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $reference, $sujet, $zumraReference, $this->texte($titre, 'titre'), $this->texte($dossier, 'dossier'),
            $accueil,
        ]);

        return $reference;
    }

    public function modifierProjet(string $sujet, string $reference, string $titre, string $dossier): void
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $requete = $registre->prepare(
            'UPDATE projet_gamad SET titre = ?, dossier = ? WHERE reference = ? AND porteur_reference = ?'
        );
        $requete->execute([$this->texte($titre, 'titre'), $this->texte($dossier, 'dossier'), $reference, $sujet]);
        abort_if($requete->rowCount() === 0, 404);
    }

    public function projet(string $reference, ?string $session): ?array
    {
        $registre = Magasin::ouvrir();
        $requete = $registre->prepare('SELECT * FROM projet_gamad WHERE reference = ?');
        $requete->execute([$reference]);
        $projet = $requete->fetch();
        if ($projet === false || $this->membres->lire($projet['porteur_reference'], $registre) === null) {
            return null;
        }

        $sujet = $this->membres->depuisSession($session, $registre)['identite_reference'] ?? null;
        if ($sujet === $projet['porteur_reference']) {
            return $projet + ['prive' => true, 'porteur' => true,
                'equipe' => MembresEspacesGamad::liste($registre, $reference, false),
                'etapes' => EtapesProjetGamad::lister($registre, $reference),
                'participations' => ParticipationsGamad::demandes($registre, $reference)];
        }
        $equipe = $registre->prepare('SELECT etat FROM equipe_projet_gamad WHERE espace_reference = ? AND membre_reference = ?');
        $equipe->execute([$reference, $sujet]);
        $etat = $equipe->fetchColumn();
        if (isset($this->blocages->exclus($registre, $sujet)[$projet['porteur_reference']])) {
            return null;
        }
        if ($etat === 'accepted') {
            return $projet + ['prive' => true, 'porteur' => false, 'etapes' => EtapesProjetGamad::lister($registre, $reference)];
        }
        if ($etat === 'pending') {
            return ['reference' => $reference, 'titre' => $projet['titre'], 'invitation' => true, 'prive' => false];
        }
        if ($projet['accueil'] !== 'accepte' || $projet['vitrine'] === null
            || $this->chercherZumra($registre, $projet['zumra_reference']) === null) {
            return null;
        }

        return ['reference' => $reference, 'titre' => $projet['titre'], 'vitrine' => $projet['vitrine'], 'prive' => false,
            'participation_autorisee' => $sujet !== null,
            'participation' => $sujet === null ? null : ParticipationsGamad::etat($registre, $reference, $sujet)];
    }

    public function deciderAccueil(string $sujet, string $zumra, string $projet, bool $accepter): void
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $fiche = $this->chercherZumra($registre, $zumra);
        abort_unless($fiche !== null && ($sujet === $fiche['fondateur_reference']
            || MembresEspacesGamad::estDelegue($registre, $zumra, $sujet)), 404);

        $requete = $registre->prepare(
            "UPDATE projet_gamad SET accueil = ? WHERE reference = ? AND zumra_reference = ? AND accueil = 'en_attente'"
        );
        $requete->execute([$accepter ? 'accepte' : 'refuse', $projet, $zumra]);
        abort_if($requete->rowCount() === 0, 409);
    }

    public function publierVitrine(string $sujet, string $reference, ?string $vitrine): void
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $vitrine = $vitrine === null ? null : $this->texte($vitrine, 'vitrine');
        $requete = $registre->prepare(
            "UPDATE projet_gamad SET vitrine = ? WHERE reference = ? AND porteur_reference = ? AND accueil = 'accepte'"
        );
        $requete->execute([$vitrine, $reference, $sujet]);
        abort_if($requete->rowCount() === 0, 404);
    }

    private function chercherZumra(PDO $registre, string $reference): ?array
    {
        $requete = $registre->prepare('SELECT * FROM zumra_gamad WHERE reference = ?');
        $requete->execute([$reference]);
        $zumra = $requete->fetch();

        return $zumra !== false && $this->membres->lire($zumra['fondateur_reference'], $registre) !== null ? $zumra : null;
    }

    private function exigerMembre(PDO $registre, string $sujet): void
    {
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
    }

    private function texte(string $saisie, string $champ): string
    {
        $texte = Normalizer::multiline($saisie);
        if ($texte === '') {
            throw ValidationException::withMessages([$champ => 'Saisissez un texte.']);
        }

        return $texte;
    }
}
