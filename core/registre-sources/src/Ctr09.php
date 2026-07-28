<?php

declare(strict_types=1);

namespace Gamad\RegistreSources;

use Gamad\RegistreNormes\GitBlob;

/**
 * Les trois opérations de lecture du contrat CTR-09 — Registre des sources
 * (CAP-CORE-006, conception adoptée par ADOPTION-0032, Titre III, Article 12).
 *
 * Lecture et attestation seulement : aucune écriture applicative du corpus
 * (INV-4). Les seules écritures passent par des actes d'adoption signés.
 *
 * Ce service porte quatre invariants propres à la capacité :
 *
 *   INV-7  identité canonique — une source se désigne par sa référence, jamais
 *          par son chemin de fichier ; renommer un fichier ne renomme pas la
 *          source (menace M-6).
 *   INV-8  rang fondé, jamais inventé — le rang restitué est celui que le
 *          corpus établit. Tant qu'aucune autorité ne l'a qualifié, la valeur
 *          rendue est INDETERMINE : le service déclare son ignorance plutôt
 *          que de présumer un rang (Article 116 du registre des capacités).
 *   INV-9  authenticité distincte de l'adoption — qu'un acte adopte une source
 *          n'authentifie pas son contenu. Les deux valeurs sont rendues côte à
 *          côte et ne se contaminent jamais (menaces M-1, M-3).
 *   INV-11 non-effacement de la provenance — la lignée est tenue en ajout
 *          seul ; aucune relation n'est jamais supprimée (menace M-2).
 *
 * Ce service est le titulaire du contrat CTR-09. CTR-04 (CAP-CORE-007) lui
 * délègue la résolution des sources : le registre des normes dépend des
 * sources, et non l'inverse (Article 42 du registre des capacités).
 */
final class Ctr09
{
    public function __construct(
        private \PDO $pdo,
        private string $corpus,
    ) {
    }

    /**
     * Résout une source reconnue : son identité, sa catégorie, son niveau
     * d'authenticité et — si le corpus la connaît aussi comme norme versionnée
     * — son rang, son statut et l'acte qui l'a adoptée.
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
     * Vérifie l'authenticité matérielle d'une source : recalcule l'empreinte du
     * fichier porteur et la compare à celle que le corpus déclare (INV-1).
     *
     * L'empreinte réelle n'est jamais recopiée depuis l'index : elle est
     * recalculée à partir du fichier, faute de quoi la vérification ne
     * vérifierait rien.
     *
     * `verifiable` est faux pour une source que le corpus ne porte pas en
     * fichier — statuts sur papier, silsila, archive externe. Dans ce cas
     * `concorde` vaut `null` et non `false` : rien n'a été comparé, et déclarer
     * une discordance non constatée serait un mensonge symétrique du silence
     * (INV-9).
     *
     * @return array<string,mixed>|null `null` si la source est inconnue
     */
    public function verifierAuthenticite(string $reference): ?array
    {
        $st = $this->pdo->prepare('SELECT reference, authenticite FROM source WHERE reference = ?');
        $st->execute([$reference]);
        $source = $st->fetch();
        if ($source === false) {
            return null;
        }

        $sv = $this->pdo->prepare(
            'SELECT chemin, empreinte_git FROM version_norme
             WHERE norme_reference = ? ORDER BY version DESC LIMIT 1'
        );
        $sv->execute([$reference]);
        $version = $sv->fetch();

        if ($version === false) {
            return [
                'reference'          => $source['reference'],
                'authenticite'       => $source['authenticite'],
                'chemin'             => null,
                'empreinte_declaree' => null,
                'empreinte_reelle'   => null,
                'concorde'           => null,
                'verifiable'         => false,
                'motif'              => 'Source non portée en fichier par le corpus : aucune empreinte à comparer.',
            ];
        }

        $fichier = $this->corpus . '/' . $version['chemin'];
        $present = is_file($fichier);
        $reelle  = $present ? GitBlob::hashFile($fichier) : null;

        return [
            'reference'          => $source['reference'],
            'authenticite'       => $source['authenticite'],
            'chemin'             => $version['chemin'],
            'empreinte_declaree' => $version['empreinte_git'],
            'empreinte_reelle'   => $reelle,
            'concorde'           => $present ? $reelle === $version['empreinte_git'] : false,
            'verifiable'         => true,
            'motif'              => $present ? null : 'Fichier déclaré absent du dépôt.',
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
     * dépendance va des normes vers les sources (Article 42 du registre des
     * capacités). L'inverse ferait dépendre la racine de ce qu'elle fonde.
     *
     * @return array<string,mixed>|null
     */
    private function versionEtStatut(string $reference, ?string $date): ?array
    {
        $sv = $this->pdo->prepare(
            'SELECT v.id, v.version, v.empreinte_git, v.chemin, n.rang_code
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
            'empreinte_git'      => $version['empreinte_git'],
            'chemin'             => $version['chemin'],
            'rang'               => $version['rang_code'],
            'statut'             => $statut['valeur'] ?? null,
            'date_effet'         => $statut['date_effet'] ?? null,
            'adoption_reference' => $statut['adoption_reference'] ?? null,
        ];
    }
}
