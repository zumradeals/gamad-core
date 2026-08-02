<?php

declare(strict_types=1);

namespace Gamad\RegistreSources;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreProduits\RegistreProduits;

/**
 * Registre opérationnel des sources (CAP-CORE-006).
 *
 * Une source n'est plus une ligne de l'index documentaire reconstructible :
 * elle possède une fiche persistante, un cycle de vie en ajout seul, des
 * révisions de métadonnées, des vérifications historisées et expirables, des
 * finalités bornées par consommateur et par période, et une lignée traçable.
 *
 * Ce module possède la fiche de provenance — pas les données métier produites
 * par la source. Il ne décide rien lui-même : la décision d'autoriser une
 * commande vient de CAP-CORE-004, dans la couche applicative ; ce module
 * conserve seulement ses propres bornes, pour rester sûr même si une
 * politique est mal écrite ailleurs.
 */
final class RegistreSources
{
    public const CAPACITE = 'CAP-CORE-006';

    public function __construct(
        private \PDO $index,
        private \PDO $registreIdentites,
        private \PDO $magasin,
        private \PDO $magasinProduits,
        private ?Ctr01 $identites = null,
        private ?RegistreProduits $produits = null,
    ) {
        $this->identites ??= new Ctr01($index, $registreIdentites);
        $this->produits ??= new RegistreProduits($index, $registreIdentites, $magasinProduits, $this->identites);
        SchemaSources::migrer($this->magasin);
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudreSource(string $reference, ?string $date = null): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM source WHERE reference = ?');
        $st->execute([$reference]);
        $s = $st->fetch();
        if ($s === false) {
            return null;
        }

        return $this->projeter($s, $date);
    }

    /**
     * @param array{etat?:string,type_source?:string,proprietaire_reference?:string,produit_producteur_reference?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public function listerSources(array $filtres = []): array
    {
        $sql = 'SELECT * FROM source';
        $conditions = [];
        $args = [];
        if (isset($filtres['type_source'])) {
            $conditions[] = 'type_source = ?';
            $args[] = $filtres['type_source'];
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY reference';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        $lignes = array_map(fn (array $s): array => $this->projeter($s, null), $st->fetchAll());

        if (isset($filtres['etat'])) {
            $lignes = array_values(array_filter(
                $lignes,
                static fn (array $l): bool => $l['etat'] === $filtres['etat'],
            ));
        }
        if (isset($filtres['proprietaire_reference'])) {
            $lignes = array_values(array_filter(
                $lignes,
                static fn (array $l): bool => $l['proprietaire_reference'] === $filtres['proprietaire_reference'],
            ));
        }
        if (isset($filtres['produit_producteur_reference'])) {
            $lignes = array_values(array_filter(
                $lignes,
                static fn (array $l): bool => $l['produit_producteur_reference'] === $filtres['produit_producteur_reference'],
            ));
        }

        return $lignes;
    }

    /** @return array<string,mixed>|null */
    public function resoudreEtat(string $reference, ?string $date = null): ?array
    {
        $cycle = $this->dernierCycle($reference, $date);

        return $cycle === null ? null : [
            'reference' => $reference,
            'etat' => $cycle['etat'],
            'date_effet' => $cycle['date_effet'],
            'motif' => $cycle['motif'],
            'acteur_reference' => $cycle['acteur_reference'],
        ];
    }

    /** @return array<string,mixed>|null */
    public function resoudreRevision(string $reference, ?string $date = null): ?array
    {
        $revision = $this->derniereRevision($reference, $date);

        return $revision === null ? null : $this->projeterRevision($revision);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreHistorique(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM source_cycle WHERE source_reference = ? ORDER BY date_effet, id'
        );
        $st->execute([$reference]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function resoudreRevisions(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM source_revision WHERE source_reference = ? ORDER BY numero_revision'
        );
        $st->execute([$reference]);

        return array_map(fn (array $r): array => $this->projeterRevision($r), $st->fetchAll());
    }

    /** @return array<string,mixed>|null */
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
        if ($v === false) {
            return null;
        }

        return $this->projeterVerification($v, $date);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreVerifications(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM source_verification WHERE source_reference = ? ORDER BY verifie_le, id'
        );
        $st->execute([$reference]);

        return array_map(fn (array $v): array => $this->projeterVerification($v, null), $st->fetchAll());
    }

