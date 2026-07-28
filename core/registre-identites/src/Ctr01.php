<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Contrat CTR-01 — Identity Registry (CAP-CORE-001), conception adoptée par
 * ADOPTION-0038.
 *
 * Lecture et attestation seulement (INV-4) : créer, corriger, fusionner ou
 * clore une identité demeure un acte signé.
 *
 * INV-19 est tenu par la structure : il n'existe aucune colonne de profil, de
 * dossier métier, de réputation ou de jugement, donc aucune opération ne peut
 * en restituer. Un registre d'identités souverain qui accumulerait des
 * jugements sur les personnes deviendrait un instrument de pouvoir sur elles.
 */
final class Ctr01
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    /**
     * Résout une entité et son état, éventuellement à une date passée.
     * Une entité dissoute demeure consultable (INV-21).
     *
     * @return array<string,mixed>|null
     */
    public function resoudreIdentite(string $reference, ?string $date = null): ?array
    {
        $st = $this->pdo->prepare('SELECT reference, type, libelle, source FROM entite WHERE reference = ?');
        $st->execute([$reference]);
        $e = $st->fetch();
        if ($e === false) {
            return null;
        }

        $sql = 'SELECT valeur, date_effet, adoption_reference FROM etat_entite WHERE entite_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';
        $sq = $this->pdo->prepare($sql);
        $sq->execute($args);
        $etat = $sq->fetch() ?: null;

        return [
            'reference'          => $e['reference'],
            'type'               => $e['type'],
            'libelle'            => $e['libelle'],
            'etat'               => $etat['valeur'] ?? null,
            'date_effet'         => $etat['date_effet'] ?? null,
            'adoption_reference' => $etat['adoption_reference'] ?? null,
            'source'             => $e['source'],
        ];
    }

    /**
     * Inventaire des entités connues, éventuellement d'un seul type.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreInventaire(?string $type = null): array
    {
        $sql = 'SELECT reference, type, libelle FROM entite';
        $args = [];
        if ($type !== null) {
            $sql .= ' WHERE type = ?';
            $args[] = $type;
        }
        $sql .= ' ORDER BY type, reference';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);

        $lignes = [];
        foreach ($st->fetchAll() as $e) {
            $r = $this->resoudreIdentite((string) $e['reference']);
            $lignes[] = [
                'reference' => $e['reference'],
                'type'      => $e['type'],
                'libelle'   => $e['libelle'],
                'etat'      => $r['etat'] ?? null,
            ];
        }

        return $lignes;
    }

    /**
     * Dénominations portées par une même référence dans le corpus.
     *
     * Le service SIGNALE les divergences, il ne les tranche pas : retenir une
     * dénomination canonique est une qualification, réservée à l'autorité
     * (ADOPTION-0037, Art. 3). `divergente` vaut vrai dès qu'une référence
     * porte plus d'une dénomination.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreDenominations(?string $reference = null): array
    {
        $sql = 'SELECT entite_reference, libelle, source FROM denomination';
        $args = [];
        if ($reference !== null) {
            $sql .= ' WHERE entite_reference = ?';
            $args[] = $reference;
        }
        $sql .= ' ORDER BY entite_reference, id';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);

        $par = [];
        foreach ($st->fetchAll() as $d) {
            $par[$d['entite_reference']][$d['libelle']] = $d['source'];
        }

        $lignes = [];
        foreach ($par as $ref => $libelles) {
            $lignes[] = [
                'reference'  => $ref,
                'libelles'   => array_keys($libelles),
                'sources'    => array_values($libelles),
                'divergente' => count($libelles) > 1,
            ];
        }

        return $lignes;
    }
}
