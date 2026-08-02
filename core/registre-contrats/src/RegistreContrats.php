<?php

declare(strict_types=1);

namespace Gamad\RegistreContrats;

use Gamad\RegistreIdentites\Ctr01;

/**
 * Registre opérationnel des contrats d'échange (CAP-CORE-009).
 *
 * Un contrat est une description versionnée, stable et opposable d'un
 * échange entre un producteur et un ou plusieurs consommateurs — jamais une
 * donnée métier réelle, jamais une permission (celle-ci reste rendue par
 * `Ctr03`, CAP-CORE-004), jamais le transport d'un événement (celui-ci reste
 * `CAP-CORE-014`).
 *
 * Une version quitte `BROUILLON` à la soumission et devient alors immuable :
 * parties, opérations, schémas, erreurs et obligations ne s'ajoutent plus.
 * L'activation exige une analyse de compatibilité et une conformité
 * enregistrées pour cette version exacte ; une rupture exige en outre un
 * plan de migration et une date limite explicites. Comme les autres
 * registres persistants du Core, ce module ne décide rien lui-même : chaque
 * commande gouvernée exige `politique`, `producteur`, `source` et `preuve`.
 *
 * `source_reference` reste un champ descriptif libre, comme pour
 * CAP-CORE-006, CAP-CORE-007 et CAP-CORE-011 : un resserrement vers une
 * validation stricte contre le registre des sources est un choix produit
 * réversible, non pris ici.
 */
final class RegistreContrats
{
    public const CAPACITE = 'CAP-CORE-009';

