<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as MagasinJournal;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class ContactsGamad
{
    public const SUJETS = [
        'general' => 'Question générale',
        'account' => 'Problème de compte',
        'problem' => 'Signaler un problème',
        'partnership' => 'Partenariat',
        'other' => 'Autre',
    ];

    public function envoyer(array $donnees): void
    {
        $registre = Magasin::ouvrir();
        $this->purger($registre);
        $registre->prepare(
            "INSERT INTO contact_reseau_gamad (reference, nom, email, sujet, message, etat, cree_le)
             VALUES (?, ?, ?, ?, ?, 'new', ?)"
        )->execute([
            bin2hex(random_bytes(12)), $donnees['nom'], mb_strtolower($donnees['email']),
            $donnees['sujet'], $donnees['message'], gmdate('c'),
        ]);
    }

    public function lister(): array
    {
        $registre = Magasin::ouvrir();
        $this->purger($registre);

        return $registre->query(
            'SELECT reference, sujet, etat, cree_le FROM contact_reseau_gamad ORDER BY cree_le DESC, reference DESC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ouvrir(string $acteur, string $reference): array
    {
        $registre = Magasin::ouvrir();
        $this->purger($registre);
        $requete = $registre->prepare('SELECT * FROM contact_reseau_gamad WHERE reference = ?');
        $requete->execute([$reference]);
        $message = $requete->fetch(PDO::FETCH_ASSOC);
        $requete->closeCursor();
        abort_if($message === false, 404);
        $this->journaliser($acteur, $reference, 'CONSULTER_MESSAGE_CONTACT');
        $registre->prepare("UPDATE contact_reseau_gamad SET etat = 'read' WHERE reference = ? AND etat = 'new'")
            ->execute([$reference]);

        return $message;
    }

    public function traiter(string $acteur, string $reference, bool $supprimer): void
    {
        $registre = Magasin::ouvrir();
        $this->purger($registre);
        $requete = $registre->prepare('SELECT 1 FROM contact_reseau_gamad WHERE reference = ?');
        $requete->execute([$reference]);
        abort_if($requete->fetchColumn() === false, 404);
        $requete->closeCursor();
        $this->journaliser($acteur, $reference, $supprimer ? 'SUPPRIMER_MESSAGE_CONTACT' : 'TRAITER_MESSAGE_CONTACT');
        $sql = $supprimer ? 'DELETE FROM contact_reseau_gamad' : "UPDATE contact_reseau_gamad SET etat = 'handled'";
        $registre->prepare($sql.' WHERE reference = ?')->execute([$reference]);
    }

    private function purger(PDO $registre): void
    {
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-12 months')->format('c');
        $registre->prepare('DELETE FROM contact_reseau_gamad WHERE cree_le < ?')->execute([$date]);
    }

    private function journaliser(string $acteur, string $reference, string $action): void
    {
        (new Journal(MagasinJournal::connecter()))->enregistrer([
            'categorie' => 'SECURITE',
            'type' => $action,
            'acteur' => $acteur,
            'ressource' => $reference,
            'action' => $action,
            'decision' => 'AUTORISEE',
        ]);
    }
}
