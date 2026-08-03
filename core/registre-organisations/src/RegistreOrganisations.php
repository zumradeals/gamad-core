<?php

declare(strict_types=1);

namespace Gamad\RegistreOrganisations;

use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;

/**
 * Registre opérationnel des organisations (CAP-CORE-002).
 *
 * Possède la fiche organisationnelle, sa structure, ses relations et ses
 * affiliations — jamais l'identité canonique elle-même (CAP-CORE-001),
 * jamais les mandats opposables (CAP-CORE-003), jamais les décisions
 * d'autorisation (CAP-CORE-004), jamais les dossiers métier d'un satellite.
 * Comme les autres registres persistants du Core, ce module ne décide rien
 * lui-même : chaque commande gouvernée exige `politique`, `producteur`,
 * `source` et `preuve` ; la permission vient de `CAP-CORE-004`, appliquée par
 * la couche applicative (`AccesOrganisations`), et la preuve d'exploitation
 * vient de `CAP-CORE-013`.
 *
 * `DIRIGEANT` et `REPRESENTANT` restent strictement descriptifs ici : seule
 * `ProjectionIdentites::verifierRepresentation()`, qui interroge
 * `CAP-CORE-003`, peut rendre une représentation opposable.
 */
final class RegistreOrganisations
{
    public const CAPACITE = 'CAP-CORE-002';

    private ProjectionIdentites $projection;

    public function __construct(
        private \PDO $index,
        private \PDO $registreIdentites,
        private \PDO $magasin,
        private ?Ctr01 $identites = null,
        private ?Ctr02 $autorites = null,
    ) {
        $this->identites ??= new Ctr01($index, $registreIdentites);
        SchemaOrganisations::migrer($this->magasin);
        $this->projection = new ProjectionIdentites($this->magasin, $this->identites, $this->autorites);
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudreOrganisation(string $reference, ?string $date = null): ?array
    {
        $o = $this->ligneOrganisation($reference);

        return $o === null ? null : $this->projeter($o, $date);
    }

    /** @return array<string,mixed>|null */
    public function resoudreOrganisationParIdentite(string $identite, ?string $date = null): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM organisation WHERE identite_reference = ?');
        $st->execute([$identite]);
        $o = $st->fetch();

        return $o === false ? null : $this->projeter($o, $date);
    }

