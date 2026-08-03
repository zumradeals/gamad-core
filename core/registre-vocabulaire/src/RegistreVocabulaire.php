<?php

declare(strict_types=1);

namespace Gamad\RegistreVocabulaire;

use Gamad\RegistreIdentites\Ctr01;

/**
 * Registre opérationnel du vocabulaire canonique (CAP-CORE-010).
 *
 * Un vocabulaire est un ensemble versionné de termes canoniques — code
 * stable, définition, libellés localisés, alias explicites, relations
 * sémantiques, mappings externes qualifiés et usages déclarés — jamais une
 * donnée métier, jamais une permission (celle-ci reste rendue par `Ctr03`,
 * CAP-CORE-004).
 *
 * Ajouter un terme à ce registre n'élargit aucune valeur de sécurité
 * ailleurs : les contraintes `CHECK` SQL et les constantes PHP des capacités
 * consommatrices restent la source d'enforcement (section 21 de la fiche de
 * codage). Ce registre décrit le vocabulaire ; il ne le fait pas appliquer à
 * leur place.
 */
final class RegistreVocabulaire
{
    public const CAPACITE = 'CAP-CORE-010';

    public function __construct(
        private \PDO $index,
        private \PDO $registreIdentites,
        private \PDO $magasin,
        private ?Ctr01 $identites = null,
    ) {
        $this->identites ??= new Ctr01($index, $registreIdentites);
        SchemaVocabulaire::migrer($this->magasin);
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudreVocabulaire(string $reference): ?array
    {
        $v = $this->ligneVocabulaire($reference);
        if ($v === null) {
            return null;
        }
        $active = $this->ligneVersionActive($reference);
        $versionActive = $active === null
            ? null
            : $this->ligneVersionParId((int) $active['vocabulaire_version_id'])['version'] ?? null;

        return [
            'reference' => $v['reference'], 'namespace' => $v['namespace'], 'nom' => $v['nom'],
            'domaine' => $v['domaine'], 'proprietaire_reference' => $v['proprietaire_reference'],
            'source_reference' => $v['source_reference'], 'portee' => $v['portee'],
            'description' => $v['description'], 'version_active' => $versionActive,
            'cree_le' => $v['cree_le'], 'modifie_le' => $v['modifie_le'],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function listerVocabulaires(array $filtres = []): array
    {
        $sql = 'SELECT * FROM vocabulaire';
        $conditions = [];
        $args = [];
        if (isset($filtres['domaine'])) {
            $conditions[] = 'domaine = ?';
            $args[] = $filtres['domaine'];
        }
        if (isset($filtres['portee'])) {
            $conditions[] = 'portee = ?';
            $args[] = $filtres['portee'];
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY reference';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        return array_map(fn (array $v): array => $this->resoudreVocabulaire((string) $v['reference']) ?? [], $st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function listerVersions(string $reference): array
    {
        $st = $this->magasin->prepare('SELECT * FROM vocabulaire_version WHERE vocabulaire_reference = ? ORDER BY id');
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
        $v = $this->ligneVersionParId((int) $active['vocabulaire_version_id']);

        return $v === null ? null : $this->projeterVersion($v, avecDetails: true);
    }

    /** @return array<string,mixed>|null */
    public function resoudreTerme(string $reference): ?array
    {
        $t = $this->ligneTerme($reference);
        if ($t === null) {
            return null;
        }

        return $this->projeterTerme($t);
    }

    /**
     * Résout un code dans la version active d'un vocabulaire, à une date
     * donnée. C'est le point d'entrée destiné aux capacités consommatrices
     * qui souhaitent vérifier un code sans dupliquer le registre.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreCodeActif(string $vocabulaireReference, string $code, ?string $date = null): ?array
    {
        $version = $this->resoudreVersionActive($vocabulaireReference, $date);
        if ($version === null) {
            return null;
        }
        foreach ($version['termes'] as $terme) {
            if ($terme['code'] === $code && $terme['date_fin'] === null) {
                return $terme;
            }
        }

        return null;
    }

    /** @return list<array<string,mixed>> */
    public function resoudreHistorique(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT vvc.* FROM vocabulaire_version_cycle vvc
             JOIN vocabulaire_version vv ON vv.id = vvc.vocabulaire_version_id
             WHERE vv.vocabulaire_reference = ?
             ORDER BY vvc.date_effet, vvc.id'
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

        return $this->resoudreCompatibiliteInterne((int) $v['id']);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreConformite(string $reference, string $version): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return [];
        }
        $st = $this->magasin->prepare('SELECT * FROM vocabulaire_conformite WHERE vocabulaire_version_id = ? ORDER BY execute_le');
        $st->execute([$v['id']]);

        return array_values($st->fetchAll());
    }

    /** @return array{coherent:bool,divergences:list<string>} */
    public function diagnostiquerRegistre(): array
    {
        $divergences = [];
        $references = $this->magasin->query('SELECT reference FROM vocabulaire')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($references as $reference) {
            $st = $this->magasin->prepare(
                'SELECT COUNT(*) FROM (
                    SELECT vv.id FROM vocabulaire_version vv
                    JOIN vocabulaire_version_cycle vvc ON vvc.vocabulaire_version_id = vv.id
                    WHERE vv.vocabulaire_reference = ? AND vvc.etat = ?
                    AND vvc.id = (
                        SELECT id FROM vocabulaire_version_cycle
                        WHERE vocabulaire_version_id = vv.id ORDER BY date_effet DESC, id DESC LIMIT 1
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
    // Commandes gouvernées — identité du vocabulaire

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function inscrireVocabulaire(array $dossier): array
    {
        foreach (['reference', 'namespace', 'nom', 'domaine', 'portee', 'proprietaire_reference', 'source_reference', 'politique', 'producteur', 'source', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $reference = trim((string) $dossier['reference']);
        $namespace = trim((string) $dossier['namespace']);
        $portee = (string) $dossier['portee'];
        $proprietaire = trim((string) $dossier['proprietaire_reference']);

        if (!in_array($portee, PolitiqueVocabulaire::PORTEES, true)) {
            return $this->refus('PORTEE_INCONNUE', 'portée hors liste close');
        }
        if ($this->ligneVocabulaire($reference) !== null) {
            return $this->refus('REFERENCE_DEJA_UTILISEE', "la référence `{$reference}` est déjà inscrite");
        }
        $stNs = $this->magasin->prepare('SELECT 1 FROM vocabulaire WHERE namespace = ?');
        $stNs->execute([$namespace]);
        if ($stNs->fetchColumn() !== false) {
            return $this->refus('NAMESPACE_DEJA_UTILISE', "le namespace `{$namespace}` est déjà utilisé");
        }
        if ($this->identites->resoudreIdentite($proprietaire) === null) {
            return $this->refus('PROPRIETAIRE_INCONNU', "l’identité `{$proprietaire}` n’existe pas");
        }

        $nom = trim((string) $dossier['nom']);
        $domaine = trim((string) $dossier['domaine']);
        $sourceRef = trim((string) $dossier['source_reference']);
        $description = $this->nullable($dossier['description'] ?? null);

        return $this->transaction(function () use ($reference, $namespace, $nom, $domaine, $portee, $proprietaire, $sourceRef, $description): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO vocabulaire (reference,namespace,nom,domaine,proprietaire_reference,source_reference,portee,description,cree_le,modifie_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([$reference, $namespace, $nom, $domaine, $proprietaire, $sourceRef, $portee, $description, $maintenant, $maintenant]);

            return ['reference' => $reference];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function creerVersion(string $reference, array $dossier): array
    {
        if ($this->ligneVocabulaire($reference) === null) {
            return $this->refus('VOCABULAIRE_INCONNU', "vocabulaire `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $version = trim((string) ($dossier['version'] ?? ''));
        if (!preg_match(PolitiqueVocabulaire::FORMAT_VERSION, $version)) {
            return $this->refus('VERSION_INVALIDE', 'la version doit suivre le format X.Y.Z');
        }
        if ($this->ligneVersion($reference, $version) !== null) {
            return $this->refus('VERSION_DEJA_UTILISEE', "la version `{$version}` existe déjà pour `{$reference}`");
        }

        $dateEffetPrevue = $this->nullable($dossier['date_effet_prevue'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $version, $dateEffetPrevue, $producteur, $preuve, $date, $politiqueAdmin, $correlation): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO vocabulaire_version (vocabulaire_reference,version,schema_version,date_effet_prevue,empreinte_contenu,cree_par_reference,preuve_reference,cree_le)
                 VALUES(?,?,1,?,NULL,?,?,?)'
            )->execute([$reference, $version, $dateEffetPrevue, $producteur, $preuve, $maintenant]);
            $id = (int) $this->magasin->lastInsertId();
            $this->inscrireCycle($id, 'BROUILLON', $date, null, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['vocabulaire_reference' => $reference, 'version' => $version, 'id' => $id, 'etat' => 'BROUILLON'];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — contenu d'une version (BROUILLON uniquement)

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function ajouterTerme(string $reference, string $version, array $dossier): array
    {
        $v = $this->versionModifiable($reference, $version);
        if (isset($v['refus'])) {
            return $v;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $termeReference = trim((string) ($dossier['reference'] ?? ''));
        if ($termeReference === '') {
            return $this->refus('TERME_REFERENCE_ABSENTE', 'reference absente');
        }
        if ($this->ligneTerme($termeReference) !== null) {
            return $this->refus('TERME_REFERENCE_DEJA_UTILISEE', "la référence de terme `{$termeReference}` est déjà utilisée");
        }
        $code = trim((string) ($dossier['code'] ?? ''));
        if (!ValidateurTerme::codeValide($code)) {
            return $this->refus('CODE_INVALIDE', 'le code doit être en MAJUSCULES_SOULIGNEES');
        }
        $typeSemantique = (string) ($dossier['type_semantique'] ?? '');
        if (!in_array($typeSemantique, PolitiqueVocabulaire::TYPES_SEMANTIQUES, true)) {
            return $this->refus('TYPE_SEMANTIQUE_INCONNU', 'type sémantique hors liste close');
        }
        $definition = trim((string) ($dossier['definition'] ?? ''));
        $validation = ValidateurTerme::validerDefinition($definition);
        if (!$validation['valide']) {
            return $this->refus('DEFINITION_INVALIDE', $validation['motif'] ?? 'définition invalide');
        }
        // Un code retiré ne se réutilise jamais avec un autre sens : on
        // vérifie tout l'historique du vocabulaire, pas seulement cette
        // version — y compris les versions déjà retirées.
        $stHist = $this->magasin->prepare(
            'SELECT DISTINCT t.reference FROM terme t
             JOIN vocabulaire_version vv ON vv.id = t.vocabulaire_version_id
             WHERE vv.vocabulaire_reference = ? AND t.code = ?'
        );
        $stHist->execute([$reference, $code]);
        $referencesExistantes = $stHist->fetchAll(\PDO::FETCH_COLUMN);
        if ($referencesExistantes !== [] && !in_array($termeReference, $referencesExistantes, true)) {
            return $this->refus('CODE_DEJA_UTILISE_AUTRE_SENS', "le code `{$code}` a déjà été utilisé par une autre référence de terme dans ce vocabulaire");
        }

        $ordreAffichage = isset($dossier['ordre_affichage']) && $dossier['ordre_affichage'] !== null ? (int) $dossier['ordre_affichage'] : null;
        $dateDebut = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        $remplacePar = $this->nullable($dossier['remplace_par_reference'] ?? null);

        return $this->transaction(function () use ($termeReference, $v, $code, $definition, $typeSemantique, $ordreAffichage, $dateDebut, $remplacePar): array {
            $this->magasin->prepare(
                'INSERT INTO terme (reference,vocabulaire_version_id,code,definition,type_semantique,ordre_affichage,date_debut,date_fin,remplace_par_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,NULL,?,?)'
            )->execute([$termeReference, $v['id'], $code, $definition, $typeSemantique, $ordreAffichage, $dateDebut, $remplacePar, gmdate('c')]);

            return ['reference' => $termeReference, 'code' => $code];
        });
    }

    /**
     * Fait évoluer un terme existant vers une nouvelle version du même
     * vocabulaire : `terme.reference` est une clé primaire globale, une
     * ligne ne change jamais de version, donc l'évolution insère toujours
     * une ligne neuve sous une référence neuve et relie l'ancienne à la
     * nouvelle par `remplace_par_reference`. `AnalyseurCompatibilite` suit
     * cette lignée pour comparer l'ancien et le nouveau contenu au lieu de
     * ne constater qu'une disparition.
     *
     * @param array<string,mixed> $dossier @return array<string,mixed>
     */
    public function evoluerTerme(string $ancienneReference, string $nouvelleVersion, array $dossier): array
    {
        $ancien = $this->ligneTerme($ancienneReference);
        if ($ancien === null) {
            return $this->refus('TERME_INCONNU', "terme `{$ancienneReference}` inconnu");
        }
        if ($ancien['remplace_par_reference'] !== null) {
            return $this->refus('TERME_DEJA_REMPLACE', "le terme `{$ancienneReference}` a déjà un successeur");
        }
        $ancienneVersion = $this->ligneVersionParId((int) $ancien['vocabulaire_version_id']);
        if ($ancienneVersion === null) {
            return $this->refus('VERSION_INCONNUE', 'version d’origine introuvable');
        }
        $etatAncienneVersion = $this->etatCourant((int) $ancienneVersion['id']);
        if (!in_array($etatAncienneVersion, ['ACTIVE', 'DEPRECIEE'], true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "seul un terme d’une version ACTIVE ou DEPRECIEE peut évoluer (état actuel `{$etatAncienneVersion}`)");
        }
        $reference = (string) $ancienneVersion['vocabulaire_reference'];
        $v = $this->versionModifiable($reference, $nouvelleVersion);
        if (isset($v['refus'])) {
            return $v;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $nouvelleReference = trim((string) ($dossier['reference'] ?? ''));
        if ($nouvelleReference === '') {
            return $this->refus('TERME_REFERENCE_ABSENTE', 'reference absente');
        }
        if ($nouvelleReference === $ancienneReference) {
            return $this->refus('TERME_REFERENCE_DEJA_UTILISEE', 'une évolution exige une référence différente de l’originale');
        }
        if ($this->ligneTerme($nouvelleReference) !== null) {
            return $this->refus('TERME_REFERENCE_DEJA_UTILISEE', "la référence de terme `{$nouvelleReference}` est déjà utilisée");
        }
        $code = trim((string) ($dossier['code'] ?? $ancien['code']));
        if (!ValidateurTerme::codeValide($code)) {
            return $this->refus('CODE_INVALIDE', 'le code doit être en MAJUSCULES_SOULIGNEES');
        }
        $typeSemantique = (string) ($dossier['type_semantique'] ?? $ancien['type_semantique']);
        if (!in_array($typeSemantique, PolitiqueVocabulaire::TYPES_SEMANTIQUES, true)) {
            return $this->refus('TYPE_SEMANTIQUE_INCONNU', 'type sémantique hors liste close');
        }
        $definition = trim((string) ($dossier['definition'] ?? $ancien['definition']));
        $validation = ValidateurTerme::validerDefinition($definition);
        if (!$validation['valide']) {
            return $this->refus('DEFINITION_INVALIDE', $validation['motif'] ?? 'définition invalide');
        }
        $stHist = $this->magasin->prepare(
            'SELECT DISTINCT t.reference FROM terme t
             JOIN vocabulaire_version vv ON vv.id = t.vocabulaire_version_id
             WHERE vv.vocabulaire_reference = ? AND t.code = ?'
        );
        $stHist->execute([$reference, $code]);
        $referencesExistantes = $stHist->fetchAll(\PDO::FETCH_COLUMN);
        // Toute la lignée de l'ancienne référence — pas seulement son
        // prédécesseur immédiat — a légitimement porté ce code au fil des
        // évolutions successives ; seul un code repris par une lignée tierce
        // est une collision de sens.
        $referencesAutorisees = [...$this->lignageAscendant($ancienneReference), $nouvelleReference];
        if ($referencesExistantes !== [] && array_diff($referencesExistantes, $referencesAutorisees) !== []) {
            return $this->refus('CODE_DEJA_UTILISE_AUTRE_SENS', "le code `{$code}` a déjà été utilisé par une autre référence de terme dans ce vocabulaire");
        }

        $ordreAffichage = isset($dossier['ordre_affichage']) && $dossier['ordre_affichage'] !== null
            ? (int) $dossier['ordre_affichage']
            : ($ancien['ordre_affichage'] === null ? null : (int) $ancien['ordre_affichage']);
        $dateDebut = (string) ($dossier['date_debut'] ?? date('Y-m-d'));

        return $this->transaction(function () use ($ancienneReference, $nouvelleReference, $v, $code, $definition, $typeSemantique, $ordreAffichage, $dateDebut): array {
            $this->magasin->prepare(
                'INSERT INTO terme (reference,vocabulaire_version_id,code,definition,type_semantique,ordre_affichage,date_debut,date_fin,remplace_par_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,NULL,NULL,?)'
            )->execute([$nouvelleReference, $v['id'], $code, $definition, $typeSemantique, $ordreAffichage, $dateDebut, gmdate('c')]);
            $this->magasin->prepare('UPDATE terme SET remplace_par_reference = ? WHERE reference = ?')
                ->execute([$nouvelleReference, $ancienneReference]);

            return ['reference' => $nouvelleReference, 'code' => $code, 'evolue_depuis' => $ancienneReference];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function ajouterLibelle(string $termeReference, array $dossier): array
    {
        $t = $this->ligneTerme($termeReference);
        if ($t === null) {
            return $this->refus('TERME_INCONNU', "terme `{$termeReference}` inconnu");
        }
        $vm = $this->versionModifiableParId((int) $t['vocabulaire_version_id']);
        if (isset($vm['refus'])) {
            return $vm;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $locale = (string) ($dossier['locale'] ?? '');
        if (!in_array($locale, PolitiqueVocabulaire::LOCALES, true)) {
            return $this->refus('LOCALE_INCONNUE', 'locale hors liste close');
        }
        $libelle = trim((string) ($dossier['libelle'] ?? ''));
        if ($libelle === '') {
            return $this->refus('LIBELLE_VIDE', 'libellé absent');
        }
        $principal = (bool) ($dossier['principal'] ?? true);
        if ($principal) {
            $stExiste = $this->magasin->prepare(
                'SELECT 1 FROM terme_libelle WHERE terme_reference = ? AND locale = ? AND principal = 1'
            );
            $stExiste->execute([$termeReference, $locale]);
            if ($stExiste->fetchColumn() !== false) {
                return $this->refus('LIBELLE_PRINCIPAL_DEJA_DEFINI', "un libellé principal existe déjà pour la locale `{$locale}`");
            }
        }
        $descriptionCourte = $this->nullable($dossier['description_courte'] ?? null);

        return $this->transaction(function () use ($termeReference, $locale, $libelle, $descriptionCourte, $principal): array {
            $this->magasin->prepare(
                'INSERT INTO terme_libelle (terme_reference,locale,libelle,description_courte,principal,cree_le)
                 VALUES(?,?,?,?,?,?)'
            )->execute([$termeReference, $locale, $libelle, $descriptionCourte, $principal ? 1 : 0, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'terme_reference' => $termeReference, 'locale' => $locale];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function ajouterAlias(string $termeReference, array $dossier): array
    {
        $t = $this->ligneTerme($termeReference);
        if ($t === null) {
            return $this->refus('TERME_INCONNU', "terme `{$termeReference}` inconnu");
        }
        $vm = $this->versionModifiableParId((int) $t['vocabulaire_version_id']);
        if (isset($vm['refus'])) {
            return $vm;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $alias = trim((string) ($dossier['alias'] ?? ''));
        if ($alias === '') {
            return $this->refus('ALIAS_VIDE', 'alias absent');
        }
        $typeAlias = (string) ($dossier['type_alias'] ?? '');
        if (!in_array($typeAlias, PolitiqueVocabulaire::TYPES_ALIAS, true)) {
            return $this->refus('TYPE_ALIAS_INCONNU', 'type d’alias hors liste close');
        }
        $sourceRef = trim((string) ($dossier['source_reference'] ?? ''));
        if ($sourceRef === '') {
            return $this->refus('SOURCE_ABSENTE', 'source_reference absente');
        }
        // Ambiguïté interdite dans le même contexte : même vocabulaire, même
        // chaîne d'alias pointant vers une référence de terme différente.
        $stAmbigu = $this->magasin->prepare(
            'SELECT DISTINCT ta.terme_reference FROM terme_alias ta
             JOIN terme t2 ON t2.reference = ta.terme_reference
             WHERE ta.alias = ? AND t2.vocabulaire_version_id = ?'
        );
        $stAmbigu->execute([$alias, $t['vocabulaire_version_id']]);
        $porteurs = $stAmbigu->fetchAll(\PDO::FETCH_COLUMN);
        if ($porteurs !== [] && !in_array($termeReference, $porteurs, true)) {
            return $this->refus('ALIAS_AMBIGU', "l’alias `{$alias}` désigne déjà un autre terme dans ce vocabulaire");
        }

        $locale = $this->nullable($dossier['locale'] ?? null);
        $dateDebut = (string) ($dossier['date_debut'] ?? date('Y-m-d'));

        return $this->transaction(function () use ($termeReference, $alias, $locale, $typeAlias, $dateDebut, $sourceRef): array {
            $this->magasin->prepare(
                'INSERT INTO terme_alias (terme_reference,alias,locale,type_alias,date_debut,date_fin,source_reference,cree_le)
                 VALUES(?,?,?,?,?,NULL,?,?)'
            )->execute([$termeReference, $alias, $locale, $typeAlias, $dateDebut, $sourceRef, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'terme_reference' => $termeReference, 'alias' => $alias];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerRelation(string $termeSourceReference, string $termeCibleReference, array $dossier): array
    {
        $source = $this->ligneTerme($termeSourceReference);
        if ($source === null) {
            return $this->refus('TERME_SOURCE_INCONNU', "terme `{$termeSourceReference}` inconnu");
        }
        $cible = $this->ligneTerme($termeCibleReference);
        if ($cible === null) {
            return $this->refus('TERME_CIBLE_INCONNU', "terme `{$termeCibleReference}` inconnu");
        }
        if ($termeSourceReference === $termeCibleReference) {
            return $this->refus('AUTO_RELATION_REFUSEE', 'un terme ne peut pas être en relation avec lui-même');
        }
        $vm = $this->versionModifiableParId((int) $source['vocabulaire_version_id']);
        if (isset($vm['refus'])) {
            return $vm;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $typeRelation = (string) ($dossier['type_relation'] ?? '');
        if (!in_array($typeRelation, PolitiqueVocabulaire::TYPES_RELATION, true)) {
            return $this->refus('TYPE_RELATION_INCONNU', 'type de relation hors liste close');
        }
        if (in_array($typeRelation, ['PLUS_LARGE_QUE', 'PLUS_ETROIT_QUE'], true)
            && $this->creeraitUnCycle($termeSourceReference, $termeCibleReference, $typeRelation)) {
            return $this->refus('CYCLE_HIERARCHIQUE_REFUSE', 'cette relation créerait un cycle hiérarchique');
        }
        $preuve = trim((string) ($dossier['preuve'] ?? ''));
        if ($preuve === '') {
            return $this->refus('PREUVE_ABSENTE', 'preuve_reference absente');
        }
        $dateEffet = (string) ($dossier['date_effet'] ?? date('Y-m-d'));

        return $this->transaction(function () use ($termeSourceReference, $termeCibleReference, $typeRelation, $dateEffet, $preuve): array {
            $this->magasin->prepare(
                'INSERT INTO terme_relation (terme_source_reference,terme_cible_reference,type_relation,date_effet,preuve_reference,cree_le)
                 VALUES(?,?,?,?,?,?)'
            )->execute([$termeSourceReference, $termeCibleReference, $typeRelation, $dateEffet, $preuve, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'terme_source_reference' => $termeSourceReference, 'terme_cible_reference' => $termeCibleReference];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerMappingExterne(string $termeReference, array $dossier): array
    {
        $t = $this->ligneTerme($termeReference);
        if ($t === null) {
            return $this->refus('TERME_INCONNU', "terme `{$termeReference}` inconnu");
        }
        $vm = $this->versionModifiableParId((int) $t['vocabulaire_version_id']);
        if (isset($vm['refus'])) {
            return $vm;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $systeme = trim((string) ($dossier['systeme_reference'] ?? ''));
        $vocExterne = trim((string) ($dossier['vocabulaire_externe'] ?? ''));
        $codeExterne = trim((string) ($dossier['code_externe'] ?? ''));
        if ($systeme === '' || $vocExterne === '' || $codeExterne === '') {
            return $this->refus('MAPPING_INCOMPLET', 'systeme_reference, vocabulaire_externe et code_externe sont obligatoires');
        }
        $sens = (string) ($dossier['sens'] ?? '');
        if (!in_array($sens, PolitiqueVocabulaire::SENS_MAPPING, true)) {
            return $this->refus('SENS_INCONNU', 'sens hors liste close');
        }
        $statut = (string) ($dossier['statut_mapping'] ?? '');
        if (!in_array($statut, PolitiqueVocabulaire::STATUTS_MAPPING, true)) {
            return $this->refus('STATUT_MAPPING_INCONNU', 'statut de mapping hors liste close');
        }
        $preuve = trim((string) ($dossier['preuve'] ?? ''));
        if ($preuve === '') {
            return $this->refus('PREUVE_ABSENTE', 'preuve_reference absente');
        }
        $dateDebut = (string) ($dossier['date_debut'] ?? date('Y-m-d'));

        return $this->transaction(function () use ($termeReference, $systeme, $vocExterne, $codeExterne, $sens, $statut, $dateDebut, $preuve): array {
            $this->magasin->prepare(
                'INSERT INTO terme_mapping_externe
                 (terme_reference,systeme_reference,vocabulaire_externe,code_externe,sens,statut_mapping,date_debut,date_fin,preuve_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,NULL,?,?)'
            )->execute([$termeReference, $systeme, $vocExterne, $codeExterne, $sens, $statut, $dateDebut, $preuve, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'terme_reference' => $termeReference, 'statut_mapping' => $statut];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerUsage(string $termeReference, array $dossier): array
    {
        $t = $this->ligneTerme($termeReference);
        if ($t === null) {
            return $this->refus('TERME_INCONNU', "terme `{$termeReference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $usageType = (string) ($dossier['usage_type'] ?? '');
        if (!in_array($usageType, PolitiqueVocabulaire::TYPES_USAGE, true)) {
            return $this->refus('TYPE_USAGE_INCONNU', 'type d’usage hors liste close');
        }
        $capacite = $this->nullable($dossier['capacite_reference'] ?? null);
        $contrat = $this->nullable($dossier['contrat_reference'] ?? null);
        $contratVersion = $this->nullable($dossier['contrat_version'] ?? null);
        $politique = $this->nullable($dossier['politique_reference'] ?? null);
        $produit = $this->nullable($dossier['produit_reference'] ?? null);
        if ($capacite === null && $contrat === null && $politique === null && $produit === null) {
            return $this->refus('CONSOMMATEUR_ABSENT', 'un usage doit désigner au moins un consommateur (capacité, contrat, politique ou produit)');
        }
        $obligatoire = (bool) ($dossier['obligatoire'] ?? false);
        $dateDebut = (string) ($dossier['date_debut'] ?? date('Y-m-d'));

        return $this->transaction(function () use ($termeReference, $capacite, $contrat, $contratVersion, $politique, $produit, $usageType, $obligatoire, $dateDebut): array {
            $this->magasin->prepare(
                'INSERT INTO terme_usage
                 (terme_reference,capacite_reference,contrat_reference,contrat_version,politique_reference,produit_reference,usage_type,obligatoire,date_debut,date_fin,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,NULL,?)'
            )->execute([$termeReference, $capacite, $contrat, $contratVersion, $politique, $produit, $usageType, $obligatoire ? 1 : 0, $dateDebut, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'terme_reference' => $termeReference, 'usage_type' => $usageType];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — cycle de vie de la version

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function soumettreVersion(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $etat = $this->etatCourant((int) $v['id']);
        if ($etat === 'EN_VALIDATION') {
            return ['vocabulaire_reference' => $reference, 'version' => $version, 'etat' => 'EN_VALIDATION', 'idempotent' => true];
        }
        if ($etat !== 'BROUILLON') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version BROUILLON se soumet (état actuel `{$etat}`)");
        }
        $termes = $this->termesVersion((int) $v['id']);
        if ($termes === []) {
            return $this->refus('AUCUN_TERME', 'une version sans terme ne peut pas être soumise');
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
            $this->magasin->prepare('UPDATE vocabulaire_version SET empreinte_contenu = ? WHERE id = ?')->execute([$empreinte, $v['id']]);
            $this->inscrireCycle((int) $v['id'], 'EN_VALIDATION', $date, null, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['vocabulaire_reference' => $reference, 'version' => $version, 'etat' => 'EN_VALIDATION', 'empreinte_contenu' => $empreinte, 'idempotent' => false];
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
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $comparee = $this->ligneVersionActive($reference);
        $compareeId = $comparee === null ? null : (int) $comparee['vocabulaire_version_id'];
        $resultat = AnalyseurCompatibilite::analyser(
            $compareeId === null ? [] : $this->termesVersion($compareeId),
            $this->termesVersion((int) $v['id']),
        );
        $producteur = (string) $dossier['producteur'];

        return $this->transaction(function () use ($v, $compareeId, $resultat, $producteur): array {
            $this->magasin->prepare(
                'INSERT INTO vocabulaire_compatibilite (vocabulaire_version_id,version_comparee_id,resultat,divergences_json,acteur_reference,cree_le)
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
    public function genererProjection(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        $type = (string) ($dossier['type_projection'] ?? '');
        if (!in_array($type, PolitiqueVocabulaire::TYPES_PROJECTION, true)) {
            return $this->refus('TYPE_PROJECTION_INCONNU', 'type de projection hors liste close');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $termes = $this->termesVersion((int) $v['id']);
        $contenu = match ($type) {
            'JSON' => GenerateurProjection::genererJson($reference, $version, $termes),
            'PHP_CONSTANTS' => GenerateurProjection::genererConstantesPhp($reference, $termes),
            'OPENAPI_ENUM' => GenerateurProjection::genererEnumOpenApi($termes),
            'SQL_CHECK' => GenerateurProjection::genererContrainteSql('code', $termes),
            'DOCUMENTATION' => GenerateurProjection::genererDocumentation($reference, $version, $termes),
        };
        $empreinte = hash('sha256', $contenu);
        $producteur = (string) $dossier['producteur'];

        return $this->transaction(function () use ($v, $type, $contenu, $empreinte, $producteur): array {
            $this->magasin->prepare(
                'INSERT INTO vocabulaire_projection (vocabulaire_version_id,type_projection,chemin_artefact,contenu_json,empreinte_artefact,generee_le,cree_le)
                 VALUES(?,?,NULL,?,?,?,?)'
            )->execute([$v['id'], $type, $contenu, $empreinte, gmdate('c'), gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'type_projection' => $type, 'empreinte' => $empreinte, 'contenu' => $contenu];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function enregistrerConformite(string $reference, string $version, array $dossier): array
    {
        $v = $this->ligneVersion($reference, $version);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$version}` inconnue pour `{$reference}`");
        }
        if ($this->etatCourant((int) $v['id']) === 'BROUILLON') {
            return $this->refus('ETAT_INCOMPATIBLE', 'une version BROUILLON n’a pas encore de contenu figé à évaluer');
        }
        $resultat = (string) ($dossier['resultat'] ?? '');
        if (!in_array($resultat, PolitiqueVocabulaire::RESULTATS_CONFORMITE, true)) {
            return $this->refus('RESULTAT_INCONNU', 'résultat de conformité hors liste close');
        }
        $consommateur = trim((string) ($dossier['consommateur_reference'] ?? ''));
        if ($consommateur === '') {
            return $this->refus('CONSOMMATEUR_ABSENT', 'consommateur_reference absente');
        }
        $typeConsommateur = (string) ($dossier['type_consommateur'] ?? '');
        if (!in_array($typeConsommateur, PolitiqueVocabulaire::TYPES_CONSOMMATEUR, true)) {
            return $this->refus('TYPE_CONSOMMATEUR_INCONNU', 'type de consommateur hors liste close');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $commit = $this->nullable($dossier['commit_reference'] ?? null);
        $resume = (string) ($dossier['rapport_resume_json'] ?? '{}');

        return $this->transaction(function () use ($v, $consommateur, $typeConsommateur, $resultat, $commit, $resume): array {
            $ref = 'CONF-VOC-' . strtoupper(bin2hex(random_bytes(8)));
            $this->magasin->prepare(
                'INSERT INTO vocabulaire_conformite (reference,vocabulaire_version_id,consommateur_reference,type_consommateur,resultat,commit_reference,rapport_resume_json,execute_le,expire_le)
                 VALUES(?,?,?,?,?,?,?,?,NULL)'
            )->execute([$ref, $v['id'], $consommateur, $typeConsommateur, $resultat, $commit, $resume, gmdate('c')]);

            return ['reference' => $ref, 'resultat' => $resultat];
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
            return ['vocabulaire_reference' => $reference, 'version' => $version, 'etat' => 'ACTIVE', 'idempotent' => true];
        }
        if ($etat !== 'EN_VALIDATION') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version EN_VALIDATION s’active (état actuel `{$etat}`)");
        }
        $analyse = $this->derniereAnalyse((int) $v['id']);
        if ($analyse === null) {
            return $this->refus('ANALYSE_MANQUANTE', 'aucune analyse de compatibilité enregistrée pour cette version exacte');
        }
        $stProj = $this->magasin->prepare('SELECT 1 FROM vocabulaire_projection WHERE vocabulaire_version_id = ? LIMIT 1');
        $stProj->execute([$v['id']]);
        if ($stProj->fetchColumn() === false) {
            return $this->refus('PROJECTION_MANQUANTE', 'aucune projection générée pour cette version exacte');
        }
        $stConf = $this->magasin->prepare("SELECT 1 FROM vocabulaire_conformite WHERE vocabulaire_version_id = ? AND resultat = 'CONFORME' LIMIT 1");
        $stConf->execute([$v['id']]);
        if ($stConf->fetchColumn() === false) {
            return $this->refus('CONFORMITE_MANQUANTE', 'aucune conformité CONFORME enregistrée pour cette version exacte');
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
                    (int) $ancienne['vocabulaire_version_id'], 'REMPLACEE', $date,
                    "remplacée par la version {$version}", $producteur, $politiqueAdmin, $preuve, $correlation,
                );
            }
            $this->inscrireCycle((int) $v['id'], 'ACTIVE', $date, $motif, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['vocabulaire_reference' => $reference, 'version' => $version, 'etat' => 'ACTIVE', 'idempotent' => false];
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
            return ['vocabulaire_reference' => $reference, 'version' => $version, 'etat' => 'DEPRECIEE', 'idempotent' => true];
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
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $politiqueAdmin = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($v, $date, $motif, $producteur, $preuve, $politiqueAdmin, $correlation, $reference, $version): array {
            $this->inscrireCycle((int) $v['id'], 'DEPRECIEE', $date, $motif, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['vocabulaire_reference' => $reference, 'version' => $version, 'etat' => 'DEPRECIEE', 'idempotent' => false];
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
            return ['vocabulaire_reference' => $reference, 'version' => $version, 'etat' => 'RETIREE', 'idempotent' => true];
        }
        if (!in_array($etat, ['ACTIVE', 'DEPRECIEE'], true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une version ACTIVE ou DEPRECIEE se retire (état actuel `{$etat}`)");
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
            $this->inscrireCycle((int) $v['id'], 'RETIREE', $date, $motif, $producteur, $politiqueAdmin, $preuve, $correlation);

            return ['vocabulaire_reference' => $reference, 'version' => $version, 'etat' => 'RETIREE', 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — cycle de vie du terme

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function deprecierTerme(string $termeReference, array $dossier): array
    {
        $t = $this->ligneTerme($termeReference);
        if ($t === null) {
            return $this->refus('TERME_INCONNU', "terme `{$termeReference}` inconnu");
        }
        if ($t['date_fin'] !== null) {
            return ['reference' => $termeReference, 'idempotent' => true];
        }
        $etatVersion = $this->etatCourant((int) $t['vocabulaire_version_id']);
        if (!in_array($etatVersion, ['ACTIVE', 'DEPRECIEE'], true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "seul un terme d’une version ACTIVE ou DEPRECIEE se déprécie (état actuel `{$etatVersion}`)");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $remplacePar = $this->nullable($dossier['remplace_par_reference'] ?? null);
        if ($remplacePar !== null && $this->ligneTerme($remplacePar) === null) {
            return $this->refus('REMPLACANT_INCONNU', "le remplaçant `{$remplacePar}` est inconnu");
        }
        $dateFin = (string) ($dossier['date_fin'] ?? date('Y-m-d'));

        return $this->transaction(function () use ($termeReference, $dateFin, $remplacePar): array {
            $this->magasin->prepare('UPDATE terme SET date_fin = ?, remplace_par_reference = COALESCE(?, remplace_par_reference) WHERE reference = ?')
                ->execute([$dateFin, $remplacePar, $termeReference]);

            return ['reference' => $termeReference, 'date_fin' => $dateFin, 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function retirerTerme(string $termeReference, array $dossier): array
    {
        $t = $this->ligneTerme($termeReference);
        if ($t === null) {
            return $this->refus('TERME_INCONNU', "terme `{$termeReference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $stUsage = $this->magasin->prepare(
            "SELECT contrat_reference, politique_reference FROM terme_usage
             WHERE terme_reference = ? AND obligatoire = 1 AND date_fin IS NULL
             AND (contrat_reference IS NOT NULL OR politique_reference IS NOT NULL)"
        );
        $stUsage->execute([$termeReference]);
        $dependances = $stUsage->fetchAll();
        if ($dependances !== []) {
            return $this->refus('USAGE_ACTIF_DEPENDANT', 'un contrat ou une politique active dépend encore de ce terme');
        }
        $dateFin = (string) ($dossier['date_fin'] ?? date('Y-m-d'));

        return $this->transaction(function () use ($termeReference, $dateFin): array {
            $this->magasin->prepare('UPDATE terme SET date_fin = COALESCE(date_fin, ?) WHERE reference = ?')
                ->execute([$dateFin, $termeReference]);

            return ['reference' => $termeReference, 'date_fin' => $dateFin];
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

    /** @return array<string,mixed> */
    private function versionModifiableParId(int $id): array
    {
        $v = $this->ligneVersionParId($id);
        if ($v === null) {
            return $this->refus('VERSION_INCONNUE', 'version inconnue');
        }
        if ($this->etatCourant($id) !== 'BROUILLON') {
            return $this->refus('VERSION_IMMUABLE', 'seule une version BROUILLON accepte de nouvelles déclarations');
        }

        return $v;
    }

    /**
     * Remonte la chaîne des prédécesseurs d'une référence de terme via
     * `remplace_par_reference` (ligne A remplacée par B remplacée par C…),
     * la référence elle-même incluse. Une borne évite tout risque de boucle
     * infinie en cas de donnée corrompue.
     *
     * @return list<string>
     */
    private function lignageAscendant(string $reference): array
    {
        $chaine = [$reference];
        $courant = $reference;
        for ($i = 0; $i < 200; $i++) {
            $st = $this->magasin->prepare('SELECT reference FROM terme WHERE remplace_par_reference = ?');
            $st->execute([$courant]);
            $predecesseur = $st->fetchColumn();
            if ($predecesseur === false) {
                break;
            }
            $courant = (string) $predecesseur;
            $chaine[] = $courant;
        }

        return $chaine;
    }

    private function creeraitUnCycle(string $source, string $cible, string $typeRelation): bool
    {
        // A PLUS_LARGE_QUE B créerait un cycle si B (directement ou
        // transitivement) est déjà PLUS_LARGE_QUE A — et symétriquement
        // pour PLUS_ETROIT_QUE, son inverse exact.
        $inverse = $typeRelation === 'PLUS_LARGE_QUE' ? 'PLUS_ETROIT_QUE' : 'PLUS_LARGE_QUE';
        $visites = [];
        $pile = [$cible];
        while ($pile !== []) {
            $courant = array_pop($pile);
            if ($courant === $source) {
                return true;
            }
            if (isset($visites[$courant])) {
                continue;
            }
            $visites[$courant] = true;
            $st = $this->magasin->prepare(
                'SELECT terme_cible_reference FROM terme_relation WHERE terme_source_reference = ? AND type_relation = ?
                 UNION
                 SELECT terme_source_reference FROM terme_relation WHERE terme_cible_reference = ? AND type_relation = ?'
            );
            $st->execute([$courant, $typeRelation, $courant, $inverse]);
            foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $suivant) {
                $pile[] = $suivant;
            }
        }

        return false;
    }

    /** @return array<string,mixed>|null */
    private function ligneVocabulaire(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM vocabulaire WHERE reference = ?');
        $st->execute([$reference]);
        $v = $st->fetch();

        return $v === false ? null : $v;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersion(string $reference, string $version): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM vocabulaire_version WHERE vocabulaire_reference = ? AND version = ?');
        $st->execute([$reference, $version]);
        $v = $st->fetch();

        return $v === false ? null : $v;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersionParId(int $id): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM vocabulaire_version WHERE id = ?');
        $st->execute([$id]);
        $v = $st->fetch();

        return $v === false ? null : $v;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersionActive(string $reference, ?string $date = null): ?array
    {
        $date ??= date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT vvc.* FROM vocabulaire_version_cycle vvc
             JOIN vocabulaire_version vv ON vv.id = vvc.vocabulaire_version_id
             WHERE vv.vocabulaire_reference = ? AND vvc.date_effet <= ?
             AND vvc.id = (
                 SELECT id FROM vocabulaire_version_cycle
                 WHERE vocabulaire_version_id = vvc.vocabulaire_version_id AND date_effet <= ?
                 ORDER BY date_effet DESC, id DESC LIMIT 1
             )
             AND vvc.etat = ?'
        );
        $st->execute([$reference, $date, $date, 'ACTIVE']);
        $c = $st->fetch();

        return $c === false ? null : $c;
    }

    private function etatCourant(int $versionId): string
    {
        $st = $this->magasin->prepare(
            'SELECT etat FROM vocabulaire_version_cycle WHERE vocabulaire_version_id = ? ORDER BY date_effet DESC, id DESC LIMIT 1'
        );
        $st->execute([$versionId]);
        $etat = $st->fetchColumn();

        return $etat === false ? 'BROUILLON' : (string) $etat;
    }

    /** @return array<string,mixed>|null */
    private function derniereAnalyse(int $versionId): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM vocabulaire_compatibilite WHERE vocabulaire_version_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$versionId]);
        $a = $st->fetch();

        return $a === false ? null : $a;
    }

    /** @return list<array<string,mixed>> */
    private function resoudreCompatibiliteInterne(int $versionId): array
    {
        $st = $this->magasin->prepare('SELECT * FROM vocabulaire_compatibilite WHERE vocabulaire_version_id = ? ORDER BY id');
        $st->execute([$versionId]);

        return array_map(function (array $r): array {
            $r['divergences'] = json_decode((string) $r['divergences_json'], true) ?? [];

            return $r;
        }, $st->fetchAll());
    }

    /** @return array<string,mixed>|null */
    private function ligneTerme(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM terme WHERE reference = ?');
        $st->execute([$reference]);
        $t = $st->fetch();

        return $t === false ? null : $t;
    }

    /** @return list<array<string,mixed>> */
    private function termesVersion(int $versionId): array
    {
        $st = $this->magasin->prepare('SELECT * FROM terme WHERE vocabulaire_version_id = ? ORDER BY COALESCE(ordre_affichage, 999999), code');
        $st->execute([$versionId]);

        return array_map(fn (array $t): array => $this->projeterTerme($t), $st->fetchAll());
    }

    /** @param array<string,mixed> $t @return array<string,mixed> */
    private function projeterTerme(array $t): array
    {
        $stL = $this->magasin->prepare('SELECT * FROM terme_libelle WHERE terme_reference = ? ORDER BY locale');
        $stL->execute([$t['reference']]);
        $stA = $this->magasin->prepare('SELECT * FROM terme_alias WHERE terme_reference = ? ORDER BY id');
        $stA->execute([$t['reference']]);
        $stR = $this->magasin->prepare(
            'SELECT * FROM terme_relation WHERE terme_source_reference = ? OR terme_cible_reference = ? ORDER BY id'
        );
        $stR->execute([$t['reference'], $t['reference']]);
        $stM = $this->magasin->prepare('SELECT * FROM terme_mapping_externe WHERE terme_reference = ? ORDER BY id');
        $stM->execute([$t['reference']]);
        $stU = $this->magasin->prepare('SELECT * FROM terme_usage WHERE terme_reference = ? ORDER BY id');
        $stU->execute([$t['reference']]);

        return [
            'reference' => $t['reference'], 'vocabulaire_version_id' => (int) $t['vocabulaire_version_id'],
            'code' => $t['code'], 'definition' => $t['definition'], 'type_semantique' => $t['type_semantique'],
            'ordre_affichage' => $t['ordre_affichage'] === null ? null : (int) $t['ordre_affichage'],
            'date_debut' => $t['date_debut'], 'date_fin' => $t['date_fin'],
            'remplace_par_reference' => $t['remplace_par_reference'],
            'libelles' => $stL->fetchAll(), 'alias' => $stA->fetchAll(),
            'relations' => $stR->fetchAll(), 'mappings' => $stM->fetchAll(), 'usages' => $stU->fetchAll(),
        ];
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
        if (!in_array($etat, PolitiqueVocabulaire::ETATS_CYCLE, true)) {
            throw new ExceptionVocabulaire("état `{$etat}` hors liste close");
        }
        $this->magasin->prepare(
            'INSERT INTO vocabulaire_version_cycle (vocabulaire_version_id,etat,date_effet,motif,acteur_reference,politique_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?)'
        )->execute([$versionId, $etat, $date, $motif, $acteur, $politique, $preuve, $correlation, gmdate('c')]);
    }

    private function calculerEmpreinte(int $versionId): string
    {
        $termes = $this->termesVersion($versionId);
        $canonique = array_map(static fn (array $t): array => [
            'reference' => $t['reference'], 'code' => $t['code'], 'definition' => $t['definition'],
            'type_semantique' => $t['type_semantique'],
        ], $termes);
        usort($canonique, static fn (array $a, array $b): int => $a['reference'] <=> $b['reference']);

        return hash('sha256', json_encode($canonique, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string,mixed> $v @return array<string,mixed> */
    private function projeterVersion(array $v, bool $avecDetails = false): array
    {
        $projection = [
            'id' => (int) $v['id'], 'vocabulaire_reference' => $v['vocabulaire_reference'], 'version' => $v['version'],
            'date_effet_prevue' => $v['date_effet_prevue'], 'empreinte_contenu' => $v['empreinte_contenu'],
            'etat' => $this->etatCourant((int) $v['id']), 'cree_le' => $v['cree_le'],
        ];
        if ($avecDetails) {
            $projection['termes'] = $this->termesVersion((int) $v['id']);
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
