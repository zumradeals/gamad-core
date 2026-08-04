<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

/**
 * Livraison PULL authentifiée (CAP-CORE-014) : baux, accusés, refus,
 * nouvelles tentatives et lettres mortes.
 *
 * Un bail est opaque et plafonné ; il ne donne jamais accès à un autre
 * abonnement (partie 3 §8.1). Un accusé signifie que le consommateur a
 * accepté la responsabilité de traiter l'événement selon son contrat — pas
 * que toutes les conséquences métier ont réussi (partie 3 §9).
 */
final class LivreurEvenements
{
    public function __construct(
        private \PDO $magasin,
        private RegistreEvenements $evenements,
    ) {
        SchemaEvenements::migrer($this->magasin);
    }

    /** @return array<string,mixed> */
    public function obtenirLivraisons(
        string $abonnement,
        string $consommateurAppelant,
        int $limiteDemandee,
        ?int $bailSecondesDemande,
        string $correlation,
    ): array {
        $abo = $this->ligneAbonnement($abonnement);
        if ($abo === null) {
            return $this->refus('ABONNEMENT_INCONNU', "abonnement `{$abonnement}` inconnu");
        }
        if ($abo['consommateur_reference'] !== $consommateurAppelant) {
            return $this->refus('CONSOMMATEUR_NON_PROPRIETAIRE', 'lecture réservée au propriétaire de l’abonnement');
        }
        if ($this->etatAbonnement($abonnement) !== 'ACTIF') {
            return $this->refus('ABONNEMENT_INACTIF', 'abonnement non actif : aucun nouveau bail');
        }

        $limite = max(1, min($limiteDemandee, (int) $abo['taille_lot_max'], PolitiqueEvenements::TAILLE_LOT_MAX));
        $bailSecondes = max(1, min(
            $bailSecondesDemande ?? (int) $abo['duree_bail_secondes'],
            (int) $abo['duree_bail_secondes'],
            PolitiqueEvenements::BAIL_SECONDES_MAX,
        ));

        $propre = !$this->magasin->inTransaction();
        $sqlite = $propre && $this->driver() === 'sqlite';
        if ($propre) {
            $sqlite ? $this->magasin->exec('BEGIN IMMEDIATE') : $this->magasin->beginTransaction();
        }

        try {
            $maintenant = gmdate('c');
            $verrouSql = $this->driver() === 'pgsql' ? ' FOR UPDATE SKIP LOCKED' : '';
            $st = $this->magasin->prepare(
                "SELECT reference FROM livraison_evenement
                 WHERE abonnement_reference = ?
                   AND (
                        etat = 'DISPONIBLE'
                        OR (etat = 'SOUS_BAIL' AND bail_expire_le < ?)
                        OR (etat = 'A_REESSAYER' AND (prochaine_tentative_le IS NULL OR prochaine_tentative_le <= ?))
                   )
                 ORDER BY sequence_evenement
                 LIMIT ?{$verrouSql}"
            );
            $st->execute([$abonnement, $maintenant, $maintenant, $limite]);
            $references = array_column($st->fetchAll(), 'reference');

            $bail = 'BAIL-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
            $bailExpireLe = gmdate('c', time() + $bailSecondes);
            $livraisons = [];
            foreach ($references as $reference) {
                $this->magasin->prepare(
                    "UPDATE livraison_evenement
                     SET etat = 'SOUS_BAIL', bail_reference = ?, bail_expire_le = ?, tentatives = tentatives + 1
                     WHERE reference = ?"
                )->execute([$bail, $bailExpireLe, $reference]);
                $this->ajouterTentative($reference, 'LECTURE', 'BAIL_ACCORDE', null, null);

                $ligne = $this->ligneLivraison($reference);
                $evenement = $this->evenements->resoudreEvenement((string) $ligne['evenement_reference']);
                $charge = $this->evenements->resoudreCharge((string) $ligne['evenement_reference']);
                $livraisons[] = [
                    'livraison' => $reference,
                    'evenement' => $evenement,
                    'charge' => $charge['etat'] === 'DISPONIBLE' ? $charge['charge'] : null,
                    'charge_etat' => $charge['etat'],
                    'rejeu' => (bool) $ligne['rejeu'],
                ];
            }

            if ($propre) {
                $sqlite ? $this->magasin->exec('COMMIT') : $this->magasin->commit();
            }

            return [
                'abonnement' => $abonnement,
                'bail' => $references === [] ? null : $bail,
                'expire_le' => $references === [] ? null : $bailExpireLe,
                'livraisons' => $livraisons,
                'correlation_id' => $correlation,
            ];
        } catch (\Throwable $e) {
            if ($propre) {
                if ($sqlite) {
                    try {
                        $this->magasin->exec('ROLLBACK');
                    } catch (\Throwable) {
                    }
                } elseif ($this->magasin->inTransaction()) {
                    $this->magasin->rollBack();
                }
            }
            throw $e;
        }
    }

