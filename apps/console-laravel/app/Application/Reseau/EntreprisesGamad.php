<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class EntreprisesGamad
{
    public function __construct(private MembreGamad $membres)
    {
    }

    public function lister(?string $session): array
    {
        $registre = Magasin::ouvrir();
        $sujet = $this->sujet($registre, $session);
        $requete = $registre->prepare(
            "SELECT reference, nom, secteur, etat, proprietaire_reference FROM entreprise_gamad
             WHERE etat = 'verified' OR proprietaire_reference = ? ORDER BY nom, reference LIMIT 100"
        );
        $requete->execute([$sujet]);

        return array_values(array_filter($requete->fetchAll(), fn (array $entreprise): bool =>
            $this->membres->lire($entreprise['proprietaire_reference'], $registre) !== null));
    }

    public function fiche(string $reference, ?string $session): ?array
    {
        $registre = Magasin::ouvrir();
        $sujet = $this->sujet($registre, $session);
        $entreprise = $this->entreprise($registre, $reference);
        if ($entreprise === null || ($entreprise['etat'] !== 'verified' && $entreprise['proprietaire_reference'] !== $sujet)) {
            return null;
        }
        $requete = $registre->prepare(
            'SELECT reference, titre, etat FROM opportunite_gamad WHERE entreprise_reference = ? ORDER BY cree_le DESC LIMIT 100'
        );
        $requete->execute([$reference]);

        return ['entreprise' => $entreprise, 'gestion' => $entreprise['proprietaire_reference'] === $sujet,
            'opportunites' => $requete->fetchAll()];
    }

    public function soumettre(string $sujet, array $donnees, string $justificatif): string
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $reference = bin2hex(random_bytes(12));
        $requete = $registre->prepare(
            "INSERT INTO entreprise_gamad (reference, proprietaire_reference, nom, pays, immatriculation, secteur, justificatif, etat)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending') ON CONFLICT (pays, immatriculation) DO NOTHING"
        );
        $requete->execute([
            $reference, $sujet, $donnees['nom'], strtoupper($donnees['pays']),
            mb_strtoupper(preg_replace('/\s+/u', '', $donnees['immatriculation'])),
            $donnees['secteur'], base64_encode($justificatif),
        ]);
        abort_if($requete->rowCount() === 0, 409, 'Cette immatriculation est déjà utilisée.');

        return $reference;
    }

    public function revue(): array
    {
        return Magasin::ouvrir()->query(
            "SELECT reference, nom, pays, immatriculation, secteur, etat FROM entreprise_gamad
             WHERE etat IN ('pending', 'verified') ORDER BY nom LIMIT 100"
        )->fetchAll();
    }

    public function justificatif(string $reference): string
    {
        $requete = Magasin::ouvrir()->prepare('SELECT justificatif FROM entreprise_gamad WHERE reference = ?');
        $requete->execute([$reference]);
        $contenu = $requete->fetchColumn();
        abort_unless(is_string($contenu), 404);

        return base64_decode($contenu);
    }

    public function decider(string $acteur, string $reference, string $action): void
    {
        $registre = Magasin::ouvrir();
        $entreprise = $this->entreprise($registre, $reference);
        abort_if($entreprise === null || $entreprise['proprietaire_reference'] === $acteur, 404);
        $requete = $registre->prepare(
            'UPDATE entreprise_gamad SET etat = ?, decision_par = ?, decision_le = ? WHERE reference = ? AND etat = ?'
        );
        $requete->execute([$action, $acteur, gmdate('c'), $reference, $action === 'suspended' ? 'verified' : 'pending']);
        abort_if($requete->rowCount() === 0, 409);
    }

    public function opportunites(): array
    {
        $registre = Magasin::ouvrir();
        $lignes = $registre->query(
            "SELECT o.reference, o.titre, o.etat, e.nom, e.proprietaire_reference FROM opportunite_gamad o
             JOIN entreprise_gamad e ON e.reference = o.entreprise_reference
             WHERE e.etat = 'verified' ORDER BY o.cree_le DESC LIMIT 100"
        )->fetchAll();

        return array_values(array_filter($lignes, fn (array $ligne): bool =>
            $this->membres->lire($ligne['proprietaire_reference'], $registre) !== null));
    }

    public function opportunite(string $reference, ?string $session): ?array
    {
        $registre = Magasin::ouvrir();
        $sujet = $this->sujet($registre, $session);
        $opportunite = $this->chercherOpportunite($registre, $reference);
        if ($opportunite === null) {
            return null;
        }
        $gestion = $opportunite['proprietaire_reference'] === $sujet;
        $sql = 'SELECT c.message, i.libelle AS candidat FROM candidature_gamad c
                JOIN identite_inscrite i ON i.reference = c.candidat_reference WHERE c.opportunite_reference = ?';
        $requete = $registre->prepare($sql . ($gestion ? '' : ' AND c.candidat_reference = ?') . ' ORDER BY c.cree_le DESC');
        $requete->execute($gestion ? [$reference] : [$reference, $sujet]);

        return compact('opportunite', 'gestion') + [
            'candidatures' => $requete->fetchAll(),
            'peut_candidater' => $sujet !== '' && !$gestion && $opportunite['etat'] === 'published',
        ];
    }

    public function publier(string $sujet, string $entreprise, string $titre, string $description): string
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $reference = bin2hex(random_bytes(12));
        $requete = $registre->prepare(
            "INSERT INTO opportunite_gamad (reference, entreprise_reference, titre, description, etat, cree_le)
             SELECT ?, reference, ?, ?, 'published', ? FROM entreprise_gamad
             WHERE reference = ? AND proprietaire_reference = ? AND etat = 'verified'"
        );
        $requete->execute([$reference, $titre, $description, gmdate('c'), $entreprise, $sujet]);
        abort_if($requete->rowCount() === 0, 404);

        return $reference;
    }

    public function candidater(string $sujet, string $reference, string $message): void
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        abort_if($this->chercherOpportunite($registre, $reference) === null, 404);
        $requete = $registre->prepare(
            "INSERT INTO candidature_gamad (opportunite_reference, candidat_reference, message, cree_le)
             SELECT o.reference, ?, ?, ? FROM opportunite_gamad o JOIN entreprise_gamad e ON e.reference = o.entreprise_reference
             WHERE o.reference = ? AND o.etat = 'published' AND e.etat = 'verified' AND e.proprietaire_reference <> ?
             ON CONFLICT (opportunite_reference, candidat_reference) DO NOTHING"
        );
        $requete->execute([$sujet, $message, gmdate('c'), $reference, $sujet]);
        abort_if($requete->rowCount() === 0, 409);
    }

    public function cloturer(string $sujet, string $reference): void
    {
        $registre = Magasin::ouvrir();
        $this->exigerMembre($registre, $sujet);
        $requete = $registre->prepare(
            "UPDATE opportunite_gamad SET etat = 'closed' WHERE reference = ? AND etat = 'published'
             AND entreprise_reference IN (SELECT reference FROM entreprise_gamad WHERE proprietaire_reference = ? AND etat = 'verified')"
        );
        $requete->execute([$reference, $sujet]);
        abort_if($requete->rowCount() === 0, 404);
    }

    private function entreprise(PDO $registre, string $reference): ?array
    {
        $requete = $registre->prepare('SELECT reference, nom, secteur, etat, proprietaire_reference FROM entreprise_gamad WHERE reference = ?');
        $requete->execute([$reference]);
        $ligne = $requete->fetch();

        return $ligne !== false && $this->membres->lire($ligne['proprietaire_reference'], $registre) !== null ? $ligne : null;
    }

    private function chercherOpportunite(PDO $registre, string $reference): ?array
    {
        $requete = $registre->prepare(
            "SELECT o.*, e.nom, e.proprietaire_reference FROM opportunite_gamad o JOIN entreprise_gamad e ON e.reference = o.entreprise_reference
             WHERE o.reference = ? AND e.etat = 'verified'"
        );
        $requete->execute([$reference]);
        $ligne = $requete->fetch();

        return $ligne !== false && $this->membres->lire($ligne['proprietaire_reference'], $registre) !== null ? $ligne : null;
    }

    private function sujet(PDO $registre, ?string $session): string
    {
        return $this->membres->depuisSession($session, $registre)['identite_reference'] ?? '';
    }

    private function exigerMembre(PDO $registre, string $sujet): void
    {
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
    }
}