    /**
     * @param bool $activesSeulement Ne rend que les finalités encore actives.
     * @return list<array<string,mixed>>
     */
    public function resoudreFinalites(string $reference, bool $activesSeulement = false): array
    {
        $sql = 'SELECT * FROM source_finalite WHERE source_reference = ?';
        $args = [$reference];
        if ($activesSeulement) {
            $sql .= ' AND actif = 1';
        }
        $sql .= ' ORDER BY date_debut, id';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        return array_map(fn (array $f): array => $this->projeterFinalite($f), $st->fetchAll());
    }

    /**
     * @return array{reference:string,amont:list<array<string,mixed>>,aval:list<array<string,mixed>>}|null
     */
    public function resoudreLignee(string $reference): ?array
    {
        $existe = $this->magasin->prepare('SELECT 1 FROM source WHERE reference = ?');
        $existe->execute([$reference]);
        if ($existe->fetchColumn() === false) {
            return null;
        }

        // Amont : les sources parentes de celle-ci (elle en dérive).
        $amont = $this->magasin->prepare(
            'SELECT source_parente_reference AS reference, type_relation, date_effet
             FROM source_lignee WHERE source_reference = ? ORDER BY id'
        );
        $amont->execute([$reference]);

        // Aval : les sources qui dérivent de celle-ci.
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

    /** @return list<array<string,mixed>> */
    public function listerSourcesActives(): array
    {
        return $this->listerSources(['etat' => 'ACTIVE']);
    }

    /** @return list<array<string,mixed>> */
    public function listerSourcesParProduit(string $produit): array
    {
        return $this->listerSources(['produit_producteur_reference' => $produit]);
    }

    /**
     * Une source n'est utilisable pour un traitement que si elle existe, est
     * active, et si la finalité demandée est déclarée pour ce consommateur
     * précis et n'est pas expirée. Le résultat est toujours explicable : il
     * énumère chaque motif de refus plutôt que de rendre un simple booléen.
     *
     * @return array{utilisable:bool,source:string,consommateur:?string,finalite:string,motifs:list<string>}
     */
    public function verifierUtilisable(
        string $reference,
        ?string $consommateur,
        string $finalite,
        ?string $date = null,
    ): array {
        $date ??= date('Y-m-d');
        $motifs = [];

        $source = $this->resoudreSource($reference, $date);
        if ($source === null) {
            return [
                'utilisable' => false, 'source' => $reference, 'consommateur' => $consommateur,
                'finalite' => $finalite, 'motifs' => ['SOURCE_INCONNUE'],
            ];
        }

        if ($source['etat'] !== 'ACTIVE') {
            $motifs[] = match ($source['etat']) {
                'SUSPENDUE' => 'SOURCE_SUSPENDUE',
                'RETIREE' => 'SOURCE_RETIREE',
                default => 'SOURCE_EN_PREPARATION',
            };
        }

        $correspondante = null;
        foreach ($this->resoudreFinalites($reference) as $f) {
            if ($f['finalite_reference'] !== $finalite) {
                continue;
            }
            if ($f['produit_consommateur_reference'] !== $consommateur) {
                continue;
            }
            $correspondante = $f;
            break;
        }

        if ($correspondante === null) {
            $motifs[] = 'FINALITE_NON_DECLAREE';
        } elseif (!$correspondante['actif']
            || ($correspondante['date_fin'] !== null && $correspondante['date_fin'] < $date)) {
            $motifs[] = 'FINALITE_EXPIREE';
        }

        return [
            'utilisable' => $motifs === [],
            'source' => $reference,
            'consommateur' => $consommateur,
            'finalite' => $finalite,
            'motifs' => $motifs,
        ];
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function inscrireSource(array $dossier): array
    {
        $controle = $this->controlerInscription($dossier);
        if (isset($controle['refus'])) {
            return $controle;
        }

        $reference = trim((string) $dossier['reference']);
        $nomCanonique = trim((string) $dossier['nom_canonique']);
        $nomAffichage = trim((string) $dossier['nom_affichage']);
        $type = (string) $dossier['type_source'];
        $proprietaire = trim((string) $dossier['proprietaire_reference']);
        $categorie = $this->nullable($dossier['categorie'] ?? null);
        $description = $this->nullable($dossier['description'] ?? null);
        $produitProducteur = $this->nullable($dossier['produit_producteur_reference'] ?? null);
        $reserve = $this->nullable($dossier['reserve'] ?? null);
        $authenticiteLegacy = $this->nullable($dossier['authenticite_legacy'] ?? null);
        $politique = (string) $dossier['politique'];
        $producteur = (string) $dossier['producteur'];
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $nomCanonique, $nomAffichage, $type, $proprietaire, $categorie,
            $description, $produitProducteur, $reserve, $authenticiteLegacy,
            $politique, $producteur, $source, $preuve, $date, $correlation,
        ): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO source
                 (reference,nom_canonique,type_source,authenticite_legacy,
                  politique_inscription,producteur,preuve_reference,cree_le,modifie_le)
                 VALUES(?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $nomCanonique, $type, $authenticiteLegacy,
                $politique, $producteur, $preuve, $maintenant, $maintenant,
            ]);
            $this->inscrireRevision(
                $reference, 1, $nomAffichage, $categorie, $description,
                $proprietaire, $produitProducteur, $reserve, $date, $producteur, $preuve, $correlation,
            );
            $this->inscrireCycle($reference, 'PREPARATION', $date, null, $producteur, $politique, $preuve, $correlation);

            return ['reference' => $reference, 'etat' => 'PREPARATION'];
        });
    }

    /**
     * Seules les métadonnées révisables changent : jamais la référence, le nom
     * canonique ou le type de source.
     *
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function modifierSource(string $reference, array $dossier): array
    {
        foreach (['reference', 'nom_canonique', 'type_source'] as $champ) {
            if (isset($dossier[$champ])) {
                return $this->refus('CHAMP_IMMUABLE', "le champ `{$champ}` ne se modifie jamais");
            }
        }
        $ligne = $this->ligneSource($reference);
        if ($ligne === null) {
            return $this->refus('SOURCE_INCONNUE', "source `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $revisable = ['nom_affichage', 'categorie', 'description', 'proprietaire_reference', 'produit_producteur_reference', 'reserve'];
        $fourni = array_intersect_key($dossier, array_flip($revisable));
        if ($fourni === []) {
            return $this->refus('DOSSIER_VIDE', 'aucune métadonnée modifiable fournie');
        }

        if (isset($dossier['proprietaire_reference'])) {
            $proprietaire = trim((string) $dossier['proprietaire_reference']);
            if ($this->identites->resoudreIdentite($proprietaire) === null) {
                return $this->refus('PROPRIETAIRE_INCONNU', "l’identité `{$proprietaire}` n’existe pas");
            }
        }
        if (array_key_exists('produit_producteur_reference', $dossier) && $dossier['produit_producteur_reference'] !== null) {
            $controle = $this->controlerProduit((string) $dossier['produit_producteur_reference'], 'PRODUIT_PRODUCTEUR');
            if (isset($controle['refus'])) {
                return $controle;
            }
        }

        $derniere = $this->derniereRevision($reference);
        if ($derniere === null) {
            return $this->refus('REVISION_ABSENTE', 'aucune révision existante à corriger');
        }

        $nomAffichage = isset($dossier['nom_affichage']) ? trim((string) $dossier['nom_affichage']) : $derniere['nom_affichage'];
        $categorie = array_key_exists('categorie', $dossier) ? $this->nullable($dossier['categorie']) : $derniere['categorie'];
        $description = array_key_exists('description', $dossier) ? $this->nullable($dossier['description']) : $derniere['description'];
        $proprietaire = isset($dossier['proprietaire_reference']) ? trim((string) $dossier['proprietaire_reference']) : $derniere['proprietaire_reference'];
        $produitProducteur = array_key_exists('produit_producteur_reference', $dossier) ? $this->nullable($dossier['produit_producteur_reference']) : $derniere['produit_producteur_reference'];
        $reserve = array_key_exists('reserve', $dossier) ? $this->nullable($dossier['reserve']) : $derniere['reserve'];
        $numero = ((int) $derniere['numero_revision']) + 1;
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $numero, $nomAffichage, $categorie, $description,
            $proprietaire, $produitProducteur, $reserve, $date, $producteur, $preuve, $correlation,
        ): array {
            $this->inscrireRevision(
                $reference, $numero, $nomAffichage, $categorie, $description,
                $proprietaire, $produitProducteur, $reserve, $date, $producteur, $preuve, $correlation,
            );
            $this->toucher($reference);

            return $this->resoudreSource($reference) ?? [];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function activerSource(string $reference, array $dossier): array
    {
        $ligne = $this->ligneSource($reference);
        if ($ligne === null) {
            return $this->refus('SOURCE_INCONNUE', "source `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $cycle = $this->dernierCycle($reference);
        $etat = $cycle['etat'] ?? 'PREPARATION';
        if ($etat === 'ACTIVE') {
            return ['reference' => $reference, 'etat' => 'ACTIVE', 'idempotent' => true];
        }
        if (!in_array($etat, PolitiqueSources::ETATS_ACTIVABLES, true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "une source `{$etat}` ne s’active pas directement");
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $politique = (string) $dossier['politique'];
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $date, $motif, $politique, $producteur, $preuve, $correlation): array {
            $this->inscrireCycle($reference, 'ACTIVE', $date, $motif, $producteur, $politique, $preuve, $correlation);
            $this->toucher($reference);

            return ['reference' => $reference, 'etat' => 'ACTIVE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function suspendreSource(string $reference, array $dossier): array
    {
        $ligne = $this->ligneSource($reference);
        if ($ligne === null) {
            return $this->refus('SOURCE_INCONNUE', "source `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $cycle = $this->dernierCycle($reference);
        $etat = $cycle['etat'] ?? 'PREPARATION';
        if ($etat === 'SUSPENDUE') {
            return ['reference' => $reference, 'etat' => 'SUSPENDUE', 'idempotent' => true];
        }
        if ($etat !== 'ACTIVE') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une source ACTIVE se suspend (état actuel `{$etat}`)");
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $politique = (string) $dossier['politique'];
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $date, $motif, $politique, $producteur, $preuve, $correlation): array {
            $this->inscrireCycle($reference, 'SUSPENDUE', $date, $motif, $producteur, $politique, $preuve, $correlation);
            $this->toucher($reference);

            return ['reference' => $reference, 'etat' => 'SUSPENDUE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function retirerSource(string $reference, array $dossier): array
    {
        $ligne = $this->ligneSource($reference);
        if ($ligne === null) {
            return $this->refus('SOURCE_INCONNUE', "source `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $cycle = $this->dernierCycle($reference);
        $etat = $cycle['etat'] ?? 'PREPARATION';
        if ($etat === 'RETIREE') {
            return ['reference' => $reference, 'etat' => 'RETIREE', 'idempotent' => true];
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $politique = (string) $dossier['politique'];
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $date, $motif, $politique, $producteur, $preuve, $correlation): array {
            $this->inscrireCycle($reference, 'RETIREE', $date, $motif, $producteur, $politique, $preuve, $correlation);
            $this->toucher($reference);

            return ['reference' => $reference, 'etat' => 'RETIREE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerFinalite(string $reference, array $dossier): array
    {
        $ligne = $this->ligneSource($reference);
        if ($ligne === null) {
            return $this->refus('SOURCE_INCONNUE', "source `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $etat = $this->dernierCycle($reference)['etat'] ?? 'PREPARATION';
        if ($etat !== 'ACTIVE') {
            return $this->refus('SOURCE_NON_ACTIVE', "une finalité ne se déclare que pour une source ACTIVE (état actuel `{$etat}`)");
        }

        $finalite = trim((string) ($dossier['finalite_reference'] ?? ''));
        if ($finalite === '') {
            return $this->refus('DOSSIER_INCOMPLET', 'finalite_reference absente');
        }
        $consommateur = $this->nullable($dossier['produit_consommateur_reference'] ?? null);
        if ($consommateur !== null) {
            $controle = $this->controlerProduit($consommateur, 'PRODUIT_CONSOMMATEUR');
            if (isset($controle['refus'])) {
                return $controle;
            }
        }
        $dateDebut = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($dateDebut)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $dateFin = $this->nullable($dossier['date_fin'] ?? null);
        if ($dateFin !== null) {
            if (!$this->dateValide($dateFin)) {
                return $this->refus('DATE_INVALIDE', 'date_fin doit suivre YYYY-MM-DD');
            }
            if ($dateFin < $dateDebut) {
                return $this->refus('DATE_INVALIDE', 'date_fin ne peut pas précéder date_debut');
            }
        }
        $restriction = $this->nullable($dossier['restriction'] ?? null);

        $existante = $this->finaliteActive($reference, $finalite, $consommateur);
        if ($existante !== null) {
            if ($existante['date_debut'] === $dateDebut && $existante['date_fin'] === $dateFin) {
                return $this->projeterFinalite($existante) + ['idempotent' => true];
            }

            return $this->refus(
                'CONFLIT_DATES',
                "une finalité active existe déjà pour `{$finalite}` / consommateur `" . ($consommateur ?? '(aucun)') . '`',
            );
        }

        $politique = (string) $dossier['politique'];
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $finalite, $consommateur, $dateDebut, $dateFin, $restriction,
            $politique, $producteur, $preuve, $correlation,
        ): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO source_finalite
                 (source_reference,finalite_reference,produit_consommateur_reference,date_debut,date_fin,
                  restriction,actif,acteur_reference,politique_reference,preuve_reference,correlation_id,cree_le)
                 VALUES(?,?,?,?,?,?,1,?,?,?,?,?)'
            )->execute([
                $reference, $finalite, $consommateur, $dateDebut, $dateFin,
                $restriction, $producteur, $politique, $preuve, $correlation, $maintenant,
            ]);
            $id = (int) $this->magasin->lastInsertId();
            $this->toucher($reference);

            return $this->projeterFinalite($this->ligneFinalite($id) ?? []) + ['idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerFinalite(string $reference, int $id, array $dossier): array
    {
        $ligne = $this->ligneFinalite($id);
        if ($ligne === null || (string) $ligne['source_reference'] !== $reference) {
            return $this->refus('FINALITE_INCONNUE', "finalité `{$id}` inconnue pour `{$reference}`");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ((int) $ligne['actif'] === 0) {
            return ['id' => $id, 'reference' => $reference, 'actif' => false, 'idempotent' => true];
        }

        $date = (string) ($dossier['date_fin'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_fin doit suivre YYYY-MM-DD');
        }

        return $this->transaction(function () use ($id, $date, $reference): array {
            $this->magasin->prepare(
                'UPDATE source_finalite SET actif = 0, date_fin = ? WHERE id = ?'
            )->execute([$date, $id]);
            $this->toucher($reference);

            return ['id' => $id, 'reference' => $reference, 'actif' => false, 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function enregistrerVerification(string $reference, array $dossier): array
    {
        $ligne = $this->ligneSource($reference);
        if ($ligne === null) {
            return $this->refus('SOURCE_INCONNUE', "source `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $niveau = (string) ($dossier['niveau'] ?? '');
        if (!in_array($niveau, PolitiqueSources::NIVEAUX_VERIFICATION, true)) {
            return $this->refus('NIVEAU_INCONNU', 'niveau hors liste close');
        }
        $resultat = (string) ($dossier['resultat'] ?? '');
        if (!in_array($resultat, PolitiqueSources::RESULTATS_VERIFICATION, true)) {
            return $this->refus('RESULTAT_INCONNU', 'resultat hors liste close');
        }
        $verifiePar = trim((string) ($dossier['verifie_par_reference'] ?? ''));
        if ($verifiePar === '') {
            return $this->refus('DOSSIER_INCOMPLET', 'verifie_par_reference absent');
        }
        if (in_array($niveau, PolitiqueSources::NIVEAUX_EXIGEANT_PREUVE, true)
            && $verifiePar === (string) $ligne['producteur']
            && $niveau === 'ATTESTEE') {
            return $this->refus('AUTO_ATTESTATION_INTERDITE', 'le vérificateur doit être distinct du producteur pour ATTESTEE');
        }

        $verifieLe = (string) ($dossier['verifie_le'] ?? date('Y-m-d'));
        if (!$this->dateValide($verifieLe)) {
            return $this->refus('DATE_INVALIDE', 'verifie_le doit suivre YYYY-MM-DD');
        }
        $expireLe = $this->nullable($dossier['expire_le'] ?? null);
        if ($expireLe !== null && !$this->dateValide($expireLe)) {
            return $this->refus('DATE_INVALIDE', 'expire_le doit suivre YYYY-MM-DD');
        }
        $motif = $this->nullable($dossier['motif'] ?? null);
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $niveau, $resultat, $verifiePar, $verifieLe, $expireLe, $motif, $preuve, $correlation,
        ): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO source_verification
                 (source_reference,niveau,resultat,verifie_par_reference,preuve_reference,
                  verifie_le,expire_le,motif,correlation_id,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $niveau, $resultat, $verifiePar, $preuve,
                $verifieLe, $expireLe, $motif, $correlation, $maintenant,
            ]);
            $this->toucher($reference);

            return $this->resoudreVerificationCourante($reference) ?? [];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerLignee(string $reference, array $dossier): array
    {
        $parente = trim((string) ($dossier['source_parente_reference'] ?? ''));
        if ($parente === '') {
            return $this->refus('DOSSIER_INCOMPLET', 'source_parente_reference absente');
        }
        if ($parente === $reference) {
            return $this->refus('SOURCE_PROPRE_PARENTE_INTERDITE', 'une source ne peut pas être sa propre parente');
        }
        if ($this->ligneSource($reference) === null) {
            return $this->refus('SOURCE_INCONNUE', "source `{$reference}` inconnue");
        }
        if ($this->ligneSource($parente) === null) {
            return $this->refus('SOURCE_PARENTE_INCONNUE', "source parente `{$parente}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $type = (string) ($dossier['type_relation'] ?? '');
        if (!in_array($type, PolitiqueSources::TYPES_LIGNEE, true)) {
            return $this->refus('TYPE_RELATION_INCONNU', 'type_relation hors liste close');
        }
        if ($this->cheminExiste($parente, $reference)) {
            return $this->refus('CYCLE_LIGNEE_INTERDIT', "déclarer `{$reference}` DERIVEE_DE `{$parente}` créerait un cycle");
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $parente, $type, $date, $producteur, $preuve, $correlation): array {
            $this->magasin->prepare(
                'INSERT INTO source_lignee
                 (source_reference,source_parente_reference,type_relation,date_effet,
                  acteur_reference,preuve_reference,correlation_id,cree_le)
                 VALUES(?,?,?,?,?,?,?,?)'
            )->execute([$reference, $parente, $type, $date, $producteur, $preuve, $correlation, gmdate('c')]);
            $this->toucher($reference);

            return $this->resoudreLignee($reference) ?? [];
        });
    }

    // ------------------------------------------------------------------
    // Internes

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    private function controlerInscription(array $dossier): array
    {
        foreach ([
            'reference', 'nom_canonique', 'nom_affichage', 'type_source',
            'proprietaire_reference', 'politique', 'producteur', 'source', 'preuve',
        ] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $reference = trim((string) $dossier['reference']);
        $type = (string) $dossier['type_source'];
        $proprietaire = trim((string) $dossier['proprietaire_reference']);

        if (!in_array($type, PolitiqueSources::TYPES_SOURCE, true)) {
            return $this->refus('TYPE_INCONNU', 'type_source hors liste close');
        }
        if ($this->ligneSource($reference) !== null) {
            return $this->refus('REFERENCE_DEJA_UTILISEE', "la référence `{$reference}` est déjà inscrite");
        }
        if ($this->identites->resoudreIdentite($proprietaire) === null) {
            return $this->refus('PROPRIETAIRE_INCONNU', "l’identité `{$proprietaire}` n’existe pas");
        }
        if (!empty($dossier['produit_producteur_reference'])) {
            $controle = $this->controlerProduit((string) $dossier['produit_producteur_reference'], 'PRODUIT_PRODUCTEUR');
            if (isset($controle['refus'])) {
                return $controle;
            }
        }
        if (isset($dossier['date']) && !$this->dateValide((string) $dossier['date'])) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }

        return ['valide' => true];
    }

    /** @return array<string,mixed> */
    private function controlerProduit(string $reference, string $prefixeRefus): array
    {
        $produit = $this->produits->resoudreProduit($reference);
        if ($produit === null) {
            return $this->refus("{$prefixeRefus}_INCONNU", "le produit `{$reference}` n’existe pas dans le registre CAP-CORE-011");
        }
        if ($produit['etat'] !== 'ACTIF') {
            return $this->refus("{$prefixeRefus}_NON_ACTIF", "le produit `{$reference}` n’est pas ACTIF");
        }

        return ['valide' => true];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    private function controlerGouvernance(array $dossier): array
    {
        foreach (['politique', 'producteur', 'source', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('COMMANDE_NON_GOUVERNEE', "champ `{$champ}` absent");
            }
        }

        return ['valide' => true];
    }

    /**
     * Vrai si, en suivant les relations de lignée existantes en partant de
     * `$depart`, on peut atteindre `$cible`. Sert à refuser toute nouvelle
     * relation qui fermerait un cycle avant même de l'écrire.
     */
    private function cheminExiste(string $depart, string $cible): bool
    {
        $visites = [];
        $file = [$depart];
        while ($file !== []) {
            $courant = array_shift($file);
            if ($courant === $cible) {
                return true;
            }
            if (isset($visites[$courant])) {
                continue;
            }
            $visites[$courant] = true;
            $st = $this->magasin->prepare('SELECT source_parente_reference FROM source_lignee WHERE source_reference = ?');
            $st->execute([$courant]);
            foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $parent) {
                $file[] = (string) $parent;
            }
        }

        return false;
    }

    /** @return array<string,mixed>|null */
    private function ligneSource(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM source WHERE reference = ?');
        $st->execute([$reference]);
        $s = $st->fetch();

        return $s === false ? null : $s;
    }

    /** @return array<string,mixed>|null */
    private function ligneFinalite(int $id): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM source_finalite WHERE id = ?');
        $st->execute([$id]);
        $f = $st->fetch();

        return $f === false ? null : $f;
    }

    /** @return array<string,mixed>|null */
    private function finaliteActive(string $reference, string $finalite, ?string $consommateur): ?array
    {
        $sql = 'SELECT * FROM source_finalite
                WHERE source_reference = ? AND finalite_reference = ? AND actif = 1';
        $args = [$reference, $finalite];
        if ($consommateur === null) {
            $sql .= ' AND produit_consommateur_reference IS NULL';
        } else {
            $sql .= ' AND produit_consommateur_reference = ?';
            $args[] = $consommateur;
        }
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $f = $st->fetch();

        return $f === false ? null : $f;
    }

    /** @return array<string,mixed>|null */
    private function dernierCycle(string $reference, ?string $date = null): ?array
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
    private function derniereRevision(string $reference, ?string $date = null): ?array
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

    private function inscrireRevision(
        string $reference,
        int $numero,
        string $nomAffichage,
        ?string $categorie,
        ?string $description,
        string $proprietaire,
        ?string $produitProducteur,
        ?string $reserve,
        string $date,
        string $acteur,
        string $preuve,
        ?string $correlation,
    ): void {
        $this->magasin->prepare(
            'INSERT INTO source_revision
             (source_reference,numero_revision,nom_affichage,categorie,description,
              proprietaire_reference,produit_producteur_reference,reserve,
              date_effet,acteur_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference, $numero, $nomAffichage, $categorie, $description,
            $proprietaire, $produitProducteur, $reserve,
            $date, $acteur, $preuve, $correlation, gmdate('c'),
        ]);
    }

    private function inscrireCycle(
        string $reference,
        string $etat,
        string $date,
        ?string $motif,
        string $acteur,
        string $politique,
        string $preuve,
        ?string $correlation,
    ): void {
        if (!in_array($etat, PolitiqueSources::ETATS_CYCLE, true)) {
            throw new \LogicException("état `{$etat}` hors liste close");
        }
        $this->magasin->prepare(
            'INSERT INTO source_cycle
             (source_reference,etat,date_effet,motif,acteur_reference,politique_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?)'
        )->execute([$reference, $etat, $date, $motif, $acteur, $politique, $preuve, $correlation, gmdate('c')]);
    }

    private function toucher(string $reference): void
    {
        $this->magasin->prepare('UPDATE source SET modifie_le = ? WHERE reference = ?')
            ->execute([gmdate('c'), $reference]);
    }

    /** @param array<string,mixed> $s @return array<string,mixed> */
    private function projeter(array $s, ?string $date): array
    {
        $reference = (string) $s['reference'];
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

    /** @param array<string,mixed> $r @return array<string,mixed> */
    private function projeterRevision(array $r): array
    {
        return [
            'source_reference' => $r['source_reference'],
            'numero_revision' => (int) $r['numero_revision'],
            'nom_affichage' => $r['nom_affichage'],
            'categorie' => $r['categorie'],
            'description' => $r['description'],
            'proprietaire_reference' => $r['proprietaire_reference'],
            'produit_producteur_reference' => $r['produit_producteur_reference'],
            'reserve' => $r['reserve'],
            'date_effet' => $r['date_effet'],
            'acteur_reference' => $r['acteur_reference'],
        ];
    }

    /** @param array<string,mixed> $v @return array<string,mixed> */
    private function projeterVerification(array $v, ?string $date): array
    {
        $reference = date('Y-m-d');
        $aujourdhui = $date ?? $reference;
        $expiree = $v['expire_le'] !== null && $v['expire_le'] < $aujourdhui;

        return [
            'source_reference' => $v['source_reference'],
            'niveau' => $v['niveau'],
            'resultat' => $expiree ? 'EXPIREE' : $v['resultat'],
            'verifie_par_reference' => $v['verifie_par_reference'],
            'verifie_le' => $v['verifie_le'],
            'expire_le' => $v['expire_le'],
            'expiree' => $expiree,
            'motif' => $v['motif'],
        ];
    }

    /** @param array<string,mixed> $f @return array<string,mixed> */
    private function projeterFinalite(array $f): array
    {
        return [
            'id' => (int) $f['id'],
            'source_reference' => $f['source_reference'],
            'finalite_reference' => $f['finalite_reference'],
            'produit_consommateur_reference' => $f['produit_consommateur_reference'],
            'date_debut' => $f['date_debut'],
            'date_fin' => $f['date_fin'],
            'restriction' => $f['restriction'],
            'actif' => (bool) $f['actif'],
        ];
    }

    private function dateValide(string $date): bool
    {
        $valeur = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $valeur !== false && $valeur->format('Y-m-d') === $date;
    }

    private function nullable(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }

    /** @return array<string,mixed> */
    private function refus(string $motif, string $detail): array
    {
        return ['refus' => $motif, 'detail' => $detail];
    }

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    private function transaction(callable $operation): mixed
    {
        $propre = !$this->magasin->inTransaction();
        if ($propre) {
            $this->magasin->beginTransaction();
        }
        try {
            $resultat = $operation();
            if ($propre) {
                $this->magasin->commit();
            }

            return $resultat;
        } catch (\Throwable $e) {
            if ($propre && $this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }
    }
}
