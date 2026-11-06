<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Application\Comptes\MembreGamad;
use Gamad\RegistreIdentites\Magasin;
use PDO;

final class NotificationsGamad
{
    public function __construct(private MembreGamad $membres, private BlocagesGamad $blocages)
    {
    }

    public static function ajouter(PDO $registre, string $destinataire, string $auteur, string $type, ?string $proposition = null): void
    {
        $reference = bin2hex(random_bytes(12));
        $registre->prepare(
            'INSERT INTO notification_reseau_gamad (reference, destinataire_reference, auteur_reference, type, cree_le)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$reference, $destinataire, $auteur, $type, gmdate('c')]);
        if ($proposition !== null) {
            $registre->prepare(
                'INSERT INTO contexte_notification_aide_gamad (notification_reference, proposition_reference) VALUES (?, ?)'
            )->execute([$reference, $proposition]);
        }
    }

    public function lister(string $sujet, bool $avecAide = false): array
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $contexte = $avecAide
            ? "(c.notification_reference IS NULL AND f.reference_publique IS NOT NULL)
               OR (a.reference IS NOT NULL AND a.etat <> 'withdrawn')"
            : 'c.notification_reference IS NULL AND f.reference_publique IS NOT NULL';
        $requete = $registre->prepare(
            "SELECT n.*, i.libelle AS nom, f.reference_publique, a.reference AS proposition_reference, a.besoin_reference, p.type AS type_publication
             FROM notification_reseau_gamad n JOIN identite_inscrite i ON i.reference = n.auteur_reference
             LEFT JOIN fiche_annuaire_gamad f ON f.identite_reference = i.reference
             LEFT JOIN contexte_notification_aide_gamad c ON c.notification_reference = n.reference
             LEFT JOIN proposition_aide_gamad a ON a.reference = c.proposition_reference
             LEFT JOIN publication_gamad p ON p.reference = a.besoin_reference
             WHERE n.destinataire_reference = ? AND ({$contexte})
             ORDER BY n.cree_le DESC, n.reference DESC LIMIT 100"
        );
        $requete->execute([$sujet]);
        $exclus = $this->blocages->exclus($registre, $sujet);
        $resultat = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $notice) {
            if (!isset($exclus[$notice['auteur_reference']])
                && $this->membres->lire($notice['auteur_reference'], $registre) !== null) {
                if ($notice['proposition_reference'] !== null) {
                    if ($notice['type'] === 'demande' && !EmetteurBesoinGamad::peutGerer($registre, $sujet, $notice['besoin_reference'])) {
                        continue;
                    }

                    if (in_array($notice['type_publication'], PropositionsAideGamad::TYPES_FIL, true)) {
                        $notice['publication_reference'] = $notice['besoin_reference'];
                        unset($notice['besoin_reference']);
                        $notice['type'] = match ($notice['type']) {
                            'demande' => 'reponse_proposee',
                            'acceptation' => 'reponse_acceptee',
                            'message' => 'message_reponse',
                        };
                        $resultat[] = $notice;
                        continue;
                    }
                    $notice['type'] = match ($notice['type']) {
                        'demande' => 'aide_proposee',
                        'acceptation' => 'aide_acceptee',
                        'message' => 'message_aide',
                    };
                }
                $resultat[] = $notice;
            }
        }

        return $resultat;
    }

    public function lire(string $sujet, string $reference): void
    {
        $registre = Magasin::ouvrir();
        abort_if($this->membres->lire($sujet, $registre) === null, 403);
        $requete = $registre->prepare(
            'UPDATE notification_reseau_gamad SET lue_le = COALESCE(lue_le, ?) WHERE reference = ? AND destinataire_reference = ?'
        );
        $requete->execute([gmdate('c'), $reference, $sujet]);
        abort_if($requete->rowCount() === 0, 404);
    }
}
