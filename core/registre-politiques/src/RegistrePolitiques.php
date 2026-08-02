<?php

declare(strict_types=1);

namespace Gamad\RegistrePolitiques;

use Gamad\RegistreIdentites\Ctr01;

/**
 * Registre opérationnel des politiques et règles techniques (CAP-CORE-007).
 *
 * Une politique n'est plus une ligne de l'index documentaire modifiée en
 * éditant une baseline : elle possède une fiche persistante, des versions
 * numérotées et immuables une fois soumises, des règles dont chaque version
 * conserve son propre jeu, un cycle de vie en ajout seul, et une simulation
 * obligatoire avant toute activation.
 *
 * Ce module possède les politiques et leurs règles — pas la décision
 * elle-même. La décision reste rendue par `Ctr03` (CAP-CORE-004), qui lit ce
 * magasin. Ce module ne décide rien lui-même : chaque commande gouvernée
 * exige `politique`, `producteur`, `source` et `preuve`, comme les autres
 * registres persistants du Core ; il conserve en outre ses propres bornes
 * (une seule version active, immutabilité post-BROUILLON, simulation requise
 * avant activation) même si une politique est mal écrite ailleurs.
 *
 * `source_reference` reste un champ descriptif libre — comme `source` l'est
 * déjà pour CAP-CORE-011 et CAP-CORE-006 — et non une clé étrangère validée
 * contre CAP-CORE-006 : les huit politiques déjà exploitées portent des
 * provenances qui ne sont pas des références de source canoniques (articles
 * d'un corpus, chemins de module). Un resserrement vers une validation
 * stricte est un choix produit réversible, non pris ici.
 */
final class RegistrePolitiques
{
    public const CAPACITE = 'CAP-CORE-007';