    /** @param list<string> $livraisons @return array<string,mixed> */
    public function accuserLivraisons(string $abonnement, string $bail, array $livraisons, string $correlation): array
    {
        $resultats = [];
        foreach ($livraisons as $reference) {
            $ligne = $this->ligneLivraison($reference);
            if ($ligne === null || $ligne['abonnement_reference'] !== $abonnement) {
                $resultats[$reference] = $this->refus('LIVRAISON_INCONNUE', 'livraison hors abonnement ou inconnue');
                continue;
            }
            if ($ligne['etat'] === 'ACCUSE') {
                $resultats[$reference] = ['etat' => 'ACCUSE', 'idempotent' => true];
                continue;
            }
            if ($ligne['bail_reference'] !== $bail || $ligne['etat'] !== 'SOUS_BAIL') {
                $resultats[$reference] = $this->refus('BAIL_INVALIDE', 'accusé hors bail refusé');
                continue;
            }
            $this->magasin->prepare(
                "UPDATE livraison_evenement SET etat = 'ACCUSE', accuse_le = ? WHERE reference = ?"
            )->execute([gmdate('c'), $reference]);
            $this->ajouterTentative($reference, 'ACCUSE', 'ACCUSE', null, null);
            $resultats[$reference] = ['etat' => 'ACCUSE', 'idempotent' => false];
        }

        $this->recalculerCurseur($abonnement);

        return ['abonnement' => $abonnement, 'resultats' => $resultats, 'correlation_id' => $correlation];
    }

    /** @return array<string,mixed> */
    public function refuserTemporairement(string $abonnement, string $bail, string $livraison, string $codeErreur, ?int $delaiSecondes, string $correlation): array
    {
        $ligne = $this->ligneLivraison($livraison);
        if ($ligne === null || $ligne['abonnement_reference'] !== $abonnement) {
            return $this->refus('LIVRAISON_INCONNUE', 'livraison hors abonnement ou inconnue');
        }
        if ($ligne['bail_reference'] !== $bail || $ligne['etat'] !== 'SOUS_BAIL') {
            return $this->refus('BAIL_INVALIDE', 'refus hors bail refusé');
        }
        $abo = $this->ligneAbonnement($abonnement);
        $tentativesMax = (int) $abo['tentatives_max'];
        if ((int) $ligne['tentatives'] >= $tentativesMax) {
            return $this->passerEnLettreMorte($livraison, $codeErreur, $correlation);
        }
        $delai = max(1, min($delaiSecondes ?? 30, PolitiqueEvenements::BAIL_SECONDES_MAX));
        $prochaine = gmdate('c', time() + $delai);
        $this->magasin->prepare(
            "UPDATE livraison_evenement SET etat = 'A_REESSAYER', prochaine_tentative_le = ?, dernier_code_erreur = ? WHERE reference = ?"
        )->execute([$prochaine, $codeErreur, $livraison]);
        $this->ajouterTentative($livraison, 'REFUS', 'REFUS_TEMPORAIRE', $codeErreur, null);

        return ['livraison' => $livraison, 'etat' => 'A_REESSAYER', 'prochaine_tentative_le' => $prochaine];
    }

