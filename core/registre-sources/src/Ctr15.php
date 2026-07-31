<?php

declare(strict_types=1);

namespace Gamad\RegistreSources;

/**
 * Les trois opérations de lecture du contrat CTR-15 — Registre des sources
 * (CAP-CORE-006, conception adoptée par ADOPTION-0032, Titre III, Article 12).
 *
 * Lecture seulement : le service interroge l'index technique et ne lit aucun
 * fichier.
 *
 * Ce service porte trois invariants propres à la capacité :
 *
 *   INV-7  identité canonique — une source se désigne par sa référence, jamais
 *          par un chemin de fichier ; renommer un fichier ne renomme pas la
 *          source.
 *   INV-8  rang fondé, jamais inventé — tant qu'aucune autorité ne l'a
 *          qualifié, la valeur rendue est INDETERMINE : le service déclare son
 *          ignorance plutôt que de présumer un rang.
 *   INV-11 non-effacement de la provenance — la lignée est tenue en ajout
 *          seul ; aucune relation n'est jamais supprimée.
 *
 * Ce service est le titulaire du contrat CTR-15. CTR-04 (CAP-CORE-007) lui
 * délègue la résolution des sources : le registre des normes dépend des
 * sources, et non l'inverse.
 */
final class Ctr15
{
    /** La capacité que ce module sert. */
    public const CAPACITE = 'CAP-CORE-006';

    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Résout une source reconnue : son identité, sa catégorie, son niveau
     * d'authenticité et — si elle est aussi connue comme norme versionnée —
     * son rang, son statut et l'acte qui l'a adoptée.
     *
     * Sans date : l'état courant. Avec une date : l'état à cette date.
     *
     * Une source inconnue rend `null` : le service n'invente aucune source
     * (INV-7). Une source connue mais non versionnée rend `versionnee = false`
     * — l'absence est déclarée, jamais comblée par une valeur par défaut.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreSource(string $reference, ?string $date = null): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT reference, titre, categorie, authenticite, reserve FROM source WHERE reference = ?'
        );
        $st->execute([$reference]);
        $source = $st->fetch();
        if ($source === false) {
            return null;
        }

        $version = $this->versionEtStatut($reference, $date);

        return [
            'reference'          => $source['reference'],
            'titre'              => $source['titre'],
            'categorie'          => $source['categorie'],
            'authenticite'       => $source['authenticite'],
            'reserve'            => $source['reserve'],
            'rang'               => $version['rang'] ?? 'INDETERMINE',
            'statut'             => $version['statut'] ?? null,
            'adoption_reference' => $version['adoption_reference'] ?? null,
            'versionnee'         => $version !== null,
        ];
    }

    /**
     * Restitue la lignée d'une source : ce dont elle procède et ce qui l'a
     * dépassée.
     *
     * `amont` — les normes que celle-ci amende, remplace ou abroge : sa
     * provenance. `aval` — celles qui l'ont amendée, remplacée ou abrogée : sa
     * supersession.
     *
     * Une source inconnue rend `null` ; une source connue sans lignée rend deux
     * listes vides. La distinction est délibérée : « je ne connais pas cette
     * source » et « cette source n'a pas de lignée » sont deux réponses
     * différentes, et les confondre masquerait une ignorance derrière un fait.
     *
     * @return array{reference:string,amont:list<array<string,mixed>>,aval:list<array<string,mixed>>}|null
     */
    public function resoudreLignee(string $reference): ?array
    {
        $st = $this->pdo->prepare('SELECT reference FROM source WHERE reference = ?');
        $st->execute([$reference]);
        if ($st->fetch() === false) {
            return null;
        }

        $amont = $this->pdo->prepare(
            'SELECT norme_cible AS reference, type, adoption_reference FROM relation_evolution
             WHERE norme_source = ? ORDER BY id'
        );
        $amont->execute([$reference]);

        $aval = $this->pdo->prepare(
            'SELECT norme_source AS reference, type, adoption_reference FROM relation_evolution
             WHERE norme_cible = ? ORDER BY id'
        );
        $aval->execute([$reference]);

        return [
            'reference' => $reference,
            'amont'     => $amont->fetchAll(\PDO::FETCH_ASSOC),
            'aval'      => $aval->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    /**
     * Relève la version courante d'une source qui est aussi une norme
     * versionnée, et le statut en vigueur à la date demandée.
     *
     * Requête propre au registre des sources, et non un appel à CTR-04 : la
     * dépendance va des normes vers les sources. L'inverse ferait dépendre la
     * racine de ce qu'elle fonde.
     *
     * @return array<string,mixed>|null
     */
    private function versionEtStatut(string $reference, ?string $date): ?array
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
                 WHERE version_norme_id = ? ORDER BY date_effet DESC, id DESC LIMIT 1'
            );
            $ss->execute([$version['id']]);
        }
        $statut = $ss->fetch() ?: null;

        return [
            'version'            => $version['version'],
            'rang'               => $version['rang_code'],
            'statut'             => $statut['valeur'] ?? null,
            'date_effet'         => $statut['date_effet'] ?? null,
            'adoption_reference' => $statut['adoption_reference'] ?? null,
        ];
    }
}
