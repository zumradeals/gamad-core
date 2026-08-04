<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

/**
 * Rapprochement en lecture seule entre le journal central de CAP-CORE-014 et
 * les magasins producteurs raccordés (fiche partie 5 §8).
 *
 * Ne répare rien : produit uniquement un rapport structuré d'anomalies.
 * Aucune commande de réparation n'existe dans ce chantier — en créer une
 * reste un chantier ultérieur explicite, jamais une correction silencieuse
 * déclenchée par ce rapprochement.
 */
final class RapprochementEvenements
{
    public function __construct(private \PDO $central)
    {
    }

    /**
     * @param array<string,\PDO> $magasinsProducteurs nom lisible => magasin producteur
     * @return array<string,list<array<string,mixed>>>
     */
    public function rapprocher(array $magasinsProducteurs, int $limite = 1000): array
    {
        $limite = max(1, $limite);

        return [
            'evenement_sans_recu_publication' => $this->evenementsSansRecu($limite),
            'livraison_sans_evenement' => $this->livraisonsSansEvenement($limite),
            'curseur_au_dela_du_journal' => $this->curseursAuDelaDuJournal($limite),
            'charge_manquante_avant_expiration' => $this->chargesManquantes($limite),
            'abonnement_actif_sans_type' => $this->abonnementsActifsSansType($limite),
            'lettre_morte_orpheline' => $this->lettresMortesOrphelines($limite),
            'outbox_publie_sans_evenement_central' => $this->outboxPublieeSansEvenement($magasinsProducteurs, $limite),
            'outbox_en_attente_deja_acceptee' => $this->outboxEnAttenteDejaAcceptee($magasinsProducteurs, $limite),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function evenementsSansRecu(int $limite): array
    {
        $st = $this->central->prepare(
            'SELECT e.reference, e.producteur_reference, e.idempotence_reference
             FROM evenement_commun e
             LEFT JOIN recu_publication r
               ON r.producteur_reference = e.producteur_reference AND r.idempotence_reference = e.idempotence_reference
             WHERE r.id IS NULL
             ORDER BY e.sequence_id
             LIMIT ?'
        );
        $st->execute([$limite]);

        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function livraisonsSansEvenement(int $limite): array
    {
        $st = $this->central->prepare(
            'SELECT l.reference AS livraison_reference, l.abonnement_reference, l.evenement_reference
             FROM livraison_evenement l
             LEFT JOIN evenement_commun e ON e.reference = l.evenement_reference
             WHERE e.reference IS NULL
             LIMIT ?'
        );
        $st->execute([$limite]);

        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function curseursAuDelaDuJournal(int $limite): array
    {
        $tete = (int) $this->central->query('SELECT COALESCE(MAX(sequence_id), 0) FROM evenement_commun')->fetchColumn();
        $st = $this->central->prepare(
            'SELECT abonnement_reference, derniere_sequence_contigue_accusee
             FROM curseur_abonnement
             WHERE derniere_sequence_contigue_accusee > ?
             LIMIT ?'
        );
        $st->execute([$tete, $limite]);

        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function chargesManquantes(int $limite): array
    {
        $maintenant = gmdate('c');
        $st = $this->central->prepare(
            'SELECT e.reference, e.charge_expire_le
             FROM evenement_commun e
             LEFT JOIN evenement_charge c ON c.evenement_reference = e.reference
             WHERE c.evenement_reference IS NULL
               AND (e.charge_expire_le IS NULL OR e.charge_expire_le > ?)
             LIMIT ?'
        );
        $st->execute([$maintenant, $limite]);

        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function abonnementsActifsSansType(int $limite): array
    {
        $st = $this->central->query(
            'SELECT ae.reference,
                    (SELECT ac.etat FROM abonnement_cycle ac
                     WHERE ac.abonnement_reference = ae.reference ORDER BY ac.id DESC LIMIT 1) AS etat_courant,
                    (SELECT COUNT(*) FROM abonnement_type_evenement t WHERE t.abonnement_reference = ae.reference) AS nb_types
             FROM abonnement_evenement ae'
        );
        $lignes = $st->fetchAll();
        $anomalies = [];
        foreach ($lignes as $ligne) {
            if ($ligne['etat_courant'] === 'ACTIF' && (int) $ligne['nb_types'] === 0) {
                $anomalies[] = ['reference' => $ligne['reference'], 'etat_courant' => $ligne['etat_courant']];
                if (count($anomalies) >= $limite) {
                    break;
                }
            }
        }

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function lettresMortesOrphelines(int $limite): array
    {
        $st = $this->central->prepare(
            'SELECT lm.reference, lm.livraison_reference
             FROM lettre_morte_evenement lm
             LEFT JOIN livraison_evenement l ON l.reference = lm.livraison_reference
             WHERE l.reference IS NULL
             LIMIT ?'
        );
        $st->execute([$limite]);

        return $st->fetchAll();
    }

    /**
     * @param array<string,\PDO> $magasinsProducteurs
     * @return list<array<string,mixed>>
     */
    private function outboxPublieeSansEvenement(array $magasinsProducteurs, int $limite): array
    {
        $anomalies = [];
        foreach ($magasinsProducteurs as $nom => $magasin) {
            try {
                $st = $magasin->prepare(
                    "SELECT idempotence_reference, evenement_reference
                     FROM evenement_sortant
                     WHERE etat = 'PUBLIE' AND evenement_reference IS NOT NULL
                     LIMIT ?"
                );
                $st->execute([$limite]);
            } catch (\PDOException) {
                continue;
            }
            foreach ($st->fetchAll() as $ligne) {
                $existe = $this->central->prepare('SELECT 1 FROM evenement_commun WHERE reference = ?');
                $existe->execute([$ligne['evenement_reference']]);
                if ($existe->fetchColumn() === false) {
                    $anomalies[] = [
                        'magasin_producteur' => $nom,
                        'idempotence_reference' => $ligne['idempotence_reference'],
                        'evenement_reference' => $ligne['evenement_reference'],
                    ];
                }
                if (count($anomalies) >= $limite) {
                    return $anomalies;
                }
            }
        }

        return $anomalies;
    }

    /**
     * @param array<string,\PDO> $magasinsProducteurs
     * @return list<array<string,mixed>>
     */
    private function outboxEnAttenteDejaAcceptee(array $magasinsProducteurs, int $limite): array
    {
        $anomalies = [];
        foreach ($magasinsProducteurs as $nom => $magasin) {
            try {
                $st = $magasin->prepare(
                    "SELECT idempotence_reference, producteur_capacite_reference, producteur_produit_reference
                     FROM evenement_sortant
                     WHERE etat = 'EN_ATTENTE'
                     LIMIT ?"
                );
                $st->execute([$limite]);
            } catch (\PDOException) {
                continue;
            }
            foreach ($st->fetchAll() as $ligne) {
                $producteur = (string) ($ligne['producteur_capacite_reference'] ?? $ligne['producteur_produit_reference'] ?? '');
                if ($producteur === '') {
                    continue;
                }
                $existe = $this->central->prepare(
                    'SELECT 1 FROM recu_publication WHERE producteur_reference = ? AND idempotence_reference = ?'
                );
                $existe->execute([$producteur, $ligne['idempotence_reference']]);
                if ($existe->fetchColumn() !== false) {
                    $anomalies[] = [
                        'magasin_producteur' => $nom,
                        'producteur_reference' => $producteur,
                        'idempotence_reference' => $ligne['idempotence_reference'],
                    ];
                }
                if (count($anomalies) >= $limite) {
                    return $anomalies;
                }
            }
        }

        return $anomalies;
    }
}