    /** @return array<string,mixed> */
    public function refuserDefinitivement(string $abonnement, string $bail, string $livraison, string $codeErreur, string $justification, string $correlation): array
    {
        $ligne = $this->ligneLivraison($livraison);
        if ($ligne === null || $ligne['abonnement_reference'] !== $abonnement) {
            return $this->refus('LIVRAISON_INCONNUE', 'livraison hors abonnement ou inconnue');
        }
        if ($ligne['bail_reference'] !== $bail || $ligne['etat'] !== 'SOUS_BAIL') {
            return $this->refus('BAIL_INVALIDE', 'refus hors bail refusé');
        }
        if (trim($justification) === '') {
            return $this->refus('JUSTIFICATION_ABSENTE', 'un refus définitif exige une justification');
        }
        $this->ajouterTentative($livraison, 'REFUS', 'REFUS_DEFINITIF', $codeErreur, $justification);

        return $this->passerEnLettreMorte($livraison, $codeErreur, $correlation);
    }

    /** @return array{liberes:int} */
    public function libererBauxExpires(): array
    {
        $maintenant = gmdate('c');
        $st = $this->magasin->prepare(
            "SELECT reference, abonnement_reference, tentatives FROM livraison_evenement
             WHERE etat = 'SOUS_BAIL' AND bail_expire_le < ?"
        );
        $st->execute([$maintenant]);
        $lignes = $st->fetchAll();
        foreach ($lignes as $ligne) {
            $this->ajouterTentative((string) $ligne['reference'], 'EXPIRATION', 'BAIL_EXPIRE', null, null);
            $abo = $this->ligneAbonnement((string) $ligne['abonnement_reference']);
            if ($abo !== null && (int) $ligne['tentatives'] >= (int) $abo['tentatives_max']) {
                $this->passerEnLettreMorte((string) $ligne['reference'], 'BAIL_EXPIRE_PLAFOND', null);
                continue;
            }
            $this->magasin->prepare(
                "UPDATE livraison_evenement SET etat = 'DISPONIBLE', bail_reference = NULL, bail_expire_le = NULL WHERE reference = ?"
            )->execute([(string) $ligne['reference']]);
        }

        return ['liberes' => count($lignes)];
    }

    // ------------------------------------------------------------------
    // Lettres mortes

    /** @return array<string,mixed> */
    public function relancerLettreMorte(string $reference, array $dossier): array
    {
        foreach (['acteur', 'motif'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('CHAMP_MANQUANT', "champ obligatoire absent : {$champ}");
            }
        }
        $st = $this->magasin->prepare('SELECT * FROM lettre_morte_evenement WHERE reference = ?');
        $st->execute([$reference]);
        $lm = $st->fetch();
        if ($lm === false) {
            return $this->refus('LETTRE_MORTE_INCONNUE', "lettre morte `{$reference}` inconnue");
        }
        $livraison = (string) $lm['livraison_reference'];
        $this->magasin->prepare(
            "UPDATE livraison_evenement SET etat = 'DISPONIBLE', bail_reference = NULL, bail_expire_le = NULL, prochaine_tentative_le = NULL WHERE reference = ?"
        )->execute([$livraison]);
        $this->ajouterTentative($livraison, 'RELANCE', 'RELANCE', null, (string) $dossier['motif']);

        return ['lettre_morte' => $reference, 'livraison' => $livraison, 'relancee' => true];
    }

    /** @return array<string,mixed>|null */
    public function resoudreLettreMorte(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM lettre_morte_evenement WHERE reference = ?');
        $st->execute([$reference]);
        $l = $st->fetch();

        return $l === false ? null : $l + ['cloturee' => $this->lettreMorteEstCloturee($reference)];
    }