    /**
     * @param array{etat?:string,type_organisation?:string,classification?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public function listerOrganisations(array $filtres = []): array
    {
        $sql = 'SELECT * FROM organisation';
        $conditions = [];
        $args = [];
        if (isset($filtres['type_organisation'])) {
            $conditions[] = 'type_organisation_reference = ?';
            $args[] = $filtres['type_organisation'];
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY reference';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        $lignes = array_map(fn (array $o): array => $this->projeter($o), $st->fetchAll());
        if (isset($filtres['etat'])) {
            $lignes = array_values(array_filter($lignes, static fn (array $l): bool => $l['etat'] === $filtres['etat']));
        }
        if (isset($filtres['classification'])) {
            $lignes = array_values(array_filter(
                $lignes,
                static fn (array $l): bool => ($l['revision']['classification_reference'] ?? null) === $filtres['classification'],
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
            'etat' => $cycle['etat_reference'],
            'date_effet' => $cycle['date_effet'],
            'motif' => $cycle['motif'],
            'acteur_reference' => $cycle['acteur_reference'],
        ];
    }

    /** @return array<string,mixed>|null */
    public function resoudreRevision(string $reference, ?string $date = null): ?array
    {
        return $this->derniereRevision($reference, $date);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreHistorique(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM organisation_cycle WHERE organisation_reference = ? ORDER BY date_effet, id'
        );
        $st->execute([$reference]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function resoudreIdentifiants(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM organisation_identifiant_externe WHERE organisation_reference = ? ORDER BY id'
        );
        $st->execute([$reference]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $i): bool => $i['date_debut'] <= $jour && ($i['date_fin'] === null || $i['date_fin'] >= $jour),
        ));
    }

    /** @return list<array<string,mixed>> */
    public function resoudreStructure(string $reference, ?string $date = null): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM organisation_unite WHERE organisation_reference = ? ORDER BY reference'
        );
        $st->execute([$reference]);

        return array_map(fn (array $u): array => $this->projeterUnite($u, $date), $st->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function resoudreUnite(string $reference, ?string $date = null): ?array
    {
        $u = $this->ligneUnite($reference);

        return $u === null ? null : $this->projeterUnite($u, $date);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreRelations(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM organisation_relation
             WHERE (organisation_source_reference = ? OR organisation_cible_reference = ?)
             AND date_debut <= ?
             ORDER BY date_debut, reference'
        );
        $st->execute([$reference, $reference, $jour]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $r): bool => $r['date_fin'] === null || $r['date_fin'] >= $jour,
        ));
    }

    /**
     * @param array{type?:string,etat?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public function resoudreAffiliationsOrganisation(string $reference, array $filtres = [], ?string $date = null): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM organisation_affiliation WHERE organisation_reference = ? ORDER BY reference'
        );
        $st->execute([$reference]);

        return $this->filtrerAffiliations($st->fetchAll(), $filtres, $date);
    }

    /**
     * @param array{type?:string,etat?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public function resoudreAffiliationsIdentite(string $identite, array $filtres = [], ?string $date = null): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM organisation_affiliation WHERE identite_reference = ? ORDER BY reference'
        );
        $st->execute([$identite]);

        return $this->filtrerAffiliations($st->fetchAll(), $filtres, $date);
    }

    /** @return array<string,mixed>|null */
    public function resoudreAffiliation(string $reference, ?string $date = null): ?array
    {
        $a = $this->ligneAffiliation($reference);

        return $a === null ? null : $this->projeterAffiliation($a, $date);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreFonctions(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM organisation_fonction_interne WHERE organisation_reference = ? ORDER BY reference'
        );
        $st->execute([$reference]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $f): bool => $f['date_debut'] <= $jour && ($f['date_fin'] === null || $f['date_fin'] >= $jour),
        ));
    }

    /** @return array<string,mixed> */
    public function verifierAppartenance(string $identite, string $organisation, ?string $type = null, ?string $date = null): array
    {
        return $this->projection->verifierAppartenance($identite, $organisation, $type, $date);
    }

    /** @return array<string,mixed> */
    public function verifierRepresentation(string $identite, string $organisation, ?string $action = null, ?string $date = null): array
    {
        return $this->projection->verifierRepresentation($identite, $organisation, $action, $date);
    }

    /**
     * Vérifie l'absence de cycle structurel réel dans les données actuelles :
     * hiérarchie d'unités et relations hiérarchiques interorganisationnelles.
     *
     * @return array{coherent:bool,divergences:list<string>}
     */
    public function diagnostiquerStructure(string $reference): array
    {
        $divergences = [];

        $unites = $this->magasin->prepare(
            'SELECT reference, unite_parente_reference FROM organisation_unite WHERE organisation_reference = ?'
        );
        $unites->execute([$reference]);
        $parents = [];
        foreach ($unites->fetchAll() as $u) {
            $parents[$u['reference']] = $u['unite_parente_reference'];
        }
        foreach (array_keys($parents) as $unite) {
            $courant = $parents[$unite];
            $vus = [(string) $unite => true];
            while ($courant !== null) {
                if (isset($vus[$courant])) {
                    $divergences[] = "cycle détecté dans la hiérarchie d'unités à partir de {$unite}";

                    break;
                }
                $vus[$courant] = true;
                $courant = $parents[$courant] ?? null;
            }
        }

        $relations = $this->magasin->query(
            "SELECT organisation_source_reference, organisation_cible_reference
             FROM organisation_relation WHERE type_relation_reference IN ('PARENTE_DE','FILIALE_DE')"
        )->fetchAll();
        $graphe = [];
        foreach ($relations as $r) {
            $graphe[$r['organisation_source_reference']][] = $r['organisation_cible_reference'];
        }
        if (isset($graphe[$reference])) {
            $vus = [];
            $pile = $graphe[$reference];
            while ($pile !== []) {
                $noeud = array_pop($pile);
                if ($noeud === $reference) {
                    $divergences[] = 'cycle détecté dans les relations hiérarchiques interorganisationnelles';

                    break;
                }
                if (isset($vus[$noeud])) {
                    continue;
                }
                $vus[$noeud] = true;
                foreach ($graphe[$noeud] ?? [] as $suivant) {
                    $pile[] = $suivant;
                }
            }
        }

        return ['coherent' => $divergences === [], 'divergences' => $divergences];
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — organisation

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function inscrireOrganisation(array $dossier): array
    {
        foreach (['identite_reference', 'type_organisation_reference', 'proprietaire_reference', 'source', 'denomination_officielle', 'classification_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $identite = trim((string) $dossier['identite_reference']);
        $type = (string) $dossier['type_organisation_reference'];
        $classification = (string) $dossier['classification_reference'];

        if (!in_array($type, PolitiqueOrganisations::TYPES_ORGANISATION, true)) {
            return $this->refus('TYPE_ORGANISATION_INCONNU', 'type_organisation_reference hors liste close');
        }
        if (!in_array($classification, PolitiqueOrganisations::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        $forme = $this->nullable($dossier['forme_reference'] ?? null);
        if ($forme !== null && !in_array($forme, PolitiqueOrganisations::FORMES_ORGANISATION, true)) {
            return $this->refus('FORME_INCONNUE', 'forme_reference hors liste close');
        }
        if ($this->ligneOrganisationParIdentite($identite) !== null) {
            return $this->refus('IDENTITE_DEJA_LIEE', "l'identité `{$identite}` porte déjà une fiche organisationnelle");
        }
        $identiteResolue = $this->identites->resoudreIdentite($identite);
        if ($identiteResolue === null) {
            return $this->refus('IDENTITE_INCONNUE', "l'identité canonique `{$identite}` n'existe pas");
        }
        if (($identiteResolue['type'] ?? null) !== 'organisation') {
            return $this->refus('IDENTITE_TYPE_INVALIDE', "l'identité `{$identite}` n'est pas de type organisation");
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }

        $personnaliteJuridique = (bool) ($dossier['personnalite_juridique'] ?? false);
        $proprietaire = trim((string) $dossier['proprietaire_reference']);
        $source = (string) $dossier['source'];
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $denomination = trim((string) $dossier['denomination_officielle']);
        $nomCourt = $this->nullable($dossier['nom_court'] ?? null);
        $nomCommercial = $this->nullable($dossier['nom_commercial'] ?? null);
        $description = $this->nullable($dossier['description'] ?? null);
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $identite, $type, $personnaliteJuridique, $proprietaire, $source, $producteur,
            $politique, $preuve, $denomination, $nomCourt, $nomCommercial, $description,
            $forme, $classification, $date, $correlation,
        ): array {
            $reference = $this->allouerReference('organisation');
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO organisation
                 (reference,identite_reference,type_organisation_reference,personnalite_juridique,
                  proprietaire_reference,source_reference,politique_inscription_reference,
                  preuve_reference,cree_par_reference,cree_le,modifie_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $identite, $type, $personnaliteJuridique ? 1 : 0, $proprietaire,
                $source, $politique, $preuve, $producteur, $maintenant, $maintenant,
            ]);
            $this->inscrireRevision(
                $reference, $denomination, $nomCourt, $nomCommercial, $description,
                $forme, $classification, $date, $producteur, $source, $preuve, $correlation,
            );
            $this->inscrireCycle($reference, 'PREPARATION', $date, null, $producteur, $politique, $preuve, $correlation);

            return [
                'reference' => $reference,
                'identite_reference' => $identite,
                'etat' => 'PREPARATION',
                'type_organisation_reference' => $type,
            ];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function modifierOrganisation(string $reference, array $dossier): array
    {
        $organisation = $this->ligneOrganisation($reference);
        if ($organisation === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $derniere = $this->derniereRevision($reference);
        $classification = (string) ($dossier['classification_reference'] ?? $derniere['classification_reference'] ?? '');
        if (!in_array($classification, PolitiqueOrganisations::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        $denomination = trim((string) ($dossier['denomination_officielle'] ?? $derniere['denomination_officielle'] ?? ''));
        if ($denomination === '') {
            return $this->refus('DENOMINATION_ABSENTE', 'denomination_officielle absente');
        }
        $forme = $this->nullable($dossier['forme_reference'] ?? $derniere['forme_reference'] ?? null);
        if ($forme !== null && !in_array($forme, PolitiqueOrganisations::FORMES_ORGANISATION, true)) {
            return $this->refus('FORME_INCONNUE', 'forme_reference hors liste close');
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }
        $nomCourt = $this->nullable($dossier['nom_court'] ?? $derniere['nom_court'] ?? null);
        $nomCommercial = $this->nullable($dossier['nom_commercial'] ?? $derniere['nom_commercial'] ?? null);
        $description = $this->nullable($dossier['description'] ?? $derniere['description'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $denomination, $nomCourt, $nomCommercial, $description,
            $forme, $classification, $date, $producteur, $source, $preuve, $correlation,
        ): array {
            $numero = $this->inscrireRevision(
                $reference, $denomination, $nomCourt, $nomCommercial, $description,
                $forme, $classification, $date, $producteur, $source, $preuve, $correlation,
            );
            $this->toucher($reference);

            return ['reference' => $reference, 'numero_revision' => $numero, 'denomination_officielle' => $denomination];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function activerOrganisation(string $reference, array $dossier): array
    {
        $organisation = $this->ligneOrganisation($reference);
        if ($organisation === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $producteur = (string) $dossier['producteur'];
        if ($producteur === $reference) {
            return $this->refus('AUTO_ACTIVATION_INTERDITE', 'une organisation ne peut jamais s’auto-activer');
        }
        if ($this->derniereRevision($reference) === null) {
            return $this->refus('REVISION_ABSENTE', 'aucune révision descriptive n’existe encore');
        }
        if (trim((string) $organisation['source_reference']) === '') {
            return $this->refus('SOURCE_ABSENTE', 'aucune source déclarée');
        }

        $etat = $this->etatActuel($reference);
        if ($etat === 'ACTIVE') {
            return ['reference' => $reference, 'etat' => 'ACTIVE', 'idempotent' => true];
        }
        if (!in_array($etat, PolitiqueOrganisations::ETATS_ACTIVABLES, true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "une organisation `{$etat}` ne s'active pas directement");
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $date, $motif, $producteur, $politique, $preuve, $correlation): array {
            $this->inscrireCycle($reference, 'ACTIVE', $date, $motif, $producteur, $politique, $preuve, $correlation);
            $this->toucher($reference);

            return ['reference' => $reference, 'etat' => 'ACTIVE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function suspendreOrganisation(string $reference, array $dossier): array
    {
        return $this->transitionCycle($reference, 'SUSPENDUE', ['ACTIVE'], $dossier);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function dissoudreOrganisation(string $reference, array $dossier): array
    {
        return $this->transitionCycle($reference, 'DISSOUTE', ['ACTIVE', 'SUSPENDUE'], $dossier);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function retirerOrganisation(string $reference, array $dossier): array
    {
        $organisation = $this->ligneOrganisation($reference);
        if ($organisation === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $motif = $this->nullable($dossier['motif'] ?? null);
        if ($motif === null) {
            return $this->refus('MOTIF_ABSENT', 'un retrait exige un motif explicite');
        }
        $etat = $this->etatActuel($reference);
        if ($etat === 'RETIREE') {
            return ['reference' => $reference, 'etat' => 'RETIREE', 'idempotent' => true];
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $date, $motif, $producteur, $politique, $preuve, $correlation): array {
            $this->inscrireCycle($reference, 'RETIREE', $date, $motif, $producteur, $politique, $preuve, $correlation);
            $this->toucher($reference);

            return ['reference' => $reference, 'etat' => 'RETIREE', 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — identifiants externes

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerIdentifiantExterne(string $reference, array $dossier): array
    {
        if ($this->ligneOrganisation($reference) === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['systeme_reference', 'type_identifiant_reference', 'valeur_normalisee'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $type = (string) $dossier['type_identifiant_reference'];
        if (!in_array($type, PolitiqueOrganisations::TYPES_IDENTIFIANT_EXTERNE, true)) {
            return $this->refus('TYPE_IDENTIFIANT_INCONNU', 'type_identifiant_reference hors liste close');
        }
        $systeme = trim((string) $dossier['systeme_reference']);
        $valeur = trim((string) $dossier['valeur_normalisee']);
        $st = $this->magasin->prepare(
            'SELECT 1 FROM organisation_identifiant_externe
             WHERE systeme_reference = ? AND type_identifiant_reference = ? AND valeur_normalisee = ?'
        );
        $st->execute([$systeme, $type, $valeur]);
        if ($st->fetchColumn() !== false) {
            return $this->refus('IDENTIFIANT_DEJA_DECLARE', 'ce triplet système/type/valeur est déjà déclaré');
        }
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $verifie = (bool) ($dossier['verifie'] ?? false);
        $valeurAffichage = $this->nullable($dossier['valeur_affichage'] ?? null);
        $realm = $this->nullable($dossier['pays_ou_realm_reference'] ?? null);
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];

        return $this->transaction(function () use (
            $reference, $systeme, $type, $valeur, $valeurAffichage, $realm, $date, $verifie, $source, $preuve,
        ): array {
            $this->magasin->prepare(
                'INSERT INTO organisation_identifiant_externe
                 (organisation_reference,systeme_reference,type_identifiant_reference,valeur_normalisee,
                  valeur_affichage,pays_ou_realm_reference,date_debut,date_fin,verifie,source_reference,
                  preuve_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,NULL,?,?,?,?)'
            )->execute([
                $reference, $systeme, $type, $valeur, $valeurAffichage, $realm, $date,
                $verifie ? 1 : 0, $source, $preuve, gmdate('c'),
            ]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'organisation_reference' => $reference, 'valeur_normalisee' => $valeur, 'verifie' => $verifie];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerIdentifiantExterne(int $id, array $dossier): array
    {
        $st = $this->magasin->prepare('SELECT * FROM organisation_identifiant_externe WHERE id = ?');
        $st->execute([$id]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return $this->refus('IDENTIFIANT_INCONNU', "identifiant `{$id}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($ligne['date_fin'] !== null) {
            return ['id' => $id, 'idempotent' => true];
        }
        $date = (string) ($dossier['date_fin'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_fin doit suivre YYYY-MM-DD');
        }

        return $this->transaction(function () use ($id, $date): array {
            $this->magasin->prepare('UPDATE organisation_identifiant_externe SET date_fin = ? WHERE id = ?')->execute([$date, $id]);

            return ['id' => $id, 'date_fin' => $date, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — unités

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function creerUnite(string $reference, array $dossier): array
    {
        $organisation = $this->ligneOrganisation($reference);
        if ($organisation === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$reference}` inconnue");
        }
        if ($this->etatActuel($reference) !== 'ACTIVE') {
            return $this->refus('ORGANISATION_NON_ACTIVE', 'seule une organisation ACTIVE reçoit de nouvelles unités');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['type_unite_reference', 'nom', 'classification_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $type = (string) $dossier['type_unite_reference'];
        if (!in_array($type, PolitiqueOrganisations::TYPES_UNITE, true)) {
            return $this->refus('TYPE_UNITE_INCONNU', 'type_unite_reference hors liste close');
        }
        $classification = (string) $dossier['classification_reference'];
        if (!in_array($classification, PolitiqueOrganisations::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        $parente = $this->nullable($dossier['unite_parente_reference'] ?? null);
        if ($parente !== null) {
            $ligneParente = $this->ligneUnite($parente);
            if ($ligneParente === null || (string) $ligneParente['organisation_reference'] !== $reference) {
                return $this->refus('UNITE_PARENTE_INVALIDE', 'la parente doit appartenir à la même organisation');
            }
        }
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $nom = trim((string) $dossier['nom']);
        $codeInterne = $this->nullable($dossier['code_interne'] ?? null);
        $realm = $this->nullable($dossier['realm_reference'] ?? null);
        $source = (string) $dossier['source'];
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];

        return $this->transaction(function () use (
            $reference, $parente, $type, $nom, $codeInterne, $realm, $classification, $date, $source, $producteur, $preuve,
        ): array {
            $uniteRef = $this->allouerReference('unite');
            $this->magasin->prepare(
                'INSERT INTO organisation_unite
                 (reference,organisation_reference,unite_parente_reference,type_unite_reference,nom,
                  code_interne,realm_reference,classification_reference,date_debut,source_reference,
                  preuve_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $uniteRef, $reference, $parente, $type, $nom, $codeInterne, $realm,
                $classification, $date, $source, $preuve, gmdate('c'),
            ]);
            $this->inscrireCycleUnite($uniteRef, 'ACTIVE', $date, null, $producteur, $preuve, null);

            return ['reference' => $uniteRef, 'organisation_reference' => $reference, 'etat' => 'ACTIVE'];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function deplacerUnite(string $unite, array $dossier): array
    {
        $ligne = $this->ligneUnite($unite);
        if ($ligne === null) {
            return $this->refus('UNITE_INCONNUE', "unité `{$unite}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $nouvelleParente = $this->nullable($dossier['unite_parente_reference'] ?? null);
        if ($nouvelleParente !== null) {
            $ligneParente = $this->ligneUnite($nouvelleParente);
            if ($ligneParente === null || (string) $ligneParente['organisation_reference'] !== (string) $ligne['organisation_reference']) {
                return $this->refus('UNITE_PARENTE_INVALIDE', 'la parente doit appartenir à la même organisation');
            }
            $parents = $this->parentsUnites((string) $ligne['organisation_reference']);
            if (ValidateurStructure::uniteCreeraitCycle($unite, $nouvelleParente, $parents)) {
                return $this->refus('CYCLE_UNITE_DETECTE', 'ce déplacement créerait un cycle hiérarchique');
            }
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }
        $motif = $this->nullable($dossier['motif'] ?? null) ?? 'déplacement structurel';
        $ancienneParente = $ligne['unite_parente_reference'];
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);
        $etatActuel = $this->etatActuelUnite($unite) ?? 'ACTIVE';

        return $this->transaction(function () use (
            $unite, $nouvelleParente, $ancienneParente, $date, $motif, $producteur, $preuve, $correlation, $etatActuel,
        ): array {
            $this->magasin->prepare('UPDATE organisation_unite SET unite_parente_reference = ? WHERE reference = ?')
                ->execute([$nouvelleParente, $unite]);
            // La colonne de rattachement est mutable ; l'événement est
            // néanmoins journalisé en ajout seul dans le cycle, motif détaillé,
            // pour ne jamais effacer silencieusement la trace du déplacement.
            $this->inscrireCycleUnite(
                $unite, $etatActuel, $date,
                sprintf('%s (ancienne parente : %s ; nouvelle parente : %s)', $motif, $ancienneParente ?? '(aucune)', $nouvelleParente ?? '(aucune)'),
                $producteur, $preuve, $correlation,
            );

            return ['reference' => $unite, 'unite_parente_reference' => $nouvelleParente, 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerUnite(string $unite, array $dossier): array
    {
        $ligne = $this->ligneUnite($unite);
        if ($ligne === null) {
            return $this->refus('UNITE_INCONNUE', "unité `{$unite}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($this->etatActuelUnite($unite) === 'FERMEE') {
            return ['reference' => $unite, 'etat' => 'FERMEE', 'idempotent' => true];
        }
        $descendants = $this->descendantsActifs($unite);
        if ($descendants !== [] && !($dossier['confirmer_descendants_actifs'] ?? false)) {
            return $this->refus(
                'DESCENDANTS_ACTIFS',
                'des unités descendantes sont encore actives ; aucune fermeture en cascade automatique — '
                . 'les fermer explicitement ou confirmer avec confirmer_descendants_actifs',
            );
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($unite, $date, $motif, $producteur, $preuve, $correlation, $descendants): array {
            $this->inscrireCycleUnite($unite, 'FERMEE', $date, $motif, $producteur, $preuve, $correlation);

            return ['reference' => $unite, 'etat' => 'FERMEE', 'idempotent' => false, 'descendants_actifs_signales' => $descendants];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — relations interorganisationnelles

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerRelationOrganisationnelle(array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['organisation_source_reference', 'organisation_cible_reference', 'type_relation_reference', 'classification_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $source = trim((string) $dossier['organisation_source_reference']);
        $cible = trim((string) $dossier['organisation_cible_reference']);
        if ($source === $cible) {
            return $this->refus('AUTO_RELATION_INTERDITE', "une organisation n'entretient pas de relation avec elle-même");
        }
        if ($this->ligneOrganisation($source) === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$source}` inconnue");
        }
        if ($this->ligneOrganisation($cible) === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$cible}` inconnue");
        }
        $type = (string) $dossier['type_relation_reference'];
        if (!in_array($type, PolitiqueOrganisations::TYPES_RELATION, true)) {
            return $this->refus('TYPE_RELATION_INCONNU', 'type_relation_reference hors liste close');
        }
        $classification = (string) $dossier['classification_reference'];
        if (!in_array($classification, PolitiqueOrganisations::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        $pourcentage = $dossier['pourcentage'] ?? null;
        if ($pourcentage !== null) {
            $pourcentage = (float) $pourcentage;
            if ($pourcentage < 0.0 || $pourcentage > 100.0) {
                return $this->refus('POURCENTAGE_INVALIDE', 'pourcentage doit être compris entre 0 et 100');
            }
        }
        if (in_array($type, PolitiqueOrganisations::TYPES_RELATION_HIERARCHIQUE, true)) {
            $aretes = $this->aretesHierarchiques();
            if (ValidateurStructure::relationCreeraitCycle($source, $cible, $aretes)) {
                return $this->refus('CYCLE_HIERARCHIQUE_DETECTE', 'cette relation créerait un cycle hiérarchique');
            }
        }
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $sourceRef = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $producteur = (string) $dossier['producteur'];

        return $this->transaction(function () use (
            $source, $cible, $type, $date, $pourcentage, $classification, $sourceRef, $preuve, $producteur,
        ): array {
            $relationRef = $this->allouerReference('relation');
            $this->magasin->prepare(
                'INSERT INTO organisation_relation
                 (reference,organisation_source_reference,organisation_cible_reference,type_relation_reference,
                  date_debut,date_fin,pourcentage,classification_reference,source_reference,preuve_reference,
                  acteur_reference,cree_le)
                 VALUES(?,?,?,?,?,NULL,?,?,?,?,?,?)'
            )->execute([
                $relationRef, $source, $cible, $type, $date, $pourcentage,
                $classification, $sourceRef, $preuve, $producteur, gmdate('c'),
            ]);

            return ['reference' => $relationRef, 'organisation_source_reference' => $source, 'organisation_cible_reference' => $cible, 'type_relation_reference' => $type];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerRelationOrganisationnelle(string $relation, array $dossier): array
    {
        $st = $this->magasin->prepare('SELECT * FROM organisation_relation WHERE reference = ?');
        $st->execute([$relation]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return $this->refus('RELATION_INCONNUE', "relation `{$relation}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($ligne['date_fin'] !== null) {
            return ['reference' => $relation, 'idempotent' => true];
        }
        $date = (string) ($dossier['date_fin'] ?? date('Y-m-d'));
        if (!$this->dateValide($date) || $date < $ligne['date_debut']) {
            return $this->refus('DATE_INVALIDE', 'date_fin doit suivre YYYY-MM-DD et ne pas précéder le début');
        }

        return $this->transaction(function () use ($relation, $date): array {
            $this->magasin->prepare('UPDATE organisation_relation SET date_fin = ? WHERE reference = ?')->execute([$date, $relation]);

            return ['reference' => $relation, 'date_fin' => $date, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — affiliations

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function proposerAffiliation(array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['organisation_reference', 'identite_reference', 'type_affiliation_reference', 'niveau_assurance_reference', 'classification_reference', 'producteur_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $organisation = trim((string) $dossier['organisation_reference']);
        $identite = trim((string) $dossier['identite_reference']);
        $ligneOrg = $this->ligneOrganisation($organisation);
        if ($ligneOrg === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$organisation}` inconnue");
        }
        if ($this->identites->resoudreIdentite($identite) === null) {
            return $this->refus('IDENTITE_INCONNUE', "identité `{$identite}` inconnue");
        }
        $type = (string) $dossier['type_affiliation_reference'];
        if (!in_array($type, PolitiqueOrganisations::TYPES_AFFILIATION, true)) {
            return $this->refus('TYPE_AFFILIATION_INCONNU', 'type_affiliation_reference hors liste close');
        }
        $niveau = (string) $dossier['niveau_assurance_reference'];
        if (!in_array($niveau, PolitiqueOrganisations::NIVEAUX_ASSURANCE, true)) {
            return $this->refus('NIVEAU_ASSURANCE_INCONNU', 'niveau_assurance_reference hors liste close');
        }
        $classification = (string) $dossier['classification_reference'];
        if (!in_array($classification, PolitiqueOrganisations::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        $unite = $this->nullable($dossier['unite_reference'] ?? null);
        if ($unite !== null) {
            $ligneUnite = $this->ligneUnite($unite);
            if ($ligneUnite === null || (string) $ligneUnite['organisation_reference'] !== $organisation) {
                return $this->refus('UNITE_INVALIDE', "l'unité doit appartenir à l'organisation");
            }
        }
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $dateFinPrevue = $this->nullable($dossier['date_fin_prevue'] ?? null);
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $producteur = trim((string) $dossier['producteur_reference']);
        $acteur = (string) $dossier['producteur'];

        return $this->transaction(function () use (
            $organisation, $identite, $unite, $type, $date, $dateFinPrevue, $niveau,
            $classification, $source, $preuve, $producteur, $acteur,
        ): array {
            $affiliationRef = $this->allouerReference('affiliation');
            $this->magasin->prepare(
                'INSERT INTO organisation_affiliation
                 (reference,organisation_reference,identite_reference,unite_reference,type_affiliation_reference,
                  date_debut,date_fin_prevue,niveau_assurance_reference,classification_reference,source_reference,
                  preuve_reference,producteur_reference,acteur_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $affiliationRef, $organisation, $identite, $unite, $type, $date, $dateFinPrevue,
                $niveau, $classification, $source, $preuve, $producteur, $acteur, gmdate('c'),
            ]);
            $this->inscrireCycleAffiliation($affiliationRef, 'PROPOSEE', $date, null, $acteur, PolitiqueOrganisations::POLITIQUE, $preuve, null);

            return [
                'reference' => $affiliationRef, 'organisation_reference' => $organisation,
                'identite_reference' => $identite, 'type_affiliation_reference' => $type, 'etat' => 'PROPOSEE',
            ];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function activerAffiliation(string $affiliation, array $dossier): array
    {
        $ligne = $this->ligneAffiliation($affiliation);
        if ($ligne === null) {
            return $this->refus('AFFILIATION_INCONNUE', "affiliation `{$affiliation}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $etat = $this->etatActuelAffiliation($affiliation);
        if ($etat === 'ACTIVE') {
            return ['reference' => $affiliation, 'etat' => 'ACTIVE', 'idempotent' => true];
        }
        if ($etat !== 'PROPOSEE') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une affiliation PROPOSEE s'active (état actuel `{$etat}`)");
        }
        if ($this->etatActuel((string) $ligne['organisation_reference']) !== 'ACTIVE') {
            return $this->refus('ORGANISATION_NON_ACTIVE', "l'organisation doit être ACTIVE pour activer une affiliation");
        }
        $identiteEtat = $this->identites->resoudreIdentite((string) $ligne['identite_reference']);
        if ($identiteEtat === null || !in_array($identiteEtat['etat'], ['ACTIVE', 'VERIFIEE'], true)) {
            return $this->refus('IDENTITE_NON_ACTIVE', "l'identité doit être active pour activer une affiliation");
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($affiliation, $date, $motif, $producteur, $politique, $preuve, $correlation): array {
            $this->inscrireCycleAffiliation($affiliation, 'ACTIVE', $date, $motif, $producteur, $politique, $preuve, $correlation);

            return ['reference' => $affiliation, 'etat' => 'ACTIVE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function suspendreAffiliation(string $affiliation, array $dossier): array
    {
        $ligne = $this->ligneAffiliation($affiliation);
        if ($ligne === null) {
            return $this->refus('AFFILIATION_INCONNUE', "affiliation `{$affiliation}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $etat = $this->etatActuelAffiliation($affiliation);
        if ($etat === 'SUSPENDUE') {
            return ['reference' => $affiliation, 'etat' => 'SUSPENDUE', 'idempotent' => true];
        }
        if ($etat !== 'ACTIVE') {
            return $this->refus('ETAT_INCOMPATIBLE', "seule une affiliation ACTIVE se suspend (état actuel `{$etat}`)");
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($affiliation, $date, $motif, $producteur, $politique, $preuve, $correlation): array {
            $this->inscrireCycleAffiliation($affiliation, 'SUSPENDUE', $date, $motif, $producteur, $politique, $preuve, $correlation);

            return ['reference' => $affiliation, 'etat' => 'SUSPENDUE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerAffiliation(string $affiliation, array $dossier): array
    {
        $ligne = $this->ligneAffiliation($affiliation);
        if ($ligne === null) {
            return $this->refus('AFFILIATION_INCONNUE', "affiliation `{$affiliation}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $etat = $this->etatActuelAffiliation($affiliation);
        if (in_array($etat, ['CLOSE', 'REJETEE'], true)) {
            return ['reference' => $affiliation, 'etat' => $etat, 'idempotent' => true];
        }
        if (!in_array($etat, ['PROPOSEE', 'ACTIVE', 'SUSPENDUE'], true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "affiliation `{$etat}` déjà terminale");
        }
        $cible = $etat === 'PROPOSEE' ? 'REJETEE' : 'CLOSE';
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($affiliation, $cible, $date, $motif, $producteur, $politique, $preuve, $correlation): array {
            $this->inscrireCycleAffiliation($affiliation, $cible, $date, $motif, $producteur, $politique, $preuve, $correlation);

            return ['reference' => $affiliation, 'etat' => $cible, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — fonctions internes

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function creerFonctionInterne(string $reference, array $dossier): array
    {
        $organisation = $this->ligneOrganisation($reference);
        if ($organisation === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$reference}` inconnue");
        }
        if ($this->etatActuel($reference) !== 'ACTIVE') {
            return $this->refus('ORGANISATION_NON_ACTIVE', 'seule une organisation ACTIVE reçoit une fonction interne');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['type_fonction_reference', 'libelle'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $type = (string) $dossier['type_fonction_reference'];
        if (!in_array($type, PolitiqueOrganisations::TYPES_FONCTION, true)) {
            return $this->refus('TYPE_FONCTION_INCONNU', 'type_fonction_reference hors liste close');
        }
        $unite = $this->nullable($dossier['unite_reference'] ?? null);
        if ($unite !== null) {
            $ligneUnite = $this->ligneUnite($unite);
            if ($ligneUnite === null || (string) $ligneUnite['organisation_reference'] !== $reference) {
                return $this->refus('UNITE_INVALIDE', "l'unité doit appartenir à l'organisation");
            }
        }
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $libelle = trim((string) $dossier['libelle']);
        $mandatFonction = $this->nullable($dossier['mandat_fonction_reference'] ?? null);
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];

        return $this->transaction(function () use (
            $reference, $unite, $type, $libelle, $mandatFonction, $date, $source, $preuve,
        ): array {
            $fonctionRef = $this->allouerReference('fonction');
            $this->magasin->prepare(
                'INSERT INTO organisation_fonction_interne
                 (reference,organisation_reference,unite_reference,type_fonction_reference,libelle,
                  mandat_fonction_reference,date_debut,date_fin,source_reference,preuve_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,NULL,?,?,?)'
            )->execute([
                $fonctionRef, $reference, $unite, $type, $libelle, $mandatFonction, $date, $source, $preuve, gmdate('c'),
            ]);

            return ['reference' => $fonctionRef, 'organisation_reference' => $reference, 'libelle' => $libelle];
        });
    }

    // ------------------------------------------------------------------
    // Internes

    private function transitionCycle(string $reference, string $cible, array $depuis, array $dossier): array
    {
        $organisation = $this->ligneOrganisation($reference);
        if ($organisation === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$reference}` inconnue");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $etat = $this->etatActuel($reference);
        if ($etat === $cible) {
            return ['reference' => $reference, 'etat' => $cible, 'idempotent' => true];
        }
        if (!in_array($etat, $depuis, true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "transition vers `{$cible}` impossible depuis `{$etat}`");
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $cible, $date, $motif, $producteur, $politique, $preuve, $correlation): array {
            $this->inscrireCycle($reference, $cible, $date, $motif, $producteur, $politique, $preuve, $correlation);
            $this->toucher($reference);

            return ['reference' => $reference, 'etat' => $cible, 'idempotent' => false];
        });
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

    /** @return array<string,mixed>|null */
    private function ligneOrganisation(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM organisation WHERE reference = ?');
        $st->execute([$reference]);
        $o = $st->fetch();

        return $o === false ? null : $o;
    }

    /** @return array<string,mixed>|null */
    private function ligneOrganisationParIdentite(string $identite): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM organisation WHERE identite_reference = ?');
        $st->execute([$identite]);
        $o = $st->fetch();

        return $o === false ? null : $o;
    }

    /** @return array<string,mixed>|null */
    private function ligneUnite(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM organisation_unite WHERE reference = ?');
        $st->execute([$reference]);
        $u = $st->fetch();

        return $u === false ? null : $u;
    }

    /** @return array<string,mixed>|null */
    private function ligneAffiliation(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM organisation_affiliation WHERE reference = ?');
        $st->execute([$reference]);
        $a = $st->fetch();

        return $a === false ? null : $a;
    }

    /** @return array<string,mixed>|null */
    private function dernierCycle(string $reference, ?string $date = null): ?array
    {
        $sql = 'SELECT * FROM organisation_cycle WHERE organisation_reference = ?';
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

    private function etatActuel(string $reference, ?string $date = null): ?string
    {
        return $this->dernierCycle($reference, $date)['etat_reference'] ?? null;
    }

    /** @return array<string,mixed>|null */
    private function derniereRevision(string $reference, ?string $date = null): ?array
    {
        $sql = 'SELECT * FROM organisation_revision WHERE organisation_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY numero_revision DESC LIMIT 1';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $r = $st->fetch();

        return $r === false ? null : $r;
    }

    private function etatActuelUnite(string $reference, ?string $date = null): ?string
    {
        $sql = 'SELECT etat_reference FROM organisation_unite_cycle WHERE unite_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $etat = $st->fetchColumn();

        return $etat === false ? null : (string) $etat;
    }

    private function etatActuelAffiliation(string $reference, ?string $date = null): ?string
    {
        $sql = 'SELECT etat_reference FROM organisation_affiliation_cycle WHERE affiliation_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $etat = $st->fetchColumn();

        return $etat === false ? null : (string) $etat;
    }

    /** @return array<string,?string> */
    private function parentsUnites(string $organisation): array
    {
        $st = $this->magasin->prepare(
            'SELECT reference, unite_parente_reference FROM organisation_unite WHERE organisation_reference = ?'
        );
        $st->execute([$organisation]);
        $parents = [];
        foreach ($st->fetchAll() as $u) {
            $parents[(string) $u['reference']] = $u['unite_parente_reference'] === null ? null : (string) $u['unite_parente_reference'];
        }

        return $parents;
    }

    /** @return list<string> */
    private function descendantsActifs(string $unite): array
    {
        $st = $this->magasin->prepare('SELECT reference FROM organisation_unite WHERE unite_parente_reference = ?');
        $st->execute([$unite]);
        $descendants = [];
        foreach ($st->fetchAll() as $u) {
            $ref = (string) $u['reference'];
            if ($this->etatActuelUnite($ref) !== 'FERMEE') {
                $descendants[] = $ref;
            }
        }

        return $descendants;
    }

    /** @return list<array{0:string,1:string}> */
    private function aretesHierarchiques(): array
    {
        $st = $this->magasin->query(
            "SELECT organisation_source_reference, organisation_cible_reference
             FROM organisation_relation
             WHERE type_relation_reference IN ('PARENTE_DE','FILIALE_DE') AND date_fin IS NULL"
        );

        return array_map(
            static fn (array $r): array => [(string) $r['organisation_source_reference'], (string) $r['organisation_cible_reference']],
            $st->fetchAll(),
        );
    }

    private function inscrireRevision(
        string $reference,
        string $denomination,
        ?string $nomCourt,
        ?string $nomCommercial,
        ?string $description,
        ?string $forme,
        string $classification,
        string $date,
        string $acteur,
        string $source,
        string $preuve,
        ?string $correlation,
    ): int {
        $st = $this->magasin->prepare('SELECT COALESCE(MAX(numero_revision),0) FROM organisation_revision WHERE organisation_reference = ?');
        $st->execute([$reference]);
        $numero = ((int) $st->fetchColumn()) + 1;
        $this->magasin->prepare(
            'INSERT INTO organisation_revision
             (organisation_reference,numero_revision,denomination_officielle,nom_court,nom_commercial,
              description,forme_reference,classification_reference,date_effet,acteur_reference,
              source_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference, $numero, $denomination, $nomCourt, $nomCommercial, $description,
            $forme, $classification, $date, $acteur, $source, $preuve, $correlation, gmdate('c'),
        ]);

        return $numero;
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
        if (!in_array($etat, PolitiqueOrganisations::ETATS_CYCLE, true)) {
            throw new ExceptionOrganisation("état `{$etat}` hors liste close");
        }
        $this->magasin->prepare(
            'INSERT INTO organisation_cycle
             (organisation_reference,etat_reference,date_effet,motif,acteur_reference,politique_reference,
              preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?)'
        )->execute([$reference, $etat, $date, $motif, $acteur, $politique, $preuve, $correlation, gmdate('c')]);
    }

    private function inscrireCycleUnite(
        string $reference,
        string $etat,
        string $date,
        ?string $motif,
        string $acteur,
        string $preuve,
        ?string $correlation,
    ): void {
        if (!in_array($etat, PolitiqueOrganisations::ETATS_UNITE, true)) {
            throw new ExceptionOrganisation("état `{$etat}` hors liste close");
        }
        $this->magasin->prepare(
            'INSERT INTO organisation_unite_cycle
             (unite_reference,etat_reference,date_effet,motif,acteur_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?)'
        )->execute([$reference, $etat, $date, $motif, $acteur, $preuve, $correlation, gmdate('c')]);
    }

    private function inscrireCycleAffiliation(
        string $reference,
        string $etat,
        string $date,
        ?string $motif,
        string $acteur,
        string $politique,
        string $preuve,
        ?string $correlation,
    ): void {
        if (!in_array($etat, PolitiqueOrganisations::ETATS_AFFILIATION, true)) {
            throw new ExceptionOrganisation("état `{$etat}` hors liste close");
        }
        $this->magasin->prepare(
            'INSERT INTO organisation_affiliation_cycle
             (affiliation_reference,etat_reference,date_effet,motif,acteur_reference,politique_reference,
              preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?)'
        )->execute([$reference, $etat, $date, $motif, $acteur, $politique, $preuve, $correlation, gmdate('c')]);
    }

    private function allouerReference(string $type): string
    {
        $prefixe = PolitiqueOrganisations::PREFIXE[$type] ?? throw new ExceptionOrganisation("type de référence `{$type}` inconnu");
        $this->magasin->prepare(
            'INSERT INTO compteur_reference_organisation(type,dernier) VALUES(?,0) ON CONFLICT(type) DO NOTHING'
        )->execute([$type]);
        $sql = 'SELECT dernier FROM compteur_reference_organisation WHERE type = ?';
        if ((string) $this->magasin->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $sql .= ' FOR UPDATE';
        }
        $st = $this->magasin->prepare($sql);
        $st->execute([$type]);
        $numero = ((int) $st->fetchColumn()) + 1;
        $this->magasin->prepare('UPDATE compteur_reference_organisation SET dernier = ? WHERE type = ?')
            ->execute([$numero, $type]);

        return sprintf('%s-%06d', $prefixe, $numero);
    }

    private function toucher(string $reference): void
    {
        $this->magasin->prepare('UPDATE organisation SET modifie_le = ? WHERE reference = ?')
            ->execute([gmdate('c'), $reference]);
    }

    /** @param array<string,mixed> $o @return array<string,mixed> */
    private function projeter(array $o, ?string $date = null): array
    {
        $cycle = $this->dernierCycle((string) $o['reference'], $date);
        $revision = $this->derniereRevision((string) $o['reference'], $date);

        return [
            'reference' => $o['reference'],
            'identite_reference' => $o['identite_reference'],
            'type_organisation_reference' => $o['type_organisation_reference'],
            'personnalite_juridique' => (bool) $o['personnalite_juridique'],
            'proprietaire_reference' => $o['proprietaire_reference'],
            'source_reference' => $o['source_reference'],
            'etat' => $cycle['etat_reference'] ?? 'PREPARATION',
            'depuis' => $cycle['date_effet'] ?? null,
            'revision' => $revision,
            'cree_le' => $o['cree_le'],
            'modifie_le' => $o['modifie_le'],
        ];
    }

    /** @param array<string,mixed> $u @return array<string,mixed> */
    private function projeterUnite(array $u, ?string $date = null): array
    {
        return [
            'reference' => $u['reference'],
            'organisation_reference' => $u['organisation_reference'],
            'unite_parente_reference' => $u['unite_parente_reference'],
            'type_unite_reference' => $u['type_unite_reference'],
            'nom' => $u['nom'],
            'code_interne' => $u['code_interne'],
            'realm_reference' => $u['realm_reference'],
            'classification_reference' => $u['classification_reference'],
            'date_debut' => $u['date_debut'],
            'etat' => $this->etatActuelUnite((string) $u['reference'], $date) ?? 'ACTIVE',
        ];
    }

    /** @param array<string,mixed> $a @return array<string,mixed> */
    private function projeterAffiliation(array $a, ?string $date = null): array
    {
        return [
            'reference' => $a['reference'],
            'organisation_reference' => $a['organisation_reference'],
            'identite_reference' => $a['identite_reference'],
            'unite_reference' => $a['unite_reference'],
            'type_affiliation_reference' => $a['type_affiliation_reference'],
            'date_debut' => $a['date_debut'],
            'date_fin_prevue' => $a['date_fin_prevue'],
            'niveau_assurance_reference' => $a['niveau_assurance_reference'],
            'classification_reference' => $a['classification_reference'],
            'etat' => $this->etatActuelAffiliation((string) $a['reference'], $date) ?? 'PROPOSEE',
        ];
    }

    /**
     * @param list<array<string,mixed>> $lignes
     * @param array{type?:string,etat?:string} $filtres
     * @return list<array<string,mixed>>
     */
    private function filtrerAffiliations(array $lignes, array $filtres, ?string $date): array
    {
        $projetees = array_map(fn (array $a): array => $this->projeterAffiliation($a, $date), $lignes);
        if (isset($filtres['type'])) {
            $projetees = array_values(array_filter($projetees, static fn (array $a): bool => $a['type_affiliation_reference'] === $filtres['type']));
        }
        if (isset($filtres['etat'])) {
            $projetees = array_values(array_filter($projetees, static fn (array $a): bool => $a['etat'] === $filtres['etat']));
        }

        return $projetees;
    }

    private function nullable(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }

    private function dateValide(string $date): bool
    {
        $valeur = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $valeur !== false && $valeur->format('Y-m-d') === $date;
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