    public function __construct(
        private \PDO $index,
        private \PDO $registreIdentites,
        private \PDO $magasin,
        private ?Ctr01 $identites = null,
    ) {
        $this->identites ??= new Ctr01($index, $registreIdentites);
        SchemaContrats::migrer($this->magasin);
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudreContrat(string $reference): ?array
    {
        $c = $this->ligneContrat($reference);
        if ($c === null) {
            return null;
        }

        $active = $this->ligneVersionActive($reference);
        $versionActive = $active === null
            ? null
            : $this->ligneVersionParId((int) $active['contrat_version_id'])['version'] ?? null;

        return [
            'reference' => $c['reference'],
            'nom' => $c['nom'],
            'type_contrat' => $c['type_contrat'],
            'finalite_reference' => $c['finalite_reference'],
            'producteur_capacite_reference' => $c['producteur_capacite_reference'],
            'producteur_produit_reference' => $c['producteur_produit_reference'],
            'proprietaire_reference' => $c['proprietaire_reference'],
            'source_reference' => $c['source_reference'],
            'description' => $c['description'],
            'version_active' => $versionActive,
            'cree_le' => $c['cree_le'],
            'modifie_le' => $c['modifie_le'],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function listerContrats(array $filtres = []): array
    {
        $sql = 'SELECT * FROM contrat';
        $conditions = [];
        $args = [];
        if (isset($filtres['type_contrat'])) {
            $conditions[] = 'type_contrat = ?';
            $args[] = $filtres['type_contrat'];
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
            fn (array $c): array => $this->resoudreContrat((string) $c['reference']) ?? [],
            $st->fetchAll(),
        );
    }

    /** @return list<array<string,mixed>> */
    public function listerVersions(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM contrat_version WHERE contrat_reference = ? ORDER BY id'
        );
        $st->execute([$reference]);

        return array_map(fn (array $v): array => $this->projeterVersion($v), $st->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function resoudreVersion(string $reference, string $version): ?array
    {
        $v = $this->ligneVersion($reference, $version);

        return $v === null ? null : $this->projeterVersion($v, avecDetails: true);
    }

    /** @return array<string,mixed>|null */
    public function resoudreVersionActive(string $reference, ?string $date = null): ?array
    {
        $active = $this->ligneVersionActive($reference, $date);
        if ($active === null) {
            return null;
        }
        $v = $this->ligneVersionParId((int) $active['contrat_version_id']);

        return $v === null ? null : $this->projeterVersion($v, avecDetails: true);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreHistorique(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT cvc.* FROM contrat_version_cycle cvc
             JOIN contrat_version cv ON cv.id = cvc.contrat_version_id
             WHERE cv.contrat_reference = ?
             ORDER BY cvc.date_effet, cvc.id'
        );
        $st->execute([$reference]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function resoudreCompatibilite(string $reference, string $version): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return [];
        }
        $st = $this->magasin->prepare(
            'SELECT * FROM contrat_compatibilite WHERE contrat_version_id = ? ORDER BY id'
        );
        $st->execute([$v['id']]);

        return array_map(function (array $r): array {
            $r['divergences'] = json_decode((string) $r['divergences_json'], true) ?? [];

            return $r;
        }, $st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function resoudreConformite(string $reference, string $version): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return [];
        }
        $st = $this->magasin->prepare(
            'SELECT * FROM contrat_conformite WHERE contrat_version_id = ? ORDER BY id'
        );
        $st->execute([$v['id']]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function resoudreConsommateurs(string $reference, ?string $version = null): array
    {
        $v = $version === null ? null : $this->ligneVersion($reference, $version);
        if ($version !== null && $v === null) {
            return [];
        }
        if ($v === null) {
            $active = $this->ligneVersionActive($reference);
            if ($active === null) {
                return [];
            }
            $v = $this->ligneVersionParId((int) $active['contrat_version_id']);
        }

        return $this->partiesVersion((int) $v['id'], 'CONSOMMATEUR');
    }

    /**
     * Vérifie l'invariant central du registre : au plus une version active
     * par contrat à l'instant présent.
     *
     * @return array{coherent:bool,divergences:list<string>}
     */
    public function diagnostiquerRegistre(): array
    {
        $divergences = [];
        $references = $this->magasin->query('SELECT reference FROM contrat')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($references as $reference) {
            $st = $this->magasin->prepare(
                'SELECT COUNT(*) FROM (
                    SELECT cv.id FROM contrat_version cv
                    JOIN contrat_version_cycle cvc ON cvc.contrat_version_id = cv.id
                    WHERE cv.contrat_reference = ? AND cvc.etat = ?
                    AND cvc.id = (
                        SELECT id FROM contrat_version_cycle
                        WHERE contrat_version_id = cv.id ORDER BY date_effet DESC, id DESC LIMIT 1
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
    // Commandes gouvernées — identité du contrat

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function inscrireContrat(array $dossier): array
    {
        foreach (['reference', 'nom', 'type_contrat', 'finalite_reference', 'proprietaire_reference', 'source_reference', 'politique', 'producteur', 'source', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $reference = trim((string) $dossier['reference']);
        $typeContrat = (string) $dossier['type_contrat'];
        $proprietaire = trim((string) $dossier['proprietaire_reference']);
        $capacite = $this->nullable($dossier['producteur_capacite_reference'] ?? null);
        $produit = $this->nullable($dossier['producteur_produit_reference'] ?? null);

        if (!in_array($typeContrat, PolitiqueContrats::TYPES_CONTRAT, true)) {
            return $this->refus('TYPE_CONTRAT_INCONNU', 'type de contrat hors liste close');
        }
        if ($this->ligneContrat($reference) !== null) {
            return $this->refus('REFERENCE_DEJA_UTILISEE', "la référence `{$reference}` est déjà inscrite");
        }
        if (($capacite === null) === ($produit === null)) {
            return $this->refus('PRODUCTEUR_PRINCIPAL_AMBIGU', 'un contrat exige exactement un producteur principal (capacité XOR produit)');
        }
        if ($this->identites->resoudreIdentite($proprietaire) === null) {
            return $this->refus('PROPRIETAIRE_INCONNU', "l’identité `{$proprietaire}` n’existe pas");
        }

        $nom = trim((string) $dossier['nom']);
        $finalite = trim((string) $dossier['finalite_reference']);
        $sourceRef = trim((string) $dossier['source_reference']);
        $description = $this->nullable($dossier['description'] ?? null);

        return $this->transaction(function () use (
            $reference, $nom, $typeContrat, $finalite, $capacite, $produit, $proprietaire, $sourceRef, $description,
        ): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO contrat
                 (reference,nom,type_contrat,finalite_reference,producteur_capacite_reference,
                  producteur_produit_reference,proprietaire_reference,source_reference,description,
                  cree_le,modifie_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $nom, $typeContrat, $finalite, $capacite, $produit,
                $proprietaire, $sourceRef, $description, $maintenant, $maintenant,
            ]);

            return ['reference' => $reference];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function creerVersion(string $reference, array $dossier): array
    {
        $contrat = $this->ligneContrat($reference);
        if ($contrat === null) {
            return $this->refus('CONTRAT_INCONNU', "contrat `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $version = trim((string) ($dossier['version'] ?? ''));
        if (!preg_match(PolitiqueContrats::FORMAT_VERSION, $version)) {
            return $this->refus('VERSION_INVALIDE', 'la version doit suivre le format X.Y.Z');
        }
        if ($this->ligneVersion($reference, $version) !== null) {
            return $this->refus('VERSION_DEJA_UTILISEE', "la version `{$version}` existe déjà pour `{$reference}` — une référence de version retirée n’est jamais réutilisable");
        }
        $compatibiliteAnnoncee = (string) ($dossier['compatibilite_annoncee'] ?? 'COMPATIBLE');
        if (!in_array($compatibiliteAnnoncee, PolitiqueContrats::COMPATIBILITES_ANNONCEES, true)) {
            return $this->refus('COMPATIBILITE_ANNONCEE_INCONNUE', 'valeur hors liste close');
        }

        $description = $this->nullable($dossier['description'] ?? null);
        $dateEffetPrevue = $this->nullable($dossier['date_effet_prevue'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $version, $compatibiliteAnnoncee, $description, $dateEffetPrevue,
            $producteur, $preuve, $date, $politiqueAdmin, $correlation,
        ): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO contrat_version
                 (contrat_reference,version,schema_version,compatibilite_annoncee,description,
                  date_effet_prevue,empreinte_contenu,cree_par_reference,preuve_reference,cree_le)
                 VALUES(?,?,1,?,?,?,NULL,?,?,?)'
            )->execute([
                $reference, $version, $compatibiliteAnnoncee, $description, $dateEffetPrevue,
                $producteur, $preuve, $maintenant,
            ]);
            $id = (int) $this->magasin->lastInsertId();
            $this->inscrireCycle($id, 'BROUILLON', $date, null, null, null, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['contrat_reference' => $reference, 'version' => $version, 'id' => $id, 'etat' => 'BROUILLON'];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — contenu d'une version (BROUILLON uniquement)

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerPartie(string $reference, string $version, array $dossier): array
    {
        $v = $this->versionModifiable($reference, $version);
        if (isset($v['refus'])) {
            return $v;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $role = (string) ($dossier['role'] ?? '');
        if (!in_array($role, PolitiqueContrats::ROLES_PARTIE, true)) {
            return $this->refus('ROLE_INCONNU', 'rôle hors liste close');
        }
        $partieType = (string) ($dossier['partie_type'] ?? '');
        if (!in_array($partieType, PolitiqueContrats::TYPES_PARTIE, true)) {
            return $this->refus('TYPE_PARTIE_INCONNU', 'type de partie hors liste close');
        }
        $partieReference = trim((string) ($dossier['partie_reference'] ?? ''));
        if ($partieReference === '') {
            return $this->refus('PARTIE_REFERENCE_ABSENTE', 'partie_reference absente');
        }

        $st = $this->magasin->prepare(
            'SELECT 1 FROM contrat_partie WHERE contrat_version_id = ? AND role = ? AND partie_reference = ?'
        );
        $st->execute([$v['id'], $role, $partieReference]);
        if ($st->fetchColumn() !== false) {
            return ['contrat_version_id' => (int) $v['id'], 'role' => $role, 'partie_reference' => $partieReference, 'idempotent' => true];
        }

        return $this->transaction(function () use ($v, $role, $partieType, $partieReference): array {
            $this->magasin->prepare(
                'INSERT INTO contrat_partie (contrat_version_id,role,partie_type,partie_reference,cree_le)
                 VALUES(?,?,?,?,?)'
            )->execute([$v['id'], $role, $partieType, $partieReference, gmdate('c')]);

            return ['contrat_version_id' => (int) $v['id'], 'role' => $role, 'partie_reference' => $partieReference, 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerOperation(string $reference, string $version, array $dossier): array
    {
        $v = $this->versionModifiable($reference, $version);
        if (isset($v['refus'])) {
            return $v;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $refOperation = trim((string) ($dossier['reference_operation'] ?? ''));
        if ($refOperation === '') {
            return $this->refus('OPERATION_REFERENCE_ABSENTE', 'reference_operation absente');
        }
        $typeOperation = (string) ($dossier['type_operation'] ?? '');
        if (!in_array($typeOperation, PolitiqueContrats::TYPES_OPERATION, true)) {
            return $this->refus('TYPE_OPERATION_INCONNU', 'type d’opération hors liste close');
        }
        $st = $this->magasin->prepare(
            'SELECT 1 FROM contrat_operation WHERE contrat_version_id = ? AND reference_operation = ?'
        );
        $st->execute([$v['id'], $refOperation]);
        if ($st->fetchColumn() !== false) {
            return $this->refus('OPERATION_DEJA_DECLAREE', "l’opération `{$refOperation}` est déjà déclarée pour cette version");
        }

        $methode = $this->nullable($dossier['methode_http'] ?? null);
        $chemin = $this->nullable($dossier['chemin_http'] ?? null);
        $action = $this->nullable($dossier['action_autorisation'] ?? null);
        $duree = isset($dossier['duree_secondes']) && $dossier['duree_secondes'] !== null ? (int) $dossier['duree_secondes'] : null;
        $idempotente = (bool) ($dossier['idempotente'] ?? false);
        $auditObligatoire = (bool) ($dossier['audit_obligatoire'] ?? true);
        $ordre = $dossier['ordre'] ?? null;
        if ($ordre === null) {
            $stO = $this->magasin->prepare('SELECT COALESCE(MAX(ordre),0) FROM contrat_operation WHERE contrat_version_id = ?');
            $stO->execute([$v['id']]);
            $ordre = ((int) $stO->fetchColumn()) + 1;
        } else {
            $ordre = (int) $ordre;
        }

        return $this->transaction(function () use (
            $v, $refOperation, $typeOperation, $methode, $chemin, $action, $duree, $idempotente, $auditObligatoire, $ordre,
        ): array {
            $this->magasin->prepare(
                'INSERT INTO contrat_operation
                 (contrat_version_id,reference_operation,type_operation,methode_http,chemin_http,
                  action_autorisation,duree_secondes,idempotente,audit_obligatoire,ordre,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $v['id'], $refOperation, $typeOperation, $methode, $chemin, $action, $duree,
                $idempotente ? 1 : 0, $auditObligatoire ? 1 : 0, $ordre, gmdate('c'),
            ]);

            return ['contrat_version_id' => (int) $v['id'], 'reference_operation' => $refOperation];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerSchema(string $reference, string $version, array $dossier): array
    {
        $v = $this->versionModifiable($reference, $version);
        if (isset($v['refus'])) {
            return $v;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $sens = (string) ($dossier['sens'] ?? '');
        if (!in_array($sens, PolitiqueContrats::SENS_SCHEMA, true)) {
            return $this->refus('SENS_INCONNU', 'sens de schéma hors liste close');
        }
        $format = (string) ($dossier['format'] ?? '');
        if (!in_array($format, PolitiqueContrats::FORMATS_SCHEMA, true)) {
            return $this->refus('FORMAT_INCONNU', 'format de schéma hors liste close');
        }
        $operationReference = $this->nullable($dossier['operation_reference'] ?? null);
        if ($operationReference !== null) {
            $st = $this->magasin->prepare(
                'SELECT 1 FROM contrat_operation WHERE contrat_version_id = ? AND reference_operation = ?'
            );
            $st->execute([$v['id'], $operationReference]);
            if ($st->fetchColumn() === false) {
                return $this->refus('OPERATION_INCONNUE', "l’opération `{$operationReference}` n’est pas déclarée pour cette version");
            }
        }
        $contenu = isset($dossier['contenu']) ? (string) $dossier['contenu'] : null;
        $validation = ValidateurContrat::validerSchema($format, $contenu);
        if (!$validation['valide']) {
            return $this->refus('SCHEMA_INVALIDE', $validation['motif'] ?? 'schéma invalide');
        }

        $empreinte = hash('sha256', $format . '|' . ($contenu ?? ''));

        return $this->transaction(function () use ($v, $operationReference, $sens, $format, $contenu, $empreinte): array {
            $this->magasin->prepare(
                'INSERT INTO contrat_schema
                 (contrat_version_id,operation_reference,sens,format,contenu,empreinte,cree_le)
                 VALUES(?,?,?,?,?,?,?)'
            )->execute([$v['id'], $operationReference, $sens, $format, $contenu, $empreinte, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'contrat_version_id' => (int) $v['id'], 'sens' => $sens, 'empreinte' => $empreinte];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerErreur(string $reference, string $version, array $dossier): array
    {
        $v = $this->versionModifiable($reference, $version);
        if (isset($v['refus'])) {
            return $v;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $code = trim((string) ($dossier['code'] ?? ''));
        if ($code === '') {
            return $this->refus('CODE_ABSENT', 'code absent');
        }
        $description = trim((string) ($dossier['description'] ?? ''));
        if ($description === '') {
            return $this->refus('DESCRIPTION_ABSENTE', 'description absente');
        }
        $st = $this->magasin->prepare('SELECT 1 FROM contrat_erreur WHERE contrat_version_id = ? AND code = ?');
        $st->execute([$v['id'], $code]);
        if ($st->fetchColumn() !== false) {
            return $this->refus('ERREUR_DEJA_DECLAREE', "le code `{$code}` est déjà déclaré pour cette version");
        }
        $operationReference = $this->nullable($dossier['operation_reference'] ?? null);
        $statutHttp = isset($dossier['statut_http']) && $dossier['statut_http'] !== null ? (int) $dossier['statut_http'] : null;
        $retentable = (bool) ($dossier['retentable'] ?? false);
        $detailExposable = (bool) ($dossier['detail_exposable'] ?? true);

        return $this->transaction(function () use (
            $v, $operationReference, $code, $statutHttp, $retentable, $detailExposable, $description,
        ): array {
            $this->magasin->prepare(
                'INSERT INTO contrat_erreur
                 (contrat_version_id,operation_reference,code,statut_http,retentable,detail_exposable,description,cree_le)
                 VALUES(?,?,?,?,?,?,?,?)'
            )->execute([
                $v['id'], $operationReference, $code, $statutHttp, $retentable ? 1 : 0, $detailExposable ? 1 : 0,
                $description, gmdate('c'),
            ]);

            return ['contrat_version_id' => (int) $v['id'], 'code' => $code];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerObligation(string $reference, string $version, array $dossier): array
    {
        $v = $this->versionModifiable($reference, $version);
        if (isset($v['refus'])) {
            return $v;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $type = (string) ($dossier['type_obligation'] ?? '');
        if (!in_array($type, PolitiqueContrats::TYPES_OBLIGATION, true)) {
            return $this->refus('TYPE_OBLIGATION_INCONNU', 'type d’obligation hors liste close');
        }
        $description = trim((string) ($dossier['description'] ?? ''));
        $validation = ValidateurContrat::validerObligation($description);
        if (!$validation['valide']) {
            return $this->refus('OBLIGATION_INVALIDE', $validation['motif'] ?? 'obligation invalide');
        }

        return $this->transaction(function () use ($v, $type, $description): array {
            $this->magasin->prepare(
                'INSERT INTO contrat_obligation (contrat_version_id,type_obligation,description,cree_le)
                 VALUES(?,?,?,?)'
            )->execute([$v['id'], $type, $description, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'contrat_version_id' => (int) $v['id'], 'type_obligation' => $type];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — cycle de vie

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function soumettreVersion(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if ($etat === 'EN_VALIDATION') {
            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'EN_VALIDATION', 'idempotent' => true];
        }
        if ($etat !== 'BROUILLON') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version BROUILLON se soumet (état actuel `{$etat}`)");
        }
        $operations = $this->operationsVersion((int) $v['id']);
        $schemas = $this->schemasVersion((int) $v['id']);
        if ($operations === [] && $schemas === []) {
            return $this->refus('CONTENU_VIDE', 'une version sans opération ni schéma ne peut pas être soumise');
        }
        $producteurs = $this->partiesVersion((int) $v['id'], 'PRODUCTEUR');
        if ($producteurs === []) {
            return $this->refus('PRODUCTEUR_ABSENT', 'aucune partie PRODUCTEUR déclarée pour cette version');
        }
        $contrat = $this->ligneContrat((string) $v['contrat_reference']);
        if ($contrat !== null && $contrat['type_contrat'] === 'HTTP_API') {
            $consommateurs = $this->partiesVersion((int) $v['id'], 'CONSOMMATEUR');
            if ($consommateurs === []) {
                return $this->refus('CONSOMMATEUR_ABSENT', 'un contrat HTTP_API exige au moins un consommateur déclaré avant soumission');
            }
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $empreinte = $this->calculerEmpreinte((int) $v['id']);
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($v, $empreinte, $date, $producteur, $preuve, $politiqueAdmin, $correlation, $reference, $version): array {
            $this->magasin->prepare('UPDATE contrat_version SET empreinte_contenu = ? WHERE id = ?')
                ->execute([$empreinte, $v['id']]);
            $this->inscrireCycle((int) $v['id'], 'EN_VALIDATION', $date, null, null, null, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'EN_VALIDATION', 'empreinte_contenu' => $empreinte, 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function analyserCompatibilite(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if (!in_array($etat, ['EN_VALIDATION', 'ACTIVE'], true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version EN_VALIDATION (ou déjà ACTIVE, pour réanalyse) peut être analysée (état actuel `{$etat}`)");
        }
        if ($v['empreinte_contenu'] === null) {
            return $this->refus('CONTENU_NON_SOUMIS', 'la version n’a pas encore d’empreinte soumise à analyser');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $comparee = $this->ligneVersionActive($reference);
        $compareeId = $comparee === null ? null : (int) $comparee['contrat_version_id'];

        $resultat = AnalyseurCompatibilite::analyser(
            operationsAvant: $compareeId === null ? [] : $this->operationsVersion($compareeId),
            operationsApres: $this->operationsVersion((int) $v['id']),
            schemasAvant: $compareeId === null ? [] : $this->schemasVersion($compareeId),
            schemasApres: $this->schemasVersion((int) $v['id']),
            erreursAvant: $compareeId === null ? [] : $this->erreursVersion($compareeId),
            erreursApres: $this->erreursVersion((int) $v['id']),
            consommateursAvant: $compareeId === null ? [] : $this->partiesVersion($compareeId, 'CONSOMMATEUR'),
            consommateursApres: $this->partiesVersion((int) $v['id'], 'CONSOMMATEUR'),
        );

        $producteur = (string) $dossier['producteur'];

        return $this->transaction(function () use ($v, $compareeId, $resultat, $producteur): array {
            $this->magasin->prepare(
                'INSERT INTO contrat_compatibilite
                 (contrat_version_id,version_comparee_id,resultat,divergences_json,acteur_reference,cree_le)
                 VALUES(?,?,?,?,?,?)'
            )->execute([
                $v['id'], $compareeId, $resultat['resultat'],
                json_encode($resultat['divergences'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $producteur, gmdate('c'),
            ]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'resultat' => $resultat['resultat'], 'divergences' => $resultat['divergences']];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function enregistrerConformite(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if ($etat === 'BROUILLON') {
            return $this->refus('ETAT_INCOMPATIBLE', 'une version BROUILLON n’a pas encore de contenu figé à évaluer');
        }
        $resultat = (string) ($dossier['resultat'] ?? '');
        if (!in_array($resultat, PolitiqueContrats::RESULTATS_CONFORMITE, true)) {
            return $this->refus('RESULTAT_INCONNU', 'résultat de conformité hors liste close');
        }
        $artefact = trim((string) ($dossier['artefact_reference'] ?? ''));
        if ($artefact === '') {
            return $this->refus('ARTEFACT_ABSENT', 'artefact_reference absente — une conformité référence toujours un commit ou artefact précis');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $partieReference = $this->nullable($dossier['partie_reference'] ?? null);
        $resume = $this->nullable($dossier['resume'] ?? null);
        $producteur = (string) $dossier['producteur'];

        return $this->transaction(function () use ($v, $partieReference, $resultat, $artefact, $resume, $producteur): array {
            $this->magasin->prepare(
                'INSERT INTO contrat_conformite
                 (contrat_version_id,partie_reference,resultat,artefact_reference,resume,acteur_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?)'
            )->execute([$v['id'], $partieReference, $resultat, $artefact, $resume, $producteur, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'contrat_version_id' => (int) $v['id'], 'resultat' => $resultat];
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
            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'ACTIVE', 'idempotent' => true];
        }
        if ($etat !== 'EN_VALIDATION') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version EN_VALIDATION s’active (état actuel `{$etat}`)");
        }

        $analyse = $this->derniereAnalyse((int) $v['id']);
        if ($analyse === null) {
            return $this->refus('ANALYSE_MANQUANTE', 'aucune analyse de compatibilité enregistrée pour cette version exacte');
        }
        $conformite = $this->derniereConformiteConforme((int) $v['id']);
        if ($conformite === null) {
            return $this->refus('CONFORMITE_MANQUANTE', 'aucune conformité CONFORME enregistrée pour cette version exacte');
        }

        $planMigration = $this->nullable($dossier['plan_migration'] ?? null);
        $dateLimite = $this->nullable($dossier['date_limite_migration'] ?? null);
        if ($analyse['resultat'] === 'RUPTURE') {
            if ($planMigration === null || $dateLimite === null) {
                return $this->refus('PLAN_MIGRATION_REQUIS', 'une rupture exige un plan de migration et une date limite explicites avant activation');
            }
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

        return $this->transaction(function () use (
            $v, $ancienne, $date, $motif, $planMigration, $dateLimite, $producteur, $preuve, $politiqueAdmin, $correlation, $reference, $version,
        ): array {
            if ($ancienne !== null) {
                $this->inscrireCycle(
                    (int) $ancienne['contrat_version_id'], 'REMPLACEE', $date,
                    "remplacée par la version {$version}", null, null, $producteur, $politiqueAdmin, $preuve, $correlation,
                );
            }
            $this->inscrireCycle((int) $v['id'], 'ACTIVE', $date, $motif, $planMigration, $dateLimite, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'ACTIVE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function deprecierVersion(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if ($etat === 'DEPRECIEE') {
            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'DEPRECIEE', 'idempotent' => true];
        }
        if ($etat !== 'ACTIVE') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version ACTIVE se déprécie (état actuel `{$etat}`)");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $dateLimite = $this->nullable($dossier['date_limite_migration'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($v, $date, $motif, $dateLimite, $producteur, $preuve, $politiqueAdmin, $correlation, $reference, $version): array {
            $this->inscrireCycle((int) $v['id'], 'DEPRECIEE', $date, $motif, null, $dateLimite, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'DEPRECIEE', 'idempotent' => false];
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
            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'SUSPENDUE', 'idempotent' => true];
        }
        if (!in_array($etat, ['ACTIVE', 'DEPRECIEE'], true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version ACTIVE ou DEPRECIEE se suspend (état actuel `{$etat}`)");
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
            $this->inscrireCycle((int) $v['id'], 'SUSPENDUE', $date, $motif, null, null, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'SUSPENDUE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function retirerVersion(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if ($etat === 'RETIREE') {
            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'RETIREE', 'idempotent' => true];
        }
        if (!in_array($etat, ['ACTIVE', 'DEPRECIEE', 'SUSPENDUE'], true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version ACTIVE, DEPRECIEE ou SUSPENDUE se retire (état actuel `{$etat}`)");
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
            $this->inscrireCycle((int) $v['id'], 'RETIREE', $date, $motif, null, null, $producteur, $politiqueAdmin, $preuve, $correlation);
            $this->magasin->prepare('UPDATE contrat SET modifie_le = ? WHERE reference = ?')
                ->execute([gmdate('c'), $reference]);

            return ['contrat_reference' => $reference, 'version' => $version, 'etat' => 'RETIREE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function genererProjection(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $type = (string) ($dossier['type_projection'] ?? '');
        if (!in_array($type, PolitiqueContrats::TYPES_PROJECTION, true)) {
            return $this->refus('TYPE_PROJECTION_INCONNU', 'type de projection hors liste close');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $producteur = (string) $dossier['producteur'];

        $operations = $this->operationsVersion((int) $v['id']);
        $schemas = $this->schemasVersion((int) $v['id']);
        $contenu = match ($type) {
            'OPENAPI' => GenerateurOpenApi::genererFragmentJson($reference, $version, $operations, $schemas),
            'JSON_SCHEMA' => json_encode(array_map(
                static fn (array $s): array => ['sens' => $s['sens'], 'format' => $s['format'], 'contenu' => $s['contenu']],
                $schemas,
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            'PHP_INTERFACE' => GenerateurOpenApi::genererInterfacePhp($reference, $operations),
            'DOCUMENTATION' => GenerateurOpenApi::genererDocumentation($reference, $version, $operations),
        };
        $empreinte = hash('sha256', $contenu);

        return $this->transaction(function () use ($v, $type, $contenu, $empreinte, $producteur): array {
            $this->magasin->prepare(
                'INSERT INTO contrat_projection (contrat_version_id,type_projection,contenu,empreinte,acteur_reference,cree_le)
                 VALUES(?,?,?,?,?,?)'
            )->execute([$v['id'], $type, $contenu, $empreinte, $producteur, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'type_projection' => $type, 'empreinte' => $empreinte, 'contenu' => $contenu];
        });
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

    /** @return array<string,mixed> */
    private function versionModifiable(string $reference, string $version): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        if ($this->etatCourant((int) $v['id']) !== 'BROUILLON') {
            return $this->refus('VERSION_IMMUABLE', 'seule une version BROUILLON accepte de nouvelles déclarations');
        }

        return $v;
    }

    /** @return array<string,mixed>|null */
    private function ligneContrat(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM contrat WHERE reference = ?');
        $st->execute([$reference]);
        $c = $st->fetch();

        return $c === false ? null : $c;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersion(string $reference, string $version): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM contrat_version WHERE contrat_reference = ? AND version = ?');
        $st->execute([$reference, $version]);
        $v = $st->fetch();

        return $v === false ? null : $v;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersionParId(int $id): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM contrat_version WHERE id = ?');
        $st->execute([$id]);
        $v = $st->fetch();

        return $v === false ? null : $v;
    }

    /** La ligne de cycle ACTIVE courante pour un contrat, s'il en existe une. @return array<string,mixed>|null */
    private function ligneVersionActive(string $reference, ?string $date = null): ?array
    {
        $date ??= date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT cvc.* FROM contrat_version_cycle cvc
             JOIN contrat_version cv ON cv.id = cvc.contrat_version_id
             WHERE cv.contrat_reference = ? AND cvc.date_effet <= ?
             AND cvc.id = (
                 SELECT id FROM contrat_version_cycle
                 WHERE contrat_version_id = cvc.contrat_version_id AND date_effet <= ?
                 ORDER BY date_effet DESC, id DESC LIMIT 1
             )
             AND cvc.etat = ?'
        );
        $st->execute([$reference, $date, $date, 'ACTIVE']);
        $c = $st->fetch();

        return $c === false ? null : $c;
    }

    private function etatCourant(int $versionId): string
    {
        $st = $this->magasin->prepare(
            'SELECT etat FROM contrat_version_cycle WHERE contrat_version_id = ? ORDER BY date_effet DESC, id DESC LIMIT 1'
        );
        $st->execute([$versionId]);
        $etat = $st->fetchColumn();

        return $etat === false ? 'BROUILLON' : (string) $etat;
    }

    /** @return array<string,mixed>|null */
    private function derniereAnalyse(int $versionId): ?array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM contrat_compatibilite WHERE contrat_version_id = ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$versionId]);
        $a = $st->fetch();

        return $a === false ? null : $a;
    }

    /** @return array<string,mixed>|null */
    private function derniereConformiteConforme(int $versionId): ?array
    {
        $st = $this->magasin->prepare(
            "SELECT * FROM contrat_conformite WHERE contrat_version_id = ? AND resultat = 'CONFORME' ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$versionId]);
        $c = $st->fetch();

        return $c === false ? null : $c;
    }

    /** @return list<array<string,mixed>> */
    private function partiesVersion(int $versionId, ?string $role = null): array
    {
        if ($role === null) {
            $st = $this->magasin->prepare('SELECT * FROM contrat_partie WHERE contrat_version_id = ? ORDER BY id');
            $st->execute([$versionId]);
        } else {
            $st = $this->magasin->prepare('SELECT * FROM contrat_partie WHERE contrat_version_id = ? AND role = ? ORDER BY id');
            $st->execute([$versionId, $role]);
        }

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    private function operationsVersion(int $versionId): array
    {
        $st = $this->magasin->prepare('SELECT * FROM contrat_operation WHERE contrat_version_id = ? ORDER BY ordre');
        $st->execute([$versionId]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    private function schemasVersion(int $versionId): array
    {
        $st = $this->magasin->prepare('SELECT * FROM contrat_schema WHERE contrat_version_id = ? ORDER BY id');
        $st->execute([$versionId]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    private function erreursVersion(int $versionId): array
    {
        $st = $this->magasin->prepare('SELECT * FROM contrat_erreur WHERE contrat_version_id = ? ORDER BY id');
        $st->execute([$versionId]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    private function obligationsVersion(int $versionId): array
    {
        $st = $this->magasin->prepare('SELECT * FROM contrat_obligation WHERE contrat_version_id = ? ORDER BY id');
        $st->execute([$versionId]);

        return array_values($st->fetchAll());
    }

    private function inscrireCycle(
        int $versionId,
        string $etat,
        string $date,
        ?string $motif,
        ?string $planMigration,
        ?string $dateLimiteMigration,
        string $acteur,
        string $politique,
        string $preuve,
        ?string $correlation,
    ): void {
        if (!in_array($etat, PolitiqueContrats::ETATS_CYCLE, true)) {
            throw new ExceptionContrat("état `{$etat}` hors liste close");
        }
        $this->magasin->prepare(
            'INSERT INTO contrat_version_cycle
             (contrat_version_id,etat,date_effet,motif,plan_migration,date_limite_migration,
              acteur_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $versionId, $etat, $date, $motif, $planMigration, $dateLimiteMigration,
            $acteur, $preuve, $correlation, gmdate('c'),
        ]);
    }

    private function calculerEmpreinte(int $versionId): string
    {
        $canonique = [
            'parties' => array_map(static fn (array $p): array => [
                'role' => $p['role'], 'partie_type' => $p['partie_type'], 'partie_reference' => $p['partie_reference'],
            ], $this->partiesVersion($versionId)),
            'operations' => array_map(static fn (array $o): array => [
                'reference_operation' => $o['reference_operation'], 'type_operation' => $o['type_operation'],
                'methode_http' => $o['methode_http'], 'chemin_http' => $o['chemin_http'],
                'action_autorisation' => $o['action_autorisation'], 'duree_secondes' => $o['duree_secondes'],
                'idempotente' => (int) $o['idempotente'], 'audit_obligatoire' => (int) $o['audit_obligatoire'],
                'ordre' => (int) $o['ordre'],
            ], $this->operationsVersion($versionId)),
            'schemas' => array_map(static fn (array $s): array => [
                'operation_reference' => $s['operation_reference'], 'sens' => $s['sens'],
                'format' => $s['format'], 'empreinte' => $s['empreinte'],
            ], $this->schemasVersion($versionId)),
            'erreurs' => array_map(static fn (array $e): array => [
                'operation_reference' => $e['operation_reference'], 'code' => $e['code'],
                'statut_http' => $e['statut_http'], 'retentable' => (int) $e['retentable'],
            ], $this->erreursVersion($versionId)),
            'obligations' => array_map(static fn (array $o): array => [
                'type_obligation' => $o['type_obligation'], 'description' => $o['description'],
            ], $this->obligationsVersion($versionId)),
        ];

        return hash('sha256', json_encode($canonique, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string,mixed> $v @return array<string,mixed> */
    private function projeterVersion(array $v, bool $avecDetails = false): array
    {
        $projection = [
            'id' => (int) $v['id'],
            'contrat_reference' => $v['contrat_reference'],
            'version' => $v['version'],
            'compatibilite_annoncee' => $v['compatibilite_annoncee'],
            'description' => $v['description'],
            'date_effet_prevue' => $v['date_effet_prevue'],
            'date_fin_prevue' => $v['date_fin_prevue'],
            'empreinte_contenu' => $v['empreinte_contenu'],
            'etat' => $this->etatCourant((int) $v['id']),
            'cree_le' => $v['cree_le'],
        ];
        if ($avecDetails) {
            $projection['parties'] = $this->partiesVersion((int) $v['id']);
            $projection['operations'] = $this->operationsVersion((int) $v['id']);
            $projection['schemas'] = $this->schemasVersion((int) $v['id']);
            $projection['erreurs'] = $this->erreursVersion((int) $v['id']);
            $projection['obligations'] = $this->obligationsVersion((int) $v['id']);
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
