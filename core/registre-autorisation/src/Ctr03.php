<?php

declare(strict_types=1);

namespace Gamad\RegistreAutorisation;

/**
 * Contrat CTR-03 — Moteur d'autorisation commun (CAP-CORE-004).
 *
 * Le moteur ÉVALUE ; il ne décide pas des règles. Celles-ci vivent dans le
 * registre persistant et gouverné de CAP-CORE-007 (`core/registre-politiques`)
 * — plus jamais dans l'index documentaire reconstructible. Changer une règle
 * exige une version soumise, simulée et activée, non un correctif direct
 * (INV-29 continue de s'appliquer : aucune règle n'est écrite ici).
 *
 * Deux invariants gouvernent toute décision :
 *
 *   INV-27 — REFUS PAR DÉFAUT. L'absence de règle n'est jamais une permission.
 *   INV-30 — Une limite du mandat s'oppose à son TITULAIRE comme à quiconque.
 *            Aucune qualité, aucune urgence, aucune instruction ne la lève.
 *
 * Le moteur n'empêche rien physiquement (M-29) : il dit ce qui est permis.
 * Il transforme un franchissement silencieux en franchissement constatable ;
 * c'est moins qu'un verrou, et c'est tout ce qu'un Core peut offrir tant que
 * la séparation des fonctions n'est pas réelle.
 *
 * Seules les versions dont l'état courant est `ACTIVE` sont considérées : une
 * version en `BROUILLON`, `EN_VALIDATION`, `SUSPENDUE`, `REMPLACEE` ou
 * `RETIREE` ne permet jamais rien.
 */
final class Ctr03
{
    /**
     * La capacité souveraine que ce module sert (INV-41).
     *
     * Une famille de contrat peut servir deux capacités — `CTR-10` sert
     * l'audit et l'intégrité. Le numéro de famille ne suffit donc pas à
     * rattacher un module ; le module le déclare lui-même.
     */
    public const CAPACITE = 'CAP-CORE-004';

    public function __construct(
        private \PDO $pdo,
    ) {
    }

    /**
     * Décide si un sujet peut accomplir une action.
     *
     * @return array<string,mixed>
     */
    public function autoriser(string $sujet, string $action, ?string $ressource = null): array
    {
        $demandee = $this->normaliser($action);

        $st = $this->pdo->query(
            "SELECT rp.effet, rp.action_reference AS action, rp.motif, rp.sujet_reference AS sujet_type,
                    pv.politique_reference AS politique, pv.version, p.source_reference AS source
             FROM regle_politique rp
             JOIN politique_version pv ON pv.id = rp.politique_version_id
             JOIN politique p ON p.reference = pv.politique_reference
             JOIN politique_version_cycle pvc ON pvc.politique_version_id = pv.id
             WHERE pvc.etat = 'ACTIVE'
               AND pvc.id = (
                   SELECT id FROM politique_version_cycle
                   WHERE politique_version_id = pv.id
                   ORDER BY date_effet DESC, id DESC LIMIT 1
               )
             ORDER BY rp.id"
        );

        $permission = null;
        foreach ($st->fetchAll() as $r) {
            // `sujet_type` nul : la règle vaut pour tout sujet, titulaire compris.
            if ($r['sujet_type'] !== null && $r['sujet_type'] !== $sujet) {
                continue;
            }
            if (!$this->correspond($demandee, $this->normaliser((string) $r['action']))) {
                continue;
            }

            // INV-30 : un REFUSE l'emporte toujours, sans considération du sujet.
            if ($r['effet'] === 'REFUSE') {
                return [
                    'decision'  => 'REFUSÉ',
                    'sujet'     => $sujet,
                    'action'    => $action,
                    'ressource' => $ressource,
                    'motif'     => $r['motif'],
                    'politique' => $r['politique'],
                    'version'   => $r['version'],
                    'source'    => $r['source'],
                ];
            }

            $permission ??= $r;
        }

        if ($permission !== null) {
            return [
                'decision'  => 'PERMIS',
                'sujet'     => $sujet,
                'action'    => $action,
                'ressource' => $ressource,
                'motif'     => $permission['motif'],
                'politique' => $permission['politique'],
                'version'   => $permission['version'],
                'source'    => $permission['source'],
            ];
        }

        // INV-27 — refus par défaut. Une décision sans motif serait un défaut,
        // non une commodité (INV-28) : le motif dit l'absence de règle.
        return [
            'decision'  => 'REFUSÉ',
            'sujet'     => $sujet,
            'action'    => $action,
            'ressource' => $ressource,
            'motif'     => 'aucune version active de politique ne permet cette action ; '
                . "l'absence de règle n'est jamais une permission (INV-27)",
            'politique' => null,
            'version'   => null,
            'source'    => null,
        ];
    }

    /**
     * Évalue sans effet ni trace. Identique à `autoriser`, marquée simulation.
     *
     * Distincte de `RegistrePolitiques::simulerVersion()`, qui simule une
     * version candidate encore `EN_VALIDATION` avant activation. Celle-ci
     * évalue toujours les versions déjà `ACTIVE` — c'est un aperçu de la
     * décision réelle, pas un jeu de non-régression.
     *
     * @return array<string,mixed>
     */
    public function simuler(string $sujet, string $action, ?string $ressource = null): array
    {
        return ['simulation' => true] + $this->autoriser($sujet, $action, $ressource);
    }

    /**
     * Ce qui est interdit, à qui, et en vertu de quel texte.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreInterdits(?string $sujet = null): array
    {
        $st = $this->pdo->query(
            "SELECT rp.action_reference AS action, rp.motif, rp.sujet_reference AS sujet_type,
                    pv.politique_reference AS politique, p.source_reference AS source
             FROM regle_politique rp
             JOIN politique_version pv ON pv.id = rp.politique_version_id
             JOIN politique p ON p.reference = pv.politique_reference
             JOIN politique_version_cycle pvc ON pvc.politique_version_id = pv.id
             WHERE rp.effet = 'REFUSE'
               AND pvc.etat = 'ACTIVE'
               AND pvc.id = (
                   SELECT id FROM politique_version_cycle
                   WHERE politique_version_id = pv.id
                   ORDER BY date_effet DESC, id DESC LIMIT 1
               )
             ORDER BY rp.id"
        );

        $lignes = [];
        foreach ($st->fetchAll() as $r) {
            if ($sujet !== null && $r['sujet_type'] !== null && $r['sujet_type'] !== $sujet) {
                continue;
            }
            $lignes[] = [
                'action'      => $r['action'],
                'motif'       => $r['motif'],
                'opposable_a' => $r['sujet_type'] ?? 'tout sujet, titulaire du mandat compris',
                'politique'   => $r['politique'],
                'source'      => $r['source'],
            ];
        }

        return $lignes;
    }

    /**
     * Une demande correspond à une règle si, et seulement si, leurs formes
     * normalisées sont strictement égales. Le rapprochement par sous-chaîne
     * a été retiré : il n'était exercé par aucun appelant réel du dépôt (voir
     * le chantier CAP-CORE-007), et une correspondance approchée n'a plus sa
     * place dans un moteur qui décide d'une permission.
     */
    private function correspond(string $demandee, string $reglee): bool
    {
        return $demandee !== '' && $demandee === $reglee;
    }

    private function normaliser(string $action): string
    {
        $a = preg_replace('/^(de |d\'|d’)/u', '', mb_strtolower(trim($action), 'UTF-8')) ?? $action;
        $a = preg_replace('/[^\p{L}\p{N}]+/u', '-', $a) ?? $a;

        return trim(mb_substr($a, 0, 64, 'UTF-8'), '-');
    }
}
