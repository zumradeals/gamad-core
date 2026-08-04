<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

/**
 * Rejeu borné et gouverné (CAP-CORE-014, partie 3 §12).
 *
 * Un rejeu ne crée jamais un nouvel événement métier : il ne fait que
 * remettre à disposition, pour un abonnement déjà autorisé, des livraisons
 * référençant des événements déjà acceptés dans le journal commun. Toute
 * livraison issue d'un rejeu est marquée `rejeu = 1`.
 */
final class RejoueurEvenements
{
    public function __construct(private \PDO $magasin)
    {
        SchemaEvenements::migrer($this->magasin);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function demanderRejeu(string $abonnement, array $dossier): array
    {
        foreach (['motif', 'demandeur', 'politique', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('CHAMP_MANQUANT', "champ obligatoire absent : {$champ}");
            }
        }
        $st = $this->magasin->prepare('SELECT 1 FROM abonnement_evenement WHERE reference = ?');
        $st->execute([$abonnement]);
        if ($st->fetchColumn() === false) {
            return $this->refus('ABONNEMENT_INCONNU', "abonnement `{$abonnement}` inconnu");
        }
        $sequenceDebut = isset($dossier['sequence_debut']) ? (int) $dossier['sequence_debut'] : null;
        $sequenceFin = isset($dossier['sequence_fin']) ? (int) $dossier['sequence_fin'] : null;
        if ($sequenceDebut === null && $sequenceFin === null && !isset($dossier['date_debut'])) {
            return $this->refus('BORNES_ABSENTES', 'un rejeu exige des bornes explicites, aucune demande « depuis toujours » implicite');
        }
        if ($sequenceDebut !== null && $sequenceFin !== null && $sequenceFin < $sequenceDebut) {
            return $this->refus('BORNES_INVALIDES', 'sequence_fin antérieure à sequence_debut');
        }
        $volume = $this->estimerVolume($abonnement, $sequenceDebut, $sequenceFin, $dossier['date_debut'] ?? null, $dossier['date_fin'] ?? null, $dossier['types'] ?? []);
        if ($volume > PolitiqueEvenements::REJEU_VOLUME_MAX) {
            return $this->refus('VOLUME_EXCESSIF', "volume estimé {$volume} au-delà du plafond " . PolitiqueEvenements::REJEU_VOLUME_MAX);
        }

        $reference = 'REJ-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
        $this->magasin->prepare(
            "INSERT INTO demande_rejeu
             (reference,abonnement_reference,sequence_debut,sequence_fin,date_debut,date_fin,types_json,motif,etat,
              demandeur_reference,politique_reference,preuve_reference,correlation_id,volume_estime,cree_le)
             VALUES(?,?,?,?,?,?,?,?,'DEMANDEE',?,?,?,?,?,?)"
        )->execute([
            $reference, $abonnement, $sequenceDebut, $sequenceFin,
            $dossier['date_debut'] ?? null, $dossier['date_fin'] ?? null,
            json_encode($dossier['types'] ?? [], JSON_THROW_ON_ERROR),
            (string) $dossier['motif'], (string) $dossier['demandeur'],
            (string) $dossier['politique'], (string) $dossier['preuve'],
            $dossier['correlation_id'] ?? null, $volume, gmdate('c'),
        ]);

        return ['reference' => $reference, 'etat' => 'DEMANDEE', 'volume_estime' => $volume];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function validerRejeu(string $reference, array $dossier): array
    {
        $demande = $this->ligneDemande($reference);
        if ($demande === null) {
            return $this->refus('DEMANDE_INCONNUE', "demande `{$reference}` inconnue");
        }
        if ($demande['etat'] !== 'DEMANDEE') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une demande DEMANDEE se valide (état actuel `{$demande['etat']}`)");
        }
        if ((int) $demande['volume_estime'] > PolitiqueEvenements::REJEU_VOLUME_MAX) {
            return $this->refus('VOLUME_EXCESSIF', 'le volume dépasse le plafond autorisé');
        }
        $this->magasin->prepare("UPDATE demande_rejeu SET etat = 'VALIDEE' WHERE reference = ?")->execute([$reference]);

        return ['reference' => $reference, 'etat' => 'VALIDEE'];
    }

    /** @return array<string,mixed> */
    public function annulerRejeu(string $reference): array
    {
        $demande = $this->ligneDemande($reference);
        if ($demande === null) {
            return $this->refus('DEMANDE_INCONNUE', "demande `{$reference}` inconnue");
        }
        if (in_array($demande['etat'], ['TERMINEE', 'EN_COURS'], true)) {
            return $this->refus('ETAT_INCOMPATIBLE', 'annulation impossible : rejeu déjà démarré ou terminé');
        }
        $this->magasin->prepare("UPDATE demande_rejeu SET etat = 'ANNULEE', termine_le = ? WHERE reference = ?")
            ->execute([gmdate('c'), $reference]);

        return ['reference' => $reference, 'etat' => 'ANNULEE'];
    }

    /** @return array<string,mixed> */
    public function executerRejeu(string $reference, int $limiteLot = 200): array
    {
        $demande = $this->ligneDemande($reference);
        if ($demande === null) {
            return $this->refus('DEMANDE_INCONNUE', "demande `{$reference}` inconnue");
        }
        if (!in_array($demande['etat'], ['VALIDEE', 'EN_COURS'], true)) {
            return $this->refus('ETAT_INCOMPATIBLE', 'seule une demande VALIDEE ou EN_COURS s’exécute');
        }
        if ($demande['etat'] === 'VALIDEE') {
            $this->magasin->prepare("UPDATE demande_rejeu SET etat = 'EN_COURS' WHERE reference = ?")->execute([$reference]);
        }

        $sql = 'SELECT sequence_id, reference FROM evenement_commun WHERE 1=1';
        $args = [];
        if ($demande['sequence_debut'] !== null) {
            $sql .= ' AND sequence_id >= ?';
            $args[] = (int) $demande['sequence_debut'];
        }
        if ($demande['sequence_fin'] !== null) {
            $sql .= ' AND sequence_id <= ?';
            $args[] = (int) $demande['sequence_fin'];
        }
        if ($demande['date_debut'] !== null) {
            $sql .= ' AND survenu_le >= ?';
            $args[] = (string) $demande['date_debut'];
        }
        if ($demande['date_fin'] !== null) {
            $sql .= ' AND survenu_le <= ?';
            $args[] = (string) $demande['date_fin'];
        }
        $types = json_decode((string) $demande['types_json'], true) ?: [];
        if ($types !== []) {
            $sql .= ' AND type_evenement IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            array_push($args, ...$types);
        }
        $sql .= ' ORDER BY sequence_id LIMIT ?';
        $args[] = $limiteLot;

        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $lignes = $st->fetchAll();

        $abonnement = (string) $demande['abonnement_reference'];
        $traites = 0;
        foreach ($lignes as $ligne) {
            $this->reouvrirOuCreerLivraison($abonnement, (string) $ligne['reference'], (int) $ligne['sequence_id'], $reference);
            $traites++;
        }

        if (count($lignes) < $limiteLot) {
            $this->magasin->prepare("UPDATE demande_rejeu SET etat = 'TERMINEE', termine_le = ? WHERE reference = ?")
                ->execute([gmdate('c'), $reference]);

            return ['reference' => $reference, 'etat' => 'TERMINEE', 'traites' => $traites];
        }

        return ['reference' => $reference, 'etat' => 'EN_COURS', 'traites' => $traites];
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudreDemande(string $reference): ?array
    {
        $l = $this->ligneDemande($reference);

        return $l === null ? null : $this->projeter($l);
    }

    /** @return list<array<string,mixed>> */
    public function listerDemandes(?string $abonnement = null): array
    {
        if ($abonnement === null) {
            $st = $this->magasin->query('SELECT * FROM demande_rejeu ORDER BY cree_le');

            return array_map(fn (array $l): array => $this->projeter($l), $st->fetchAll());
        }
        $st = $this->magasin->prepare('SELECT * FROM demande_rejeu WHERE abonnement_reference = ? ORDER BY cree_le');
        $st->execute([$abonnement]);

        return array_map(fn (array $l): array => $this->projeter($l), $st->fetchAll());
    }

    /** @return array<string,mixed> */
    private function projeter(array $l): array
    {
        return [
            'reference' => $l['reference'],
            'abonnement_reference' => $l['abonnement_reference'],
            'sequence_debut' => $l['sequence_debut'] !== null ? (int) $l['sequence_debut'] : null,
            'sequence_fin' => $l['sequence_fin'] !== null ? (int) $l['sequence_fin'] : null,
            'date_debut' => $l['date_debut'],
            'date_fin' => $l['date_fin'],
            'types' => json_decode((string) $l['types_json'], true) ?: [],
            'motif' => $l['motif'],
            'etat' => $l['etat'],
            'demandeur_reference' => $l['demandeur_reference'],
            'volume_estime' => $l['volume_estime'] !== null ? (int) $l['volume_estime'] : null,
            'cree_le' => $l['cree_le'],
            'termine_le' => $l['termine_le'],
        ];
    }

    private function reouvrirOuCreerLivraison(string $abonnement, string $evenementReference, int $sequenceId, string $demandeRejeu): void
    {
        $st = $this->magasin->prepare(
            'SELECT reference FROM livraison_evenement WHERE abonnement_reference = ? AND evenement_reference = ?'
        );
        $st->execute([$abonnement, $evenementReference]);
        $existante = $st->fetchColumn();

        if ($existante !== false) {
            $this->magasin->prepare(
                "UPDATE livraison_evenement
                 SET etat = 'DISPONIBLE', bail_reference = NULL, bail_expire_le = NULL,
                     prochaine_tentative_le = NULL, rejeu = 1, demande_rejeu_reference = ?
                 WHERE reference = ?"
            )->execute([$demandeRejeu, $existante]);
            $this->trace((string) $existante, 'REJEU', 'RELANCE');

            return;
        }

        $reference = 'LIV-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
        $this->magasin->prepare(
            "INSERT INTO livraison_evenement
             (reference,abonnement_reference,evenement_reference,sequence_evenement,etat,disponible_le,rejeu,demande_rejeu_reference,cree_le)
             VALUES(?,?,?,?,'DISPONIBLE',?,1,?,?)"
        )->execute([$reference, $abonnement, $evenementReference, $sequenceId, gmdate('c'), $demandeRejeu, gmdate('c')]);
        $this->trace($reference, 'REJEU', 'MISE_A_DISPOSITION');
    }

    private function trace(string $livraison, string $type, string $resultat): void
    {
        $st = $this->magasin->prepare('SELECT COALESCE(MAX(numero),0) + 1 FROM tentative_livraison WHERE livraison_reference = ?');
        $st->execute([$livraison]);
        $numero = (int) $st->fetchColumn();
        $this->magasin->prepare(
            'INSERT INTO tentative_livraison(livraison_reference,numero,type_tentative,resultat,commence_le,termine_le)
             VALUES(?,?,?,?,?,?)'
        )->execute([$livraison, $numero, $type, $resultat, gmdate('c'), gmdate('c')]);
    }

    private function estimerVolume(string $abonnement, ?int $debut, ?int $fin, ?string $dateDebut, ?string $dateFin, array $types): int
    {
        $sql = 'SELECT COUNT(*) FROM evenement_commun WHERE 1=1';
        $args = [];
        if ($debut !== null) {
            $sql .= ' AND sequence_id >= ?';
            $args[] = $debut;
        }
        if ($fin !== null) {
            $sql .= ' AND sequence_id <= ?';
            $args[] = $fin;
        }
        if ($dateDebut !== null) {
            $sql .= ' AND survenu_le >= ?';
            $args[] = $dateDebut;
        }
        if ($dateFin !== null) {
            $sql .= ' AND survenu_le <= ?';
            $args[] = $dateFin;
        }
        if ($types !== []) {
            $sql .= ' AND type_evenement IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            array_push($args, ...$types);
        }
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        return (int) $st->fetchColumn();
    }

    private function ligneDemande(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM demande_rejeu WHERE reference = ?');
        $st->execute([$reference]);
        $l = $st->fetch();

        return $l === false ? null : $l;
    }

    /** @return array{refus:string,detail:string} */
    private function refus(string $code, string $detail): array
    {
        return ['refus' => $code, 'detail' => $detail];
    }
}
