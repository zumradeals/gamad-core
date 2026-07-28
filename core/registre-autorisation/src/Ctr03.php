<?php

declare(strict_types=1);

namespace Gamad\RegistreAutorisation;

/**
 * Contrat CTR-03 — Moteur d'autorisation commun (CAP-CORE-004).
 *
 * Le moteur ÉVALUE ; il ne décide pas des règles. Celles-ci sont dérivées du
 * corpus — Articles 48 et 49 du Registre des autorités — et jamais écrites
 * ici (INV-29). Changer une règle exige un acte, non un correctif.
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
            'SELECT r.effet, r.action, r.motif, r.sujet_type, p.reference AS politique, p.version, p.source
             FROM regle r JOIN politique p ON p.reference = r.politique_reference
             ORDER BY r.id'
        );

        $permission = null;
        foreach ($st->fetchAll() as $r) {
            // `sujet_type` nul : la règle vaut pour tout sujet, titulaire compris.
            if ($r['sujet_type'] !== null && $r['sujet_type'] !== $sujet) {
                continue;
            }
            if (!$this->correspond($demandee, (string) $r['action'])) {
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
            'motif'     => 'aucune politique adoptée ne permet cette action ; '
                . "l'absence de règle n'est jamais une permission (INV-27)",
            'politique' => null,
            'version'   => null,
            'source'    => null,
        ];
    }

    /**
     * Évalue sans effet ni trace. Identique à `autoriser`, marquée simulation.
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
            "SELECT r.action, r.motif, r.sujet_type, p.reference AS politique, p.source
             FROM regle r JOIN politique p ON p.reference = r.politique_reference
             WHERE r.effet = 'REFUSE' ORDER BY r.id"
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
     * Une demande correspond à une règle si leurs formes normalisées sont
     * égales, ou si l'une contient l'autre. Le rapprochement demeure lexical :
     * le moteur n'interprète pas le sens des énoncés, il les rapproche.
     */
    private function correspond(string $demandee, string $reglee): bool
    {
        if ($demandee === '' || $reglee === '') {
            return false;
        }

        return $demandee === $reglee
            || str_contains($reglee, $demandee)
            || str_contains($demandee, $reglee);
    }

    private function normaliser(string $action): string
    {
        $a = preg_replace('/^(de |d\'|d’)/u', '', mb_strtolower(trim($action), 'UTF-8')) ?? $action;
        $a = preg_replace('/[^\p{L}\p{N}]+/u', '-', $a) ?? $a;

        return trim(mb_substr($a, 0, 64, 'UTF-8'), '-');
    }
}
