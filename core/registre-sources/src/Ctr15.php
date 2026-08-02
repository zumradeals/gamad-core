<?php

declare(strict_types=1);

namespace Gamad\RegistreSources;

/**
 * Contrat de lecture canonique du registre des sources (CAP-CORE-006).
 *
 * CTR-15 lit exclusivement le magasin persistant de CAP-CORE-006 — jamais les
 * tables `norme`, `version_norme`, `statut`, `adoption` ou `relation_evolution`
 * du registre des normes. La dépendance correcte va de CAP-CORE-007 vers
 * CAP-CORE-006, jamais l'inverse (fiche CAP-CORE-006, §7) : le registre des
 * normes peut enrichir une projection historique à partir de ce que CTR-15
 * rend, CTR-15 ne le lui doit jamais.
 *
 * Lecture seulement : aucune écriture n'est exposée ici. Les commandes
 * gouvernées vivent dans {@see RegistreSources}, appelées depuis la couche
 * applicative après décision de CAP-CORE-004.
 *
 * Une référence inconnue rend toujours `null`, jamais un rapprochement
 * approché (INV-7). La lignée distingue « source inconnue » (`null`) de
 * « source connue sans lignée » (deux listes vides) (INV-11).
 */
final class Ctr15
{
    /** La capacité que ce module sert. */
    public const CAPACITE = 'CAP-CORE-006';

    public function __construct(private ?\PDO $magasin = null)
    {
        $this->magasin ??= Magasin::ouvrir();
    }

    /**
     * La fiche courante d'une source : identité, état, révision courante.
     * Sans date : l'état courant. Avec une date : l'état à cette date.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreSource(string $reference, ?string $date = null): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM source WHERE reference = ?');
        $st->execute([$reference]);
        $s = $st->fetch();
        if ($s === false) {
            return null;
        }

        $cycle = $this->dernierCycle($reference, $date);
        $revision = $this->derniereRevision($reference, $date);

        return [
            'reference' => $s['reference'],
            'nom_canonique' => $s['nom_canonique'],
            'type_source' => $s['type_source'],
            'authenticite_legacy' => $s['authenticite_legacy'],
            'nom_affichage' => $revision['nom_affichage'] ?? $s['nom_canonique'],
            'categorie' => $revision['categorie'] ?? null,
            'description' => $revision['description'] ?? null,
            'proprietaire_reference' => $revision['proprietaire_reference'] ?? null,
            'produit_producteur_reference' => $revision['produit_producteur_reference'] ?? null,
            'reserve' => $revision['reserve'] ?? null,
            'numero_revision' => $revision !== null ? (int) $revision['numero_revision'] : null,
            'etat' => $cycle['etat'] ?? 'PREPARATION',
            'depuis' => $cycle['date_effet'] ?? null,
            'cree_le' => $s['cree_le'],
            'modifie_le' => $s['modifie_le'],
            'regime' => 'REGISTRE_PERSISTANT',
        ];
    }

    /**
     * La vérification opérationnelle courante d'une source, ou `null` si
     * aucune n'a jamais été enregistrée.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreVerificationCourante(string $reference, ?string $date = null): ?array
    {
        $sql = 'SELECT * FROM source_verification WHERE source_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND verifie_le <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY verifie_le DESC, id DESC LIMIT 1';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $v = $st->fetch();

        return $v === false ? null : [
            'source_reference' => $v['source_reference'],
            'niveau' => $v['niveau'],
            'resultat' => $v['resultat'],
            'verifie_par_reference' => $v['verifie_par_reference'],
            'verifie_le' => $v['verifie_le'],
            'expire_le' => $v['expire_le'],
        ];
    }

    /**
     * Les finalités actives déclarées pour une source.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreFinalites(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM source_finalite WHERE source_reference = ? AND actif = 1 ORDER BY date_debut, id'
        );
        $st->execute([$reference]);

        return array_map(static fn (array $f): array => [
            'finalite_reference' => $f['finalite_reference'],
            'produit_consommateur_reference' => $f['produit_consommateur_reference'],
            'date_debut' => $f['date_debut'],
            'date_fin' => $f['date_fin'],
        ], $st->fetchAll());
    }

    /**
     * Restitue la lignée d'une source : ses parentes (amont, ce dont elle
     * dérive) et ses dérivées (aval, ce qui procède d'elle).
     *
     * Une source inconnue rend `null` ; une source connue sans lignée rend
     * deux listes vides. La distinction est délibérée : « je ne connais pas
     * cette source » et « cette source n'a pas de lignée » sont deux réponses
     * différentes, et les confondre masquerait une ignorance derrière un fait.
     *
     * @return array{reference:string,amont:list<array<string,mixed>>,aval:list<array<string,mixed>>}|null
     */
    public function resoudreLignee(string $reference): ?array
    {
        $existe = $this->magasin->prepare('SELECT 1 FROM source WHERE reference = ?');
        $existe->execute([$reference]);
        if ($existe->fetchColumn() === false) {
            return null;
        }

        $amont = $this->magasin->prepare(
            'SELECT source_parente_reference AS reference, type_relation, date_effet
             FROM source_lignee WHERE source_reference = ? ORDER BY id'
        );
        $amont->execute([$reference]);

        $aval = $this->magasin->prepare(
            'SELECT source_reference AS reference, type_relation, date_effet
             FROM source_lignee WHERE source_parente_reference = ? ORDER BY id'
        );
        $aval->execute([$reference]);

        return [
            'reference' => $reference,
            'amont' => array_values($amont->fetchAll()),
            'aval' => array_values($aval->fetchAll()),
        ];
    }

    /** @return array<string,mixed>|null */
    private function dernierCycle(string $reference, ?string $date): ?array
    {
        $sql = 'SELECT * FROM source_cycle WHERE source_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $c = $st->fetch();

        return $c === false ? null : $c;
    }

    /** @return array<string,mixed>|null */
    private function derniereRevision(string $reference, ?string $date): ?array
    {
        $sql = 'SELECT * FROM source_revision WHERE source_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, numero_revision DESC LIMIT 1';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $r = $st->fetch();

        return $r === false ? null : $r;
    }
}
