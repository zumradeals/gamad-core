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
     * Résout une source reconnue : sa fiche canonique du registre persistant
     * de CAP-CORE-006, enrichie — quand elle existe — d'une projection
     * normative historique.
     *
     * L'opération délègue d'abord à `CTR-15`, seul titulaire du contrat des
     * sources : le registre des normes dépend des sources, jamais l'inverse
     * (fiche CAP-CORE-006, §7). `CTR-15` ne connaît plus les tables `norme`,
     * `version_norme`, `statut`, `adoption` ni `relation_evolution` ; c'est ici,
     * dans CAP-CORE-007, qu'une projection de compatibilité leur est
     * éventuellement ajoutée pour les appelants existants qui lisent encore
     * `titre`, `categorie`, `authenticite`, `rang`, `statut`,
     * `adoption_reference` ou `versionnee`.
     *
     * Cette projection reste facultative : l'absence d'une version normative
     * correspondante ne fait jamais échouer la résolution, et son contenu
     * n'est jamais requis pour que `CTR-15` fonctionne (fiche §16.2).
     *
     * @return array<string,mixed>|null
     */
    public function resoudreSource(string $reference, ?string $date = null): ?array
    {
        $source = (new Ctr15())->resoudreSource($reference, $date);
        if ($source === null) {
            return null;
        }

        $verification = (new Ctr15())->resoudreVerificationCourante($reference, $date);
        $finalites = (new Ctr15())->resoudreFinalites($reference);
        $projectionLegacy = $this->projectionSourceLegacy($reference, $date);

        return array_merge($projectionLegacy, [
            'reference' => $source['reference'],
            'etat' => $source['etat'],
            'proprietaire' => $source['proprietaire_reference'],
            'produit_producteur' => $source['produit_producteur_reference'],
            'niveau_verification' => $verification['niveau'] ?? 'NON_VERIFIEE',
            'verification_expire_le' => $verification['expire_le'] ?? null,
            'finalites' => $finalites,
            'regime' => 'REGISTRE_PERSISTANT',
        ]);
    }

    /**
     * Projection de compatibilité historique, construite ici — jamais dans
     * `CTR-15` — à partir de l'ancien index documentaire. Une source inscrite
     * après le passage de CAP-CORE-006 à GO n'y figure généralement pas : dans
     * ce cas, la projection retombe sur les données du registre persistant
     * plutôt que d'inventer une valeur.
     *
     * @return array<string,mixed>
     */
    private function projectionSourceLegacy(string $reference, ?string $date): array
    {
        $st = $this->pdo->prepare(
            'SELECT reference, titre, categorie, authenticite, reserve FROM source WHERE reference = ?'
        );
        $st->execute([$reference]);
        $legacy = $st->fetch();

        if ($legacy === false) {
            $courant = (new Ctr15())->resoudreSource($reference, $date) ?? [];

            return [
                'titre' => $courant['nom_affichage'] ?? null,
                'categorie' => $courant['categorie'] ?? null,
                'authenticite' => $courant['authenticite_legacy'] ?? null,
                'reserve' => $courant['reserve'] ?? null,
                'rang' => 'INDETERMINE',
                'statut' => null,
                'adoption_reference' => null,
                'versionnee' => false,
            ];
        }

        $version = $this->versionEtStatutLegacy($reference, $date);

        return [
            'titre' => $legacy['titre'],
            'categorie' => $legacy['categorie'],
            'authenticite' => $legacy['authenticite'],
            'reserve' => $legacy['reserve'],
            'rang' => $version['rang'] ?? 'INDETERMINE',
            'statut' => $version['statut'] ?? null,
            'adoption_reference' => $version['adoption_reference'] ?? null,
            'versionnee' => $version !== null,
        ];
    }

    /**
     * Relève la version courante d'une source qui est aussi une norme
     * versionnée dans l'ancien index, et le statut en vigueur à la date
     * demandée. Réservé à la projection de compatibilité ci-dessus.
     *
     * @return array<string,mixed>|null
     */
    private function versionEtStatutLegacy(string $reference, ?string $date): ?array
    {
        $sv = $this->pdo->prepare(
            'SELECT v.id, v.version, n.rang_code
             FROM version_norme v JOIN norme n ON n.reference = v.norme_reference
             WHERE v.norme_reference = ? ORDER BY v.version DESC LIMIT 1'
        );
        $sv->execute([$reference]);
        $version = $sv->fetch();
        if ($version === false) {
            return null;
        }

        if ($date !== null) {
            $ss = $this->pdo->prepare(
                'SELECT valeur, date_effet, adoption_reference FROM statut
                 WHERE version_norme_id = ? AND date_effet <= ?
                 ORDER BY date_effet DESC, id DESC LIMIT 1'
            );
            $ss->execute([$version['id'], $date]);
        } else {
            $ss = $this->pdo->prepare(
                'SELECT valeur, date_effet, adoption_reference FROM statut
                 WHERE version_norme_id = ?
                 ORDER BY date_effet DESC, id DESC LIMIT 1'
            );
            $ss->execute([$version['id']]);
        }
        $statut = $ss->fetch() ?: null;

        return [
            'version' => $version['version'],
            'rang' => $version['rang_code'],
            'statut' => $statut['valeur'] ?? null,
            'date_effet' => $statut['date_effet'] ?? null,
            'adoption_reference' => $statut['adoption_reference'] ?? null,
        ];
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
