<?php

declare(strict_types=1);

namespace Gamad\RegistreAutorites;

/**
 * Les trois opérations de lecture du contrat CTR-02 — Registre des autorités
 * et mandats (CAP-CORE-003, conception adoptée par ADOPTION-0035).
 *
 * Lecture et attestation seulement : le service ne nomme pas, ne suspend pas,
 * ne révoque pas (INV-4). Il restitue ce que les actes ont décidé.
 *
 * L'invariant central est INV-14 : un mandat se vérifie À LA DATE DE L'ACTE,
 * jamais au présent. La question n'est pas « X détient-il la fonction ? » mais
 * « X la détenait-il le jour où il a signé ? ».
 */
final class Ctr02
{
    /**
     * La capacité souveraine que ce module sert (INV-41).
     *
     * Une famille de contrat peut servir deux capacités — `CTR-10` sert
     * l'audit et l'intégrité. Le numéro de famille ne suffit donc pas à
     * rattacher un module ; le module le déclare lui-même.
     */
    public const CAPACITE = 'CAP-CORE-003';

    public function __construct(
        private \PDO $pdo,
    ) {
    }

    /**
     * Résout le mandat couvrant une fonction, ou détenu par un titulaire, à une
     * date donnée. Sans date : l'état courant.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreMandat(
        ?string $fonction = null,
        ?string $titulaire = null,
        ?string $date = null,
    ): ?array {
        $sql = 'SELECT m.reference, m.fonction_reference, m.titulaire_reference,
                       m.debut, m.fin, m.niveau_preuve, m.adoption_reference,
                       t.libelle AS titulaire_libelle, f.libelle AS fonction_libelle
                FROM mandat m
                JOIN titulaire t ON t.reference = m.titulaire_reference
                JOIN fonction  f ON f.reference = m.fonction_reference
                WHERE 1 = 1';
        $args = [];

        if ($fonction !== null) {
            $sql .= ' AND m.fonction_reference = ?';
            $args[] = $fonction;
        }
        if ($titulaire !== null) {
            $sql .= ' AND m.titulaire_reference = ?';
            $args[] = $titulaire;
        }
        // INV-13 — non-rétroactivité : un mandat ne couvre rien avant son début.
        if ($date !== null) {
            $sql .= ' AND m.debut <= ? AND (m.fin IS NULL OR m.fin >= ?)';
            $args[] = $date;
            $args[] = $date;
        }
        $sql .= ' ORDER BY m.debut DESC LIMIT 1';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $m = $st->fetch();
        if ($m === false) {
            return null;
        }

        return [
            'mandat'             => $m['reference'],
            'fonction'           => $m['fonction_reference'],
            'fonction_libelle'   => $m['fonction_libelle'],
            'titulaire'          => $m['titulaire_reference'],
            'titulaire_libelle'  => $m['titulaire_libelle'],
            'etat'               => $this->etatMandat($m['reference'], $date),
            'debut'              => $m['debut'],
            'fin'                => $m['fin'],
            'niveau_preuve'      => $m['niveau_preuve'],
            'adoption_reference' => $m['adoption_reference'],
        ];
    }

    /**
     * L'autorité signataire d'un acte détenait-elle un mandat valide À LA DATE
     * de cet acte (INV-14) ?
     *
     * Quatre verdicts, et un seul d'entre eux affirme une vérification :
     *
     *   VÉRIFIÉ     un mandat actif à cette date couvre l'acte ;
     *   CONSTITUTIF l'acte est contemporain de la fondation du mandat qu'il
     *               invoquerait — la chaîne s'y arrête (INV-15). Tout ordre
     *               normatif se termine dans un acte qui FONDE l'autorité au
     *               lieu de la dériver ; le dire vaut mieux que feindre une
     *               vérification qui n'a pas eu lieu ;
     *   NON COUVERT aucun mandat actif à cette date — anomalie signalée ;
     *   INDETERMINE le corpus ne permet pas de conclure.
     *
     * @return array<string,mixed>|null
     */
    public function verifierActe(string $adoption): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT reference, autorite, date_adoption FROM adoption WHERE reference = ?'
        );
        $st->execute([$adoption]);
        $acte = $st->fetch();
        if ($acte === false) {
            return null;
        }

        $date = $this->dateIso((string) $acte['date_adoption']);
        $base = [
            'acte'               => $acte['reference'],
            'date'               => $date,
            'signataire_declare' => $acte['autorite'],
        ];

        if ($date === null) {
            return $base + ['mandat_couvrant' => null, 'verdict' => 'INDETERMINE',
                'motif' => 'date de l\'acte non lisible'];
        }

        // Le mandat est rapproché du signataire déclaré. Aucune autorité n'est
        // présumée d'un nom approchant (INV-12) : la correspondance doit être
        // établie, faute de quoi le verdict demeure INDETERMINE.
        $mandats = $this->pdo->query(
            'SELECT m.reference, m.debut, m.fin, t.libelle
             FROM mandat m JOIN titulaire t ON t.reference = m.titulaire_reference'
        )->fetchAll();

        foreach ($mandats as $m) {
            if (!$this->memeTitulaire((string) $acte['autorite'], (string) $m['libelle'])) {
                continue;
            }

            if ($date === $m['debut']) {
                return $base + [
                    'mandat_couvrant' => $m['reference'],
                    'verdict'         => 'CONSTITUTIF',
                    'motif'           => 'acte contemporain du début du mandat qu\'il invoquerait ; '
                        . 'la chaîne de mandats s\'arrête ici (INV-15)',
                ];
            }
            if ($date < $m['debut']) {
                continue; // INV-13 : aucune couverture rétroactive
            }
            if ($m['fin'] !== null && $date > $m['fin']) {
                continue;
            }
            if (!$this->mandatActifA((string) $m['reference'], $date)) {
                continue;
            }

            return $base + [
                'mandat_couvrant' => $m['reference'],
                'verdict'         => 'VÉRIFIÉ',
                'motif'           => null,
            ];
        }

        return $base + [
            'mandat_couvrant' => null,
            'verdict'         => $mandats === [] ? 'INDETERMINE' : 'NON COUVERT',
            'motif'           => $mandats === []
                ? 'aucun mandat inscrit'
                : 'aucun mandat actif à cette date ne couvre le signataire déclaré',
        ];
    }

    /**
     * Fonctions demeurant vacantes, à une date donnée ou aujourd'hui.
     *
     * La vacance est un fait institutionnel majeur : elle est exposée, jamais
     * masquée. Une fonction sans acte de nomination est vacante quelles que
     * soient les apparences de son exercice (INV-12).
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreVacance(?string $date = null): array
    {
        $lignes = [];
        foreach ($this->pdo->query('SELECT reference, libelle, source FROM fonction ORDER BY reference')->fetchAll() as $f) {
            $etat = $this->etatFonction((string) $f['reference'], $date);
            if ($etat === null || !str_contains($etat['valeur'], 'VACANTE')) {
                continue;
            }
            $lignes[] = [
                'fonction'           => $f['reference'],
                'libelle'            => $f['libelle'],
                'etat'               => $etat['valeur'],
                'depuis'             => $etat['date_effet'],
                'adoption_reference' => $etat['adoption_reference'],
                'source'             => $f['source'],
            ];
        }

        return $lignes;
    }

    // ---- utilitaires ------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function etatFonction(string $fonction, ?string $date): ?array
    {
        $sql = 'SELECT valeur, date_effet, adoption_reference FROM etat_fonction
                WHERE fonction_reference = ?';
        $args = [$fonction];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);

        return $st->fetch() ?: null;
    }

    private function etatMandat(string $mandat, ?string $date): ?string
    {
        $sql = 'SELECT valeur FROM etat_mandat WHERE mandat_reference = ?';
        $args = [$mandat];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $v = $st->fetchColumn();

        return $v === false ? null : (string) $v;
    }

    /** Un mandat éteint ne couvre aucun acte postérieur à son extinction. */
    private function mandatActifA(string $mandat, string $date): bool
    {
        $etat = $this->etatMandat($mandat, $date);

        return $etat !== null && str_starts_with($etat, 'ACTIF');
    }

    /**
     * Le signataire déclaré par l'acte est-il le titulaire du mandat ?
     * Le rapprochement exige une inclusion franche d'un nom dans l'autre ; il
     * ne se contente d'aucune ressemblance approximative.
     */
    private function memeTitulaire(string $signataire, string $titulaire): bool
    {
        $a = mb_strtolower(trim($signataire), 'UTF-8');
        $b = mb_strtolower(trim($titulaire), 'UTF-8');

        return $a !== '' && ($a === $b || str_contains($b, $a) || str_contains($a, $b));
    }

    private function dateIso(string $fr): ?string
    {
        static $mois = [
            'janvier' => '01', 'février' => '02', 'mars' => '03', 'avril' => '04',
            'mai' => '05', 'juin' => '06', 'juillet' => '07', 'août' => '08',
            'septembre' => '09', 'octobre' => '10', 'novembre' => '11', 'décembre' => '12',
        ];

        if (!preg_match('/(\d{1,2})\s+(\p{L}+)\s+(\d{4})/u', $fr, $m)) {
            return null;
        }
        $mm = $mois[mb_strtolower($m[2], 'UTF-8')] ?? null;

        return $mm === null ? null : sprintf('%s-%s-%02d', $m[3], $mm, (int) $m[1]);
    }
}
