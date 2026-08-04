<?php

declare(strict_types=1);

namespace Gamad\EvenementsSortants;

use Gamad\JournalEvenements\RegistreEvenements;

/**
 * Relais transmettant les lignes d'outbox prêtes au journal central
 * (CAP-CORE-014, partie 3 §4).
 *
 * Un crash après acceptation centrale mais avant mise à jour de l'outbox ne
 * crée pas de doublon : `RegistreEvenements::accepterEvenement()` retourne le
 * reçu existant pour une même paire (producteur, idempotence), donc rejouer
 * une ligne déjà acceptée est sans effet observable.
 */
final class RelaisOutbox
{
    /** Codes de refus contractuel jamais retentables sans changement externe. */
    private const REFUS_DEFINITIFS = [
        'CONTRAT_INCONNU', 'CONTRAT_TYPE_INVALIDE', 'VERSION_INCOMPATIBLE',
        'PRODUCTEUR_NON_DECLARE', 'CHARGE_INVALIDE', 'ENVELOPPE_INVALIDE',
    ];

    public function __construct(
        private \PDO $magasinProducteur,
        private RegistreEvenements $registreCentral,
    ) {
        SchemaOutbox::migrer($this->magasinProducteur);
    }

    /** @param array<string,mixed> $dossier politique, producteur, source, preuve. @return array<string,mixed> */
    public function publierOutbox(array $dossier, int $limite = 100): array
    {
        $limite = max(1, min($limite, 500));
        $maintenant = gmdate('c');
        $verrouSql = $this->driver() === 'pgsql' ? ' FOR UPDATE SKIP LOCKED' : '';

        $propre = !$this->magasinProducteur->inTransaction();
        $sqlite = $propre && $this->driver() === 'sqlite';
        if ($propre) {
            $sqlite ? $this->magasinProducteur->exec('BEGIN IMMEDIATE') : $this->magasinProducteur->beginTransaction();
        }

        try {
            $st = $this->magasinProducteur->prepare(
                "SELECT id FROM evenement_sortant
                 WHERE etat = 'EN_ATTENTE'
                    OR (etat = 'ECHEC_TEMPORAIRE' AND (prochaine_tentative_le IS NULL OR prochaine_tentative_le <= ?))
                 ORDER BY id LIMIT ?{$verrouSql}"
            );
            $st->execute([$maintenant, $limite]);
            $ids = array_map('intval', array_column($st->fetchAll(), 'id'));
            if ($ids !== []) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $this->magasinProducteur->prepare(
                    "UPDATE evenement_sortant SET etat = 'EN_COURS' WHERE id IN ({$placeholders})"
                )->execute($ids);
            }
            if ($propre) {
                $sqlite ? $this->magasinProducteur->exec('COMMIT') : $this->magasinProducteur->commit();
            }
        } catch (\Throwable $e) {
            if ($propre) {
                if ($sqlite) {
                    try {
                        $this->magasinProducteur->exec('ROLLBACK');
                    } catch (\Throwable) {
                    }
                } elseif ($this->magasinProducteur->inTransaction()) {
                    $this->magasinProducteur->rollBack();
                }
            }
            throw $e;
        }

        $publies = 0;
        $echecsTemporaires = 0;
        $echecsDefinitifs = 0;
        foreach ($ids as $id) {
            $resultat = $this->traiterLigne($id, $dossier);
            match ($resultat) {
                'PUBLIE' => $publies++,
                'ECHEC_TEMPORAIRE' => $echecsTemporaires++,
                'ECHEC_DEFINITIF' => $echecsDefinitifs++,
            };
        }

        return [
            'lot' => count($ids),
            'publies' => $publies,
            'echecs_temporaires' => $echecsTemporaires,
            'echecs_definitifs' => $echecsDefinitifs,
        ];
    }

    private function traiterLigne(int $id, array $dossier): string
    {
        $st = $this->magasinProducteur->prepare('SELECT * FROM evenement_sortant WHERE id = ?');
        $st->execute([$id]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return 'ECHEC_TEMPORAIRE';
        }

        $intention = [
            'type_evenement' => $ligne['type_evenement'],
            'contrat_reference' => $ligne['contrat_reference'],
            'contrat_version' => $ligne['contrat_version'],
            'producteur_capacite_reference' => $ligne['producteur_capacite_reference'],
            'producteur_produit_reference' => $ligne['producteur_produit_reference'],
            'source_reference' => $ligne['source_reference'],
            'realm_reference' => $ligne['realm_reference'],
            'finalite_reference' => $ligne['finalite_reference'],
            'sujet_type' => $ligne['sujet_type'],
            'sujet_reference' => $ligne['sujet_reference'],
            'correlation_id' => $ligne['correlation_id'],
            'causation_reference' => $ligne['causation_reference'],
            'idempotence_reference' => $ligne['idempotence_reference'],
            'survenu_le' => $ligne['survenu_le'],
            'classification' => $ligne['classification'],
            'charge' => json_decode((string) $ligne['charge_json'], true, flags: JSON_THROW_ON_ERROR),
            'charge_empreinte' => $ligne['charge_empreinte'],
        ];

        try {
            $reponse = $this->registreCentral->accepterEvenement($intention, $dossier);
        } catch (\Throwable) {
            $this->marquerEchecTemporaire($id, (int) $ligne['tentatives'], 'ERREUR_TRANSPORT');

            return 'ECHEC_TEMPORAIRE';
        }

        if (isset($reponse['refus'])) {
            $code = (string) $reponse['refus'];
            if (in_array($code, self::REFUS_DEFINITIFS, true)) {
                $this->magasinProducteur->prepare(
                    "UPDATE evenement_sortant SET etat = 'ECHEC_DEFINITIF', derniere_erreur_code = ?, tentatives = tentatives + 1 WHERE id = ?"
                )->execute([$code, $id]);

                return 'ECHEC_DEFINITIF';
            }
            $this->marquerEchecTemporaire($id, (int) $ligne['tentatives'], $code);

            return 'ECHEC_TEMPORAIRE';
        }

        $this->magasinProducteur->prepare(
            "UPDATE evenement_sortant SET etat = 'PUBLIE', evenement_reference = ?, publie_le = ?, tentatives = tentatives + 1 WHERE id = ?"
        )->execute([(string) $reponse['reference'], gmdate('c'), $id]);

        return 'PUBLIE';
    }

    private function marquerEchecTemporaire(int $id, int $tentativesActuelles, string $code): void
    {
        $delai = min(600, (int) (2 ** min($tentativesActuelles, 9)));
        $prochaine = gmdate('c', time() + $delai);
        $this->magasinProducteur->prepare(
            "UPDATE evenement_sortant
             SET etat = 'ECHEC_TEMPORAIRE', derniere_erreur_code = ?, prochaine_tentative_le = ?, tentatives = tentatives + 1
             WHERE id = ?"
        )->execute([$code, $prochaine, $id]);
    }

    /** @return array<string,mixed> */
    public function listerEnRetard(int $seuilSecondes = 300): array
    {
        $seuil = gmdate('c', time() - $seuilSecondes);
        $st = $this->magasinProducteur->prepare(
            "SELECT * FROM evenement_sortant WHERE etat IN ('EN_ATTENTE','ECHEC_TEMPORAIRE') AND cree_le < ? ORDER BY cree_le"
        );
        $st->execute([$seuil]);

        return $st->fetchAll();
    }

    private function driver(): string
    {
        return (string) $this->magasinProducteur->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