    /**
     * La table `lettre_morte_evenement` est en ajout seul (partie 2) : la
     * clôture ne modifie jamais cette ligne. Elle s'exprime comme une
     * nouvelle `tentative_livraison` de type `CLOTURE` sur la livraison
     * concernée — une relance ultérieure (type `RELANCE`) redevient
     * naturellement la tentative la plus récente et lève la clôture.
     *
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function cloturerLettreMorte(string $reference, array $dossier): array
    {
        foreach (['acteur', 'motif'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('CHAMP_MANQUANT', "champ obligatoire absent : {$champ}");
            }
        }
        $st = $this->magasin->prepare('SELECT * FROM lettre_morte_evenement WHERE reference = ?');
        $st->execute([$reference]);
        $lm = $st->fetch();
        if ($lm === false) {
            return $this->refus('LETTRE_MORTE_INCONNUE', "lettre morte `{$reference}` inconnue");
        }
        if ($this->lettreMorteEstCloturee($reference)) {
            return ['lettre_morte' => $reference, 'cloturee' => true, 'idempotent' => true];
        }
        $livraison = (string) $lm['livraison_reference'];
        $ligneLivraison = $this->ligneLivraison($livraison);
        if (($ligneLivraison['etat'] ?? null) !== 'LETTRE_MORTE') {
            return $this->refus('ETAT_INCOMPATIBLE', 'seule une lettre morte non relancée peut être clôturée');
        }
        $this->ajouterTentative($livraison, 'CLOTURE', 'CLOTURE', null, (string) $dossier['motif']);

        return ['lettre_morte' => $reference, 'cloturee' => true, 'idempotent' => false];
    }

    private function lettreMorteEstCloturee(string $reference): bool
    {
        $st = $this->magasin->prepare('SELECT livraison_reference FROM lettre_morte_evenement WHERE reference = ?');
        $st->execute([$reference]);
        $livraison = $st->fetchColumn();
        if ($livraison === false) {
            return false;
        }
        $st2 = $this->magasin->prepare(
            'SELECT resultat FROM tentative_livraison WHERE livraison_reference = ? ORDER BY numero DESC LIMIT 1'
        );
        $st2->execute([(string) $livraison]);

        return $st2->fetchColumn() === 'CLOTURE';
    }

    /** @return list<array<string,mixed>> */
    public function listerLettresMortes(?string $abonnement = null): array
    {
        if ($abonnement === null) {
            $st = $this->magasin->query('SELECT * FROM lettre_morte_evenement ORDER BY cree_le');

            return $st->fetchAll();
        }
        $st = $this->magasin->prepare(
            'SELECT lm.* FROM lettre_morte_evenement lm
             JOIN livraison_evenement l ON l.reference = lm.livraison_reference
             WHERE l.abonnement_reference = ? ORDER BY lm.cree_le'
        );
        $st->execute([$abonnement]);

        return $st->fetchAll();
    }

    // ------------------------------------------------------------------
    // Lectures diverses

    /** @return list<array<string,mixed>> */
    public function listerLivraisons(string $abonnement, ?string $etat = null): array
    {
        $sql = 'SELECT * FROM livraison_evenement WHERE abonnement_reference = ?';
        $args = [$abonnement];
        if ($etat !== null) {
            $sql .= ' AND etat = ?';
            $args[] = $etat;
        }
        $sql .= ' ORDER BY sequence_evenement';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        return $st->fetchAll();
    }

    /** @return array<string,mixed> */
    public function resoudreCurseur(string $abonnement): array
    {
        $st = $this->magasin->prepare('SELECT * FROM curseur_abonnement WHERE abonnement_reference = ?');
        $st->execute([$abonnement]);
        $l = $st->fetch();

        return $l === false
            ? ['abonnement' => $abonnement, 'derniere_sequence_contigue_accusee' => 0]
            : $l;
    }

    /** @return array<string,mixed> */
    public function resoudreRetard(string $abonnement): array
    {
        $st = $this->magasin->prepare(
            "SELECT COUNT(*) AS n, MIN(disponible_le) AS plus_ancienne FROM livraison_evenement
             WHERE abonnement_reference = ? AND etat IN ('DISPONIBLE','A_REESSAYER')"
        );
        $st->execute([$abonnement]);
        $l = $st->fetch();

        return [
            'abonnement' => $abonnement,
            'livraisons_en_attente' => (int) $l['n'],
            'plus_ancienne_disponible_le' => $l['plus_ancienne'],
        ];
    }

    // ------------------------------------------------------------------
    // Internes

