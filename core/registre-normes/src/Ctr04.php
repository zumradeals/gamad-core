<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

use Gamad\RegistreSources\Ctr15;

/**
 * Les opérations de lecture du contrat CTR-04 — registre des normes.
 *
 * Lecture seulement : le service interroge l'index technique et ne lit aucun
 * fichier. L'initialisation contrôlée de cet index relève de
 * {@see BaselineOperationnelle}, jamais d'un appelant.
 */
final class Ctr04
{
    /** La capacité que ce module sert. */
    public const CAPACITE = 'CAP-CORE-007';

    public function __construct(
        private \PDO $pdo,
        private ?BaselineOperationnelle $baseline = null,
    ) {
    }

    /**
     * Résout une norme et son statut, éventuellement à une date passée.
     * Sans date : l'état courant. Avec une date : la version et le statut en
     * vigueur à cette date (INV-3, INV-6).
     *
     * @return array<string,mixed>|null
     */
    public function resoudreNorme(string $reference, ?string $version = null, ?string $date = null): ?array
    {
        $sql = 'SELECT v.id, v.norme_reference, v.version, n.rang_code
                FROM version_norme v JOIN norme n ON n.reference = v.norme_reference
                WHERE v.norme_reference = ?';
        $args = [$reference];
        if ($version !== null) {
            $sql .= ' AND v.version = ?';
            $args[] = $version;
        }
        $sql .= ' ORDER BY v.version DESC LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $v = $st->fetch();
        if ($v === false) {
            return null;
        }

        if ($date !== null) {
            $sq = $this->pdo->prepare(
                'SELECT valeur, date_effet, adoption_reference FROM statut
                 WHERE version_norme_id = ? AND date_effet <= ?
                 ORDER BY date_effet DESC, id DESC LIMIT 1'
            );
            $sq->execute([$v['id'], $date]);
        } else {
            $sq = $this->pdo->prepare(
                'SELECT valeur, date_effet, adoption_reference FROM statut
                 WHERE version_norme_id = ?
                 ORDER BY date_effet DESC, id DESC LIMIT 1'
            );
            $sq->execute([$v['id']]);
        }
        $statut = $sq->fetch() ?: null;

        return [
            'reference'          => $v['norme_reference'],
            'version'            => $v['version'],
            'rang'               => $v['rang_code'],
            'statut'             => $statut['valeur'] ?? null,
            'date_effet'         => $statut['date_effet'] ?? null,
            'adoption_reference' => $statut['adoption_reference'] ?? null,
            'en_vigueur'         => $statut !== null && !in_array($statut['valeur'], ['REMPLACE', 'ABROGE'], true),
        ];
    }

    /**
     * Résout une source reconnue : son identité, sa catégorie, son niveau
     * d'authenticité et, si elle est aussi connue comme norme, son statut.
     *
     * L'authenticité (`AUTH-0` à `AUTH-4`) et le statut d'adoption sont rendus
     * côte à côte mais jamais confondus : une source peut être authentifiée et
     * abrogée, ou de provenance seulement déclarée et faire règle. Le rang
     * rendu est `INDETERMINE` tant qu'aucune autorité ne l'a établi.
     *
     * L'opération délègue à `CTR-15`, seul titulaire du contrat des sources :
     * le registre des normes dépend des sources, jamais l'inverse. La méthode
     * demeure exposée ici pour les appelants existants, qui n'ont pas à
     * connaître ce déplacement.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreSource(string $reference, ?string $date = null): ?array
    {
        return (new Ctr15($this->pdo))->resoudreSource($reference, $date);
    }

    /**
     * Résout l'état d'une capacité souveraine, éventuellement à une date passée.
     *
     * Distincte de `resoudreNorme` à dessein : l'état d'une capacité
     * (`EN CONCEPTION`, `CONÇUE`, …) et le statut d'une norme
     * (`EN VIGUEUR`, `ABROGE`, …) sont deux vocabulaires que rien n'autorise à
     * mêler. En particulier, `en_vigueur` n'est pas calculé ici : la question
     * n'a pas de sens pour une capacité.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreCapacite(string $reference, string $dimension = 'conception', ?string $date = null): ?array
    {
        $sql = 'SELECT valeur, date_effet, adoption_reference FROM etat_capacite
                WHERE capacite_reference = ? AND dimension = ?';
        $args = [$reference, $dimension];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $etat = $st->fetch();

        if ($etat === false) {
            return null;
        }

        return [
            'reference'          => $reference,
            'dimension'          => $dimension,
            'valeur'             => $etat['valeur'],
            'date_effet'         => $etat['date_effet'],
            'adoption_reference' => $etat['adoption_reference'],
        ];
    }

    /**
     * Diagnostic opérationnel de l'index : intégrité de la source
     * d'initialisation et concordance des volumes réellement présents.
     *
     * Remplace les anciens contrôles d'empreinte de fichiers : l'index ne
     * dérive plus d'un corpus documentaire, et vérifier des empreintes de
     * fichiers absents ne prouverait rien.
     *
     * @return array{baseline:array<string,mixed>,index:array<string,mixed>,
     *               divergences:list<string>,coherent:bool}
     */
    public function diagnostiquerIndex(): array
    {
        return ($this->baseline ?? BaselineOperationnelle::standard())
            ->diagnostiquer($this->pdo);
    }
}