    public function __construct(
        private \PDO $index,
        private \PDO $registreIdentites,
        private \PDO $magasin,
        private ?Ctr01 $identites = null,
    ) {
        $this->identites ??= new Ctr01($index, $registreIdentites);
        SchemaPolitiques::migrer($this->magasin);
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudrePolitique(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM politique WHERE reference = ?');
        $st->execute([$reference]);
        $p = $st->fetch();
        if ($p === false) {
            return null;
        }

        $active = $this->ligneVersionActive($reference);
        $versionActive = $active === null
            ? null
            : $this->ligneVersionParId((int) $active['politique_version_id'])['version'] ?? null;

        return [
            'reference' => $p['reference'],
            'libelle' => $p['libelle'],
            'domaine' => $p['domaine'],
            'proprietaire_reference' => $p['proprietaire_reference'],
            'source_reference' => $p['source_reference'],
            'description' => $p['description'],
            'version_active' => $versionActive,
            'cree_le' => $p['cree_le'],
            'modifie_le' => $p['modifie_le'],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function listerPolitiques(array $filtres = []): array
    {
        $sql = 'SELECT * FROM politique';
        $conditions = [];
        $args = [];
        if (isset($filtres['domaine'])) {
            $conditions[] = 'domaine = ?';
            $args[] = $filtres['domaine'];
        }
        if (isset($filtres['proprietaire_reference'])) {
            $conditions[] = 'proprietaire_reference = ?';
            $args[] = $filtres['proprietaire_reference'];
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY reference';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        return array_map(
            fn (array $p): array => $this->resoudrePolitique((string) $p['reference']) ?? [],
            $st->fetchAll(),
        );
    }

    /** @return list<array<string,mixed>> */
    public function listerVersions(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM politique_version WHERE politique_reference = ? ORDER BY id'
        );
        $st->execute([$reference]);

        return array_map(
            fn (array $v): array => $this->projeterVersion($v),
            $st->fetchAll(),
        );
    }

    /** @return array<string,mixed>|null */
    public function resoudreVersion(string $reference, string $version): ?array
    {
        $v = $this->ligneVersion($reference, $version);

        return $v === null ? null : $this->projeterVersion($v, avecRegles: true);
    }

    /** @return array<string,mixed>|null */
    public function resoudreVersionActive(string $reference, ?string $date = null): ?array
    {
        $active = $this->ligneVersionActive($reference, $date);
        if ($active === null) {
            return null;
        }
        $v = $this->ligneVersionParId((int) $active['politique_version_id']);

        return $v === null ? null : $this->projeterVersion($v, avecRegles: true);
    }

    /**
     * Les règles actives, toutes politiques confondues, correspondant à
     * l'action exacte demandée. Utilisée par `Ctr03` — introspection publique
     * de la même donnée que celle qui fonde la décision.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreReglesActives(string $actionReference, ?string $date = null): array
    {
        $date ??= date('Y-m-d');
        $sql = 'SELECT rp.*, pv.politique_reference, p.source_reference
                FROM regle_politique rp
                JOIN politique_version pv ON pv.id = rp.politique_version_id
                JOIN politique p ON p.reference = pv.politique_reference
                WHERE rp.action_reference = ?
                  AND pv.id IN (
                      SELECT pvc.politique_version_id FROM politique_version_cycle pvc
                      WHERE pvc.date_effet <= ? AND pvc.etat = ?
                      AND pvc.id = (
                          SELECT id FROM politique_version_cycle
                          WHERE politique_version_id = pvc.politique_version_id AND date_effet <= ?
                          ORDER BY date_effet DESC, id DESC LIMIT 1
                      )
                  )
                ORDER BY rp.id';
        $st = $this->magasin->prepare($sql);
        $st->execute([$actionReference, $date, 'ACTIVE', $date]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function resoudreHistorique(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT pvc.* FROM politique_version_cycle pvc
             JOIN politique_version pv ON pv.id = pvc.politique_version_id
             WHERE pv.politique_reference = ?
             ORDER BY pvc.date_effet, pvc.id'
        );
        $st->execute([$reference]);

        return array_values($st->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function resoudreSimulation(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM politique_simulation WHERE reference = ?');
        $st->execute([$reference]);
        $s = $st->fetch();

        return $s === false ? null : $s;
    }

    /**
     * Vérifie l'invariant central du registre : au plus une version active
     * par politique à l'instant présent.
     *
     * @return array{coherent:bool,divergences:list<string>}
     */
    public function diagnostiquerRegistre(): array
    {
        $divergences = [];
        $politiques = $this->magasin->query('SELECT reference FROM politique')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($politiques as $reference) {
            $st = $this->magasin->prepare(
                'SELECT COUNT(*) FROM (
                    SELECT pv.id FROM politique_version pv
                    JOIN politique_version_cycle pvc ON pvc.politique_version_id = pv.id
                    WHERE pv.politique_reference = ? AND pvc.etat = ?
                    AND pvc.id = (
                        SELECT id FROM politique_version_cycle
                        WHERE politique_version_id = pv.id ORDER BY date_effet DESC, id DESC LIMIT 1
                    )
                 )'
            );
            $st->execute([$reference, 'ACTIVE']);
            $nombre = (int) $st->fetchColumn();
            if ($nombre > 1) {
                $divergences[] = "{$reference} : {$nombre} versions actives simultanées";
            }
        }

        return ['coherent' => $divergences === [], 'divergences' => $divergences];
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function inscrirePolitique(array $dossier): array
    {
        foreach (['reference', 'libelle', 'proprietaire_reference', 'source_reference', 'politique', 'producteur', 'source', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $reference = trim((string) $dossier['reference']);
        $proprietaire = trim((string) $dossier['proprietaire_reference']);

        if ($this->lignePolitique($reference) !== null) {
            return $this->refus('REFERENCE_DEJA_UTILISEE', "la référence `{$reference}` est déjà inscrite");
        }
        if ($this->identites->resoudreIdentite($proprietaire) === null) {
            return $this->refus('PROPRIETAIRE_INCONNU', "l’identité `{$proprietaire}` n’existe pas");
        }

        $libelle = trim((string) $dossier['libelle']);
        $domaine = $this->nullable($dossier['domaine'] ?? null);
        $sourceRef = trim((string) $dossier['source_reference']);
        $description = $this->nullable($dossier['description'] ?? null);
        $politique = (string) $dossier['politique'];
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];

        return $this->transaction(function () use (
            $reference, $libelle, $domaine, $proprietaire, $sourceRef, $description, $politique, $producteur, $preuve,
        ): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO politique
                 (reference,libelle,domaine,proprietaire_reference,source_reference,description,
                  politique_inscription,producteur,preuve_reference,cree_le,modifie_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $libelle, $domaine, $proprietaire, $sourceRef, $description,
                $politique, $producteur, $preuve, $maintenant, $maintenant,
            ]);

            return ['reference' => $reference];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function creerVersion(string $reference, array $dossier): array
    {
        if ($this->lignePolitique($reference) === null) {
            return $this->refus('POLITIQUE_INCONNUE', "politique `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $version = trim((string) ($dossier['version'] ?? ''));
        if (!preg_match(PolitiqueAdministration::FORMAT_VERSION, $version)) {
            return $this->refus('VERSION_INVALIDE', 'la version doit suivre le format X.Y.Z');
        }
        if ($this->ligneVersion($reference, $version) !== null) {
            return $this->refus('VERSION_DEJA_UTILISEE', "la version `{$version}` existe déjà pour `{$reference}`");
        }

        $description = $this->nullable($dossier['description'] ?? null);
        $dateEffetPrevue = $this->nullable($dossier['date_effet_prevue'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $version, $description, $dateEffetPrevue, $producteur, $preuve, $date, $politiqueAdmin, $correlation,
        ): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO politique_version
                 (politique_reference,version,schema_version,description,date_effet_prevue,
                  empreinte_contenu,cree_par_reference,preuve_reference,cree_le)
                 VALUES(?,?,1,?,?,NULL,?,?,?)'
            )->execute([$reference, $version, $description, $dateEffetPrevue, $producteur, $preuve, $maintenant]);
            $id = (int) $this->magasin->lastInsertId();
            $this->inscrireCycle($id, 'BROUILLON', $date, null, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['politique_reference' => $reference, 'version' => $version, 'id' => $id, 'etat' => 'BROUILLON'];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function ajouterRegle(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if ($etat !== 'BROUILLON') {
            return $this->refus('VERSION_IMMUABLE', "seule une version BROUILLON accepte de nouvelles règles (état actuel `{$etat}`)");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $effet = (string) ($dossier['effet'] ?? '');
        if (!in_array($effet, PolitiqueAdministration::EFFETS, true)) {
            return $this->refus('EFFET_INCONNU', 'effet hors liste close');
        }
        $action = trim((string) ($dossier['action_reference'] ?? ''));
        if ($action === '') {
            return $this->refus('ACTION_VIDE', 'action_reference absente');
        }
        $motif = trim((string) ($dossier['motif'] ?? ''));
        if ($motif === '') {
            return $this->refus('MOTIF_ABSENT', 'motif absent');
        }
        $sujetReference = $this->nullable($dossier['sujet_reference'] ?? null);
        $sujetType = $this->nullable($dossier['sujet_type'] ?? null);
        $ressourceReference = $this->nullable($dossier['ressource_reference'] ?? null);
        $ressourceType = $this->nullable($dossier['ressource_type'] ?? null);

        $ordre = $dossier['ordre'] ?? null;
        if ($ordre === null) {
            $st = $this->magasin->prepare('SELECT COALESCE(MAX(ordre),0) FROM regle_politique WHERE politique_version_id = ?');
            $st->execute([$v['id']]);
            $ordre = ((int) $st->fetchColumn()) + 1;
        } else {
            $ordre = (int) $ordre;
            $st = $this->magasin->prepare('SELECT 1 FROM regle_politique WHERE politique_version_id = ? AND ordre = ?');
            $st->execute([$v['id'], $ordre]);
            if ($st->fetchColumn() !== false) {
                return $this->refus('ORDRE_DEJA_UTILISE', "l’ordre `{$ordre}` est déjà utilisé dans cette version");
            }
        }

        return $this->transaction(function () use (
            $v, $ordre, $effet, $action, $sujetReference, $sujetType, $ressourceReference, $ressourceType, $motif,
        ): array {
            $this->magasin->prepare(
                'INSERT INTO regle_politique
                 (politique_version_id,ordre,effet,action_reference,sujet_reference,sujet_type,
                  ressource_reference,ressource_type,motif,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $v['id'], $ordre, $effet, $action, $sujetReference, $sujetType,
                $ressourceReference, $ressourceType, $motif, gmdate('c'),
            ]);

            return [
                'politique_version_id' => (int) $v['id'], 'ordre' => $ordre, 'effet' => $effet,
                'action_reference' => $action,
            ];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function modifierRegle(string $reference, string $version, int $regleId, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        if ($this->etatCourant((int) $v['id']) !== 'BROUILLON') {
            return $this->refus('VERSION_IMMUABLE', 'une règle ne se modifie que dans une version BROUILLON');
        }
        $st = $this->magasin->prepare('SELECT * FROM regle_politique WHERE id = ? AND politique_version_id = ?');
        $st->execute([$regleId, $v['id']]);
        $regle = $st->fetch();
        if ($regle === false) {
            return $this->refus('REGLE_INCONNUE', "règle `{$regleId}` inconnue pour cette version");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $champs = [];
        $valeurs = [];
        foreach (['effet', 'action_reference', 'sujet_reference', 'sujet_type', 'ressource_reference', 'ressource_type', 'motif'] as $champ) {
            if (array_key_exists($champ, $dossier)) {
                if ($champ === 'effet' && !in_array($dossier[$champ], PolitiqueAdministration::EFFETS, true)) {
                    return $this->refus('EFFET_INCONNU', 'effet hors liste close');
                }
                $champs[] = "{$champ} = ?";
                $valeurs[] = $dossier[$champ] === null ? null : (string) $dossier[$champ];
            }
        }
        if ($champs === []) {
            return $this->refus('DOSSIER_VIDE', 'aucun champ à modifier fourni');
        }
        $valeurs[] = $regleId;

        return $this->transaction(function () use ($champs, $valeurs, $regleId): array {
            $this->magasin->prepare('UPDATE regle_politique SET ' . implode(', ', $champs) . ' WHERE id = ?')
                ->execute($valeurs);

            return ['id' => $regleId];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function soumettreVersion(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if ($etat === 'EN_VALIDATION') {
            return ['politique_reference' => $reference, 'version' => $version, 'etat' => 'EN_VALIDATION', 'idempotent' => true];
        }
        if ($etat !== 'BROUILLON') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version BROUILLON se soumet (état actuel `{$etat}`)");
        }
        $regles = $this->reglesVersion((int) $v['id']);
        if ($regles === []) {
            return $this->refus('AUCUNE_REGLE', 'une version sans règle ne peut pas être soumise');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $empreinte = $this->calculerEmpreinte($regles);
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($v, $empreinte, $date, $producteur, $preuve, $politiqueAdmin, $correlation, $reference, $version): array {
            $this->magasin->prepare('UPDATE politique_version SET empreinte_contenu = ? WHERE id = ?')
                ->execute([$empreinte, $v['id']]);
            $this->inscrireCycle((int) $v['id'], 'EN_VALIDATION', $date, null, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['politique_reference' => $reference, 'version' => $version, 'etat' => 'EN_VALIDATION', 'empreinte_contenu' => $empreinte, 'idempotent' => false];
        });
    }

    /**
     * Simule les effets d'une version EN_VALIDATION sur un jeu de cas
     * explicite, sans aucun effet de bord. N'utilise jamais la version
     * active : seules les règles de la version exacte simulée sont évaluées.
     *
     * @param array<string,mixed> $dossier {jeu_reference, cas: list<array{sujet:string,action:string,ressource?:?string,attendu:string}>}
     * @return array<string,mixed>
     */
    public function simulerVersion(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        if ($this->etatCourant((int) $v['id']) !== 'EN_VALIDATION') {
            return $this->refus('ETAT_INCOMPATIBLE', 'seule une version EN_VALIDATION se simule');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $jeuReference = trim((string) ($dossier['jeu_reference'] ?? ''));
        if ($jeuReference === '') {
            return $this->refus('JEU_ABSENT', 'jeu_reference absent');
        }
        $cas = $dossier['cas'] ?? [];
        if (!is_array($cas)) {
            $cas = [];
        }

        $regles = $this->reglesVersion((int) $v['id']);
        $divergences = [];
        $evalues = 0;
        foreach ($cas as $c) {
            $sujet = (string) ($c['sujet'] ?? '');
            $action = (string) ($c['action'] ?? '');
            $ressource = $c['ressource'] ?? null;
            $attendu = (string) ($c['attendu'] ?? '');
            if ($sujet === '' || $action === '' || $attendu === '') {
                continue;
            }
            $evalues++;
            $decision = $this->evaluer($regles, $sujet, $action, $ressource === null ? null : (string) $ressource);
            if ($decision['decision'] !== $attendu) {
                $divergences[] = [
                    'sujet' => $sujet, 'action' => $action, 'ressource' => $ressource,
                    'attendu' => $attendu, 'obtenu' => $decision['decision'],
                ];
            }
        }

        $resultat = match (true) {
            $evalues === 0 => 'INCOMPLETE',
            $divergences !== [] => 'ECHEC',
            default => 'REUSSIE',
        };
        $reference2 = 'SIM-' . strtoupper(bin2hex(random_bytes(8)));
        $producteur = (string) $dossier['producteur'];
        $resume = [
            'cas_evalues' => $evalues,
            'cas_fournis' => count($cas),
            'divergences' => $divergences,
        ];

        return $this->transaction(function () use ($reference2, $v, $jeuReference, $resultat, $resume, $producteur): array {
            $this->magasin->prepare(
                'INSERT INTO politique_simulation
                 (reference,politique_version_id,jeu_reference,resultat,resume_json,acteur_reference,cree_le,expire_le)
                 VALUES(?,?,?,?,?,?,?,NULL)'
            )->execute([
                $reference2, $v['id'], $jeuReference, $resultat, json_encode($resume, JSON_UNESCAPED_UNICODE), $producteur, gmdate('c'),
            ]);

            return ['reference' => $reference2, 'resultat' => $resultat, 'resume' => $resume];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function activerVersion(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if ($etat === 'ACTIVE') {
            return ['politique_reference' => $reference, 'version' => $version, 'etat' => 'ACTIVE', 'idempotent' => true];
        }
        if ($etat !== 'EN_VALIDATION') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version EN_VALIDATION s’active (état actuel `{$etat}`)");
        }
        $st = $this->magasin->prepare(
            "SELECT 1 FROM politique_simulation WHERE politique_version_id = ? AND resultat = 'REUSSIE' LIMIT 1"
        );
        $st->execute([$v['id']]);
        if ($st->fetchColumn() === false) {
            return $this->refus('SIMULATION_MANQUANTE', 'aucune simulation réussie pour cette version exacte');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        $ancienne = $this->ligneVersionActive($reference);

        return $this->transaction(function () use ($v, $ancienne, $date, $motif, $producteur, $preuve, $politiqueAdmin, $correlation, $reference, $version): array {
            if ($ancienne !== null) {
                $this->inscrireCycle(
                    (int) $ancienne['politique_version_id'], 'REMPLACEE', $date,
                    "remplacée par la version {$version}", $producteur, $politiqueAdmin, $preuve, $correlation,
                );
            }
            $this->inscrireCycle((int) $v['id'], 'ACTIVE', $date, $motif, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['politique_reference' => $reference, 'version' => $version, 'etat' => 'ACTIVE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function suspendreVersion(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if ($etat === 'SUSPENDUE') {
            return ['politique_reference' => $reference, 'version' => $version, 'etat' => 'SUSPENDUE', 'idempotent' => true];
        }
        if ($etat !== 'ACTIVE') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version ACTIVE se suspend (état actuel `{$etat}`)");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($v, $date, $motif, $producteur, $preuve, $politiqueAdmin, $correlation, $reference, $version): array {
            $this->inscrireCycle((int) $v['id'], 'SUSPENDUE', $date, $motif, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['politique_reference' => $reference, 'version' => $version, 'etat' => 'SUSPENDUE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function retirerPolitique(string $reference, array $dossier): array
    {
        if ($this->lignePolitique($reference) === null) {
            return $this->refus('POLITIQUE_INCONNUE', "politique `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $active = $this->ligneVersionActive($reference);
        if ($active === null) {
            return $this->refus('AUCUNE_VERSION_ACTIVE', "`{$reference}` n’a aucune version active à retirer");
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($active, $date, $motif, $producteur, $preuve, $politiqueAdmin, $correlation, $reference): array {
            $this->inscrireCycle(
                (int) $active['politique_version_id'], 'RETIREE', $date, $motif, $producteur, $politiqueAdmin, $preuve, $correlation,
            );
            $this->magasin->prepare('UPDATE politique SET modifie_le = ? WHERE reference = ?')
                ->execute([gmdate('c'), $reference]);

            return ['reference' => $reference, 'etat' => 'RETIREE'];
        });
    }

    // ------------------------------------------------------------------
    // Évaluation (partagée avec CTR-03 pour la simulation)

    /**
     * Évalue un jeu de règles déjà résolu (celles d'une version précise, ou
     * celles de toutes les versions actives) : premier `REFUSE` applicable
     * gagnant immédiatement, sinon premier `PERMET` applicable, sinon refus
     * par défaut. Utilisée à la fois par `simulerVersion()` (une version
     * candidate) et par `Ctr03` (les versions actives).
     *
     * @param list<array<string,mixed>> $regles
     * @return array<string,mixed>
     */
    public function evaluer(array $regles, string $sujet, string $action, ?string $ressource = null): array
    {
        $actionNormalisee = self::normaliser($action);
        $permission = null;
        foreach ($regles as $r) {
            if (self::normaliser((string) $r['action_reference']) !== $actionNormalisee) {
                continue;
            }
            if ($r['sujet_reference'] !== null && (string) $r['sujet_reference'] !== $sujet) {
                continue;
            }
            if ($r['ressource_reference'] !== null && $ressource !== null && (string) $r['ressource_reference'] !== $ressource) {
                continue;
            }
            if ($r['effet'] === 'REFUSE') {
                return [
                    'decision' => 'REFUSE', 'sujet' => $sujet, 'action' => $action, 'ressource' => $ressource,
                    'motif' => $r['motif'], 'politique' => $r['politique_reference'] ?? null,
                    'source' => $r['source_reference'] ?? null,
                ];
            }
            $permission ??= $r;
        }
        if ($permission !== null) {
            return [
                'decision' => 'PERMIS', 'sujet' => $sujet, 'action' => $action, 'ressource' => $ressource,
                'motif' => $permission['motif'], 'politique' => $permission['politique_reference'] ?? null,
                'source' => $permission['source_reference'] ?? null,
            ];
        }

        return [
            'decision' => 'REFUSE', 'sujet' => $sujet, 'action' => $action, 'ressource' => $ressource,
            'motif' => 'aucune politique active ne permet cette action ; l’absence de règle n’est jamais une permission',
            'politique' => null, 'source' => null,
        ];
    }

    public static function normaliser(string $action): string
    {
        $a = preg_replace('/^(de |d\'|d’)/u', '', mb_strtolower(trim($action), 'UTF-8')) ?? $action;
        $a = preg_replace('/[^\p{L}\p{N}]+/u', '-', $a) ?? $a;

        return trim(mb_substr($a, 0, 64, 'UTF-8'), '-');
    }

    // ------------------------------------------------------------------
    // Internes

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

    /** @return array<string,mixed>|null */
    private function lignePolitique(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM politique WHERE reference = ?');
        $st->execute([$reference]);
        $p = $st->fetch();

        return $p === false ? null : $p;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersion(string $reference, string $version): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM politique_version WHERE politique_reference = ? AND version = ?');
        $st->execute([$reference, $version]);
        $v = $st->fetch();

        return $v === false ? null : $v;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersionParId(int $id): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM politique_version WHERE id = ?');
        $st->execute([$id]);
        $v = $st->fetch();

        return $v === false ? null : $v;
    }

    /** La ligne de cycle ACTIVE courante pour une politique, s'il en existe une. @return array<string,mixed>|null */
    private function ligneVersionActive(string $reference, ?string $date = null): ?array
    {
        $date ??= date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT pvc.* FROM politique_version_cycle pvc
             JOIN politique_version pv ON pv.id = pvc.politique_version_id
             WHERE pv.politique_reference = ? AND pvc.date_effet <= ?
             AND pvc.id = (
                 SELECT id FROM politique_version_cycle
                 WHERE politique_version_id = pvc.politique_version_id AND date_effet <= ?
                 ORDER BY date_effet DESC, id DESC LIMIT 1
             )
             AND pvc.etat = ?'
        );
        $st->execute([$reference, $date, $date, 'ACTIVE']);
        $c = $st->fetch();

        return $c === false ? null : $c;
    }

    private function etatCourant(int $versionId): string
    {
        $st = $this->magasin->prepare(
            'SELECT etat FROM politique_version_cycle WHERE politique_version_id = ? ORDER BY date_effet DESC, id DESC LIMIT 1'
        );
        $st->execute([$versionId]);
        $etat = $st->fetchColumn();

        return $etat === false ? 'BROUILLON' : (string) $etat;
    }

    /** @return list<array<string,mixed>> */
    private function reglesVersion(int $versionId): array
    {
        $st = $this->magasin->prepare('SELECT * FROM regle_politique WHERE politique_version_id = ? ORDER BY ordre');
        $st->execute([$versionId]);

        return array_values($st->fetchAll());
    }

    private function inscrireCycle(
        int $versionId,
        string $etat,
        string $date,
        ?string $motif,
        string $acteur,
        string $politique,
        string $preuve,
        ?string $correlation,
    ): void {
        if (!in_array($etat, PolitiqueAdministration::ETATS_CYCLE, true)) {
            throw new \LogicException("état `{$etat}` hors liste close");
        }
        $this->magasin->prepare(
            'INSERT INTO politique_version_cycle
             (politique_version_id,etat,date_effet,motif,acteur_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?)'
        )->execute([$versionId, $etat, $date, $motif, $acteur, $preuve, $correlation, gmdate('c')]);
    }

    /** @param list<array<string,mixed>> $regles */
    private function calculerEmpreinte(array $regles): string
    {
        $canonique = array_map(static fn (array $r): array => [
            'ordre' => (int) $r['ordre'],
            'effet' => $r['effet'],
            'action_reference' => $r['action_reference'],
            'sujet_reference' => $r['sujet_reference'],
            'ressource_reference' => $r['ressource_reference'],
            'motif' => $r['motif'],
        ], $regles);
        usort($canonique, static fn (array $a, array $b): int => $a['ordre'] <=> $b['ordre']);

        return hash('sha256', json_encode($canonique, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string,mixed> $v @return array<string,mixed> */
    private function projeterVersion(array $v, bool $avecRegles = false): array
    {
        $projection = [
            'id' => (int) $v['id'],
            'politique_reference' => $v['politique_reference'],
            'version' => $v['version'],
            'description' => $v['description'],
            'date_effet_prevue' => $v['date_effet_prevue'],
            'empreinte_contenu' => $v['empreinte_contenu'],
            'etat' => $this->etatCourant((int) $v['id']),
            'cree_le' => $v['cree_le'],
        ];
        if ($avecRegles) {
            $projection['regles'] = $this->reglesVersion((int) $v['id']);
        }

        return $projection;
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