    private function passerEnLettreMorte(string $livraison, ?string $codeErreur, ?string $correlation): array
    {
        $ligne = $this->ligneLivraison($livraison);
        if ($ligne === null) {
            return $this->refus('LIVRAISON_INCONNUE', 'livraison inconnue');
        }
        if ($ligne['etat'] === 'LETTRE_MORTE') {
            return ['livraison' => $livraison, 'etat' => 'LETTRE_MORTE', 'idempotent' => true];
        }
        $this->magasin->prepare(
            "UPDATE livraison_evenement SET etat = 'LETTRE_MORTE', bail_reference = NULL, bail_expire_le = NULL WHERE reference = ?"
        )->execute([$livraison]);

        $st = $this->magasin->prepare('SELECT commence_le FROM tentative_livraison WHERE livraison_reference = ? ORDER BY id LIMIT 1');
        $st->execute([$livraison]);
        $premiere = $st->fetchColumn();

        $reference = 'LM-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
        $this->magasin->prepare(
            'INSERT INTO lettre_morte_evenement(reference,livraison_reference,raison_code,tentatives_total,premiere_erreur_le,derniere_erreur_le,cree_le)
             VALUES(?,?,?,?,?,?,?)'
        )->execute([
            $reference, $livraison, $codeErreur ?? 'PLAFOND_TENTATIVES',
            (int) $ligne['tentatives'], $premiere !== false ? $premiere : gmdate('c'), gmdate('c'), gmdate('c'),
        ]);

        return ['livraison' => $livraison, 'etat' => 'LETTRE_MORTE', 'lettre_morte' => $reference, 'idempotent' => false];
    }

    private function recalculerCurseur(string $abonnement): void
    {
        $st = $this->magasin->prepare(
            'SELECT sequence_evenement, etat FROM livraison_evenement WHERE abonnement_reference = ? ORDER BY sequence_evenement'
        );
        $st->execute([$abonnement]);
        $curseur = 0;
        foreach ($st->fetchAll() as $ligne) {
            if (!in_array($ligne['etat'], ['ACCUSE', 'ANNULE'], true)) {
                break;
            }
            $curseur = (int) $ligne['sequence_evenement'];
        }

        $existe = $this->magasin->prepare('SELECT 1 FROM curseur_abonnement WHERE abonnement_reference = ?');
        $existe->execute([$abonnement]);
        if ($existe->fetchColumn() === false) {
            $this->magasin->prepare(
                'INSERT INTO curseur_abonnement(abonnement_reference,derniere_sequence_contigue_accusee,mis_a_jour_le) VALUES(?,?,?)'
            )->execute([$abonnement, $curseur, gmdate('c')]);

            return;
        }
        $this->magasin->prepare(
            'UPDATE curseur_abonnement SET derniere_sequence_contigue_accusee = ?, mis_a_jour_le = ? WHERE abonnement_reference = ?'
        )->execute([$curseur, gmdate('c'), $abonnement]);
    }

    private function ajouterTentative(string $livraison, string $type, string $resultat, ?string $codeErreur, ?string $detail): void
    {
        $st = $this->magasin->prepare(
            'SELECT COALESCE(MAX(numero),0) + 1 FROM tentative_livraison WHERE livraison_reference = ?'
        );
        $st->execute([$livraison]);
        $numero = (int) $st->fetchColumn();
        $this->magasin->prepare(
            'INSERT INTO tentative_livraison(livraison_reference,numero,type_tentative,resultat,code_erreur,detail_sanitaire,commence_le,termine_le)
             VALUES(?,?,?,?,?,?,?,?)'
        )->execute([$livraison, $numero, $type, $resultat, $codeErreur, $detail, gmdate('c'), gmdate('c')]);
    }

    private function ligneAbonnement(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM abonnement_evenement WHERE reference = ?');
        $st->execute([$reference]);
        $l = $st->fetch();

        return $l === false ? null : $l;
    }

    private function ligneLivraison(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM livraison_evenement WHERE reference = ?');
        $st->execute([$reference]);
        $l = $st->fetch();

        return $l === false ? null : $l;
    }

    private function etatAbonnement(string $abonnement): ?string
    {
        $st = $this->magasin->prepare('SELECT etat FROM abonnement_cycle WHERE abonnement_reference = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$abonnement]);
        $v = $st->fetchColumn();

        return $v === false ? null : (string) $v;
    }

    /** @return array{refus:string,detail:string} */
    private function refus(string $code, string $detail): array
    {
        return ['refus' => $code, 'detail' => $detail];
    }

    private function driver(): string
    {
        return (string) $this->magasin->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
