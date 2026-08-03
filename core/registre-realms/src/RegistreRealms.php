<?php

declare(strict_types=1);

namespace Gamad\RegistreRealms;

use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistreProduits\RegistreProduits;

/**
 * Registre opérationnel des realms (CAP-CORE-012).
 *
 * Possède la fiche de realm, sa hiérarchie, ses périmètres et ses
 * rattachements — jamais l'identité canonique elle-même (CAP-CORE-001),
 * jamais le dossier d'organisation (CAP-CORE-002), jamais les données de
 * produit (CAP-CORE-011), jamais les contrats (CAP-CORE-009), jamais les
 * décisions d'autorisation (CAP-CORE-004), jamais les dossiers métier d'un
 * satellite. Comme les autres registres persistants du Core, ce module ne
 * décide rien lui-même : chaque commande gouvernée exige `politique`,
 * `producteur`, `source` et `preuve` ; la permission vient de `CAP-CORE-004`,
 * appliquée par la couche applicative (`AccesRealms`), et la preuve
 * d'exploitation vient de `CAP-CORE-013`.
 *
 * `RESPONSABLE` et `REGULATEUR` restent des rôles descriptifs de
 * rattachement : ils ne donnent jamais automatiquement une autorisation ni
 * un mandat de représentation, qui reste vérifié par `CAP-CORE-003` via
 * `$organisations` (fiche §19, §35, §44).
 *
 * `$organisations`, `$produits` et `$contrats` sont optionnels : lorsqu'ils
 * sont absents, toute commande qui en dépend est refusée avec le motif
 * `DEPENDANCE_INDISPONIBLE` plutôt que de supposer un rattachement ou un état
 * (fiche §61) — jamais de repli implicite vers une portée globale.
 */
final class RegistreRealms
{
    public const CAPACITE = 'CAP-CORE-012';

    public function __construct(
        private \PDO $index,
        private \PDO $registreIdentites,
        private \PDO $magasin,
        private ?Ctr01 $identites = null,
        private ?RegistreOrganisations $organisations = null,
        private ?RegistreProduits $produits = null,
        private ?RegistreContrats $contrats = null,
    ) {
        $this->identites ??= new Ctr01($index, $registreIdentites);
        SchemaRealms::migrer($this->magasin);
    }

    // ------------------------------------------------------------------
    // Lectures — realm

    /** @return array<string,mixed>|null */
    public function resoudreRealm(string $reference, ?string $date = null): ?array
    {
        $r = $this->ligneRealm($reference);

        return $r === null ? null : $this->projeter($r, $date);
    }

    /** @return array<string,mixed>|null */
    public function resoudreRealmParIdentite(string $identite, ?string $date = null): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm WHERE identite_reference = ?');
        $st->execute([$identite]);
        $r = $st->fetch();

        return $r === false ? null : $this->projeter($r, $date);
    }

    /** @return array<string,mixed>|null */
    public function resoudreRealmParCode(string $code, ?string $date = null): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm WHERE code_canonique = ?');
        $st->execute([$code]);
        $r = $st->fetch();

        return $r === false ? null : $this->projeter($r, $date);
    }

    /**
     * @param array{type?:string,etat?:string,classification?:string,parent?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public function listerRealms(array $filtres = []): array
    {
        $sql = 'SELECT * FROM realm';
        $conditions = [];
        $args = [];
        if (isset($filtres['type'])) {
            $conditions[] = 'type_realm_reference = ?';
            $args[] = $filtres['type'];
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY reference';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        $lignes = array_map(fn (array $r): array => $this->projeter($r), $st->fetchAll());
        if (isset($filtres['etat'])) {
            $lignes = array_values(array_filter($lignes, static fn (array $l): bool => $l['etat'] === $filtres['etat']));
        }
        if (isset($filtres['classification'])) {
            $lignes = array_values(array_filter(
                $lignes,
                static fn (array $l): bool => ($l['revision']['classification_reference'] ?? null) === $filtres['classification'],
            ));
        }
        if (isset($filtres['organisation'])) {
            $org = (string) $filtres['organisation'];
            $lignes = array_values(array_filter(
                $lignes,
                fn (array $l): bool => $this->resoudreOrganisations((string) $l['reference']) !== []
                    && array_filter(
                        $this->resoudreOrganisations((string) $l['reference']),
                        static fn (array $o): bool => $o['organisation_reference'] === $org,
                    ) !== [],
            ));
        }
        if (isset($filtres['produit'])) {
            $prd = (string) $filtres['produit'];
            $lignes = array_values(array_filter(
                $lignes,
                fn (array $l): bool => array_filter(
                    $this->resoudreProduits((string) $l['reference']),
                    static fn (array $p): bool => $p['produit_reference'] === $prd,
                ) !== [],
            ));
        }
        if (isset($filtres['parent'])) {
            $parent = (string) $filtres['parent'];
            $lignes = array_values(array_filter(
                $lignes,
                fn (array $l): bool => array_filter(
                    $this->resoudreParents((string) $l['reference']),
                    static fn (array $p): bool => $p['realm_source_reference'] === $parent,
                ) !== [],
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
            'motif_reference' => $cycle['motif_reference'],
            'motif_detail' => $cycle['motif_detail'],
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
            'SELECT * FROM realm_cycle WHERE realm_reference = ? ORDER BY date_effet, id'
        );
        $st->execute([$reference]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function resoudreRelations(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_relation
             WHERE (realm_source_reference = ? OR realm_cible_reference = ?)
             AND date_debut <= ?
             ORDER BY date_debut, reference'
        );
        $st->execute([$reference, $reference, $jour]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $r): bool => $r['date_fin'] === null || $r['date_fin'] >= $jour,
        ));
    }

    /** @return list<array<string,mixed>> réalisé : realms dont `reference` est le PARENT_DE direct */
    public function resoudreEnfants(string $reference, ?string $date = null): array
    {
        return array_values(array_filter(
            $this->resoudreRelations($reference, $date),
            static fn (array $r): bool => $r['type_relation_reference'] === 'PARENT_DE'
                && $r['realm_source_reference'] === $reference,
        ));
    }

    /** @return list<array<string,mixed>> realms dont `reference` est enfant direct (PARENT_DE) */
    public function resoudreParents(string $reference, ?string $date = null): array
    {
        return array_values(array_filter(
            $this->resoudreRelations($reference, $date),
            static fn (array $r): bool => $r['type_relation_reference'] === 'PARENT_DE'
                && $r['realm_cible_reference'] === $reference,
        ));
    }

    /** @return list<string> chaîne des ascendants, bornée pour éviter une récursion non bornée (fiche §52, §69) */
    public function resoudreAscendance(string $reference, ?string $date = null, int $profondeurMax = 50): array
    {
        $chaine = [];
        $courant = $reference;
        $vus = [];
        for ($i = 0; $i < $profondeurMax; $i++) {
            $parents = $this->resoudreParents($courant, $date);
            if ($parents === []) {
                break;
            }
            $parent = (string) $parents[0]['realm_source_reference'];
            if (isset($vus[$parent])) {
                // Cycle détecté par ailleurs (import, restauration) : on
                // s'arrête plutôt que de boucler indéfiniment.
                break;
            }
            $vus[$parent] = true;
            $chaine[] = $parent;
            $courant = $parent;
        }

        return $chaine;
    }

    /** @return list<string> descendance bornée, en largeur (fiche §52, §69) */
    public function resoudreDescendance(string $reference, ?string $date = null, int $limiteNoeuds = 500): array
    {
        $vus = [];
        $file = [$reference];
        $resultat = [];
        while ($file !== [] && count($resultat) < $limiteNoeuds) {
            $noeud = array_shift($file);
            foreach ($this->resoudreEnfants($noeud, $date) as $relation) {
                $enfant = (string) $relation['realm_cible_reference'];
                if (isset($vus[$enfant])) {
                    continue;
                }
                $vus[$enfant] = true;
                $resultat[] = $enfant;
                $file[] = $enfant;
            }
        }

        return $resultat;
    }

    /** @return list<array<string,mixed>> */
    public function resoudrePerimetres(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_perimetre WHERE realm_reference = ? ORDER BY dimension_reference, id'
        );
        $st->execute([$reference]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $p): bool => $p['date_debut'] <= $jour && ($p['date_fin'] === null || $p['date_fin'] >= $jour),
        ));
    }

    /** @return list<array<string,mixed>> */
    public function resoudreIdentifiantsExternes(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_identifiant_externe WHERE realm_reference = ? ORDER BY id'
        );
        $st->execute([$reference]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $i): bool => $i['date_debut'] <= $jour && ($i['date_fin'] === null || $i['date_fin'] >= $jour),
        ));
    }

    /** @return list<array<string,mixed>> */
    public function resoudreOrganisations(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_organisation WHERE realm_reference = ? ORDER BY reference'
        );
        $st->execute([$reference]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $o): bool => $o['date_debut'] <= $jour && ($o['date_fin'] === null || $o['date_fin'] >= $jour),
        ));
    }

    /** @return list<array<string,mixed>> lecture inverse — fiche §43 */
    public function listerRealmsOrganisation(string $organisation, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_organisation WHERE organisation_reference = ? ORDER BY reference'
        );
        $st->execute([$organisation]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $o): bool => $o['date_debut'] <= $jour && ($o['date_fin'] === null || $o['date_fin'] >= $jour),
        ));
    }

    /** @return list<array<string,mixed>> */
    public function resoudreProduits(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_produit WHERE realm_reference = ? ORDER BY reference'
        );
        $st->execute([$reference]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $p): bool => $p['date_debut'] <= $jour && ($p['date_fin'] === null || $p['date_fin'] >= $jour),
        ));
    }

    /** @return list<array<string,mixed>> */
    public function resoudreContrats(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_contrat WHERE realm_reference = ? ORDER BY id'
        );
        $st->execute([$reference]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $c): bool => $c['date_debut'] <= $jour && ($c['date_fin'] === null || $c['date_fin'] >= $jour),
        ));
    }

    /** @return list<array<string,mixed>> franchissements où `reference` est source ou cible */
    public function resoudreFranchissements(string $reference, ?string $date = null): array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_franchissement
             WHERE (realm_source_reference = ? OR realm_cible_reference = ?)
             AND date_debut <= ?
             ORDER BY date_debut, id'
        );
        $st->execute([$reference, $reference, $jour]);

        return array_values(array_filter(
            $st->fetchAll(),
            static fn (array $f): bool => $f['date_fin'] === null || $f['date_fin'] >= $jour,
        ));
    }

    /** @return array<string,mixed>|null la dernière vérification enregistrée, quel que soit son résultat */
    public function resoudreVerificationCourante(string $reference, ?string $date = null): ?array
    {
        $jour = $date ?? date('Y-m-d');
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_verification
             WHERE realm_reference = ? AND verifie_le <= ?
             ORDER BY verifie_le DESC, id DESC LIMIT 1'
        );
        $st->execute([$reference, $jour]);
        $v = $st->fetch();
        if ($v === false) {
            return null;
        }

        $v['expiree'] = $v['expire_le'] !== null && (string) $v['expire_le'] < $jour;

        return $v;
    }

    /**
     * Contrôle de portée déterministe (fiche §40). Ne constitue jamais une
     * autorisation : la couche applicative doit ensuite demander une
     * décision à `CAP-CORE-004`.
     *
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function verifierPortee(array $dossier): array
    {
        $reference = trim((string) ($dossier['realm'] ?? ''));
        $jour = (string) ($dossier['date'] ?? date('Y-m-d'));
        $faits = [];

        $realm = $reference === '' ? null : $this->ligneRealm($reference);
        $faits['realm_connu'] = $realm !== null;
        $faits['realm_etat'] = $realm === null ? null : ($this->resoudreEtat($reference, $jour)['etat'] ?? null);

        $organisation = $this->nullable($dossier['organisation'] ?? null);
        $faits['organisation_fournie'] = $organisation !== null;
        if ($organisation !== null && $realm !== null) {
            if ($this->organisations === null) {
                $faits['dependance_indisponible'] = true;
                $faits['organisation_rattachee'] = false;
                $faits['organisation_active'] = false;
            } else {
                $rattachements = array_values(array_filter(
                    $this->resoudreOrganisations($reference, $jour),
                    static fn (array $o): bool => $o['organisation_reference'] === $organisation,
                ));
                $faits['organisation_rattachee'] = $rattachements !== [];
                try {
                    $ficheOrg = $this->organisations->resoudreOrganisation($organisation);
                    $faits['organisation_active'] = ($ficheOrg['etat'] ?? null) === 'ACTIVE';
                } catch (\Throwable) {
                    $faits['dependance_indisponible'] = true;
                    $faits['organisation_active'] = false;
                }
            }
        }

        $produit = $this->nullable($dossier['produit'] ?? null);
        $faits['produit_fourni'] = $produit !== null;
        if ($produit !== null && $realm !== null) {
            if ($this->produits === null) {
                $faits['dependance_indisponible'] = true;
                $faits['produit_rattache'] = false;
                $faits['produit_actif'] = false;
            } else {
                $rattachements = array_values(array_filter(
                    $this->resoudreProduits($reference, $jour),
                    static fn (array $p): bool => $p['produit_reference'] === $produit,
                ));
                $faits['produit_rattache'] = $rattachements !== [];
                try {
                    $ficheProduit = $this->produits->resoudreProduit($produit);
                    $faits['produit_actif'] = ($ficheProduit['etat'] ?? null) === 'ACTIF';
                } catch (\Throwable) {
                    $faits['dependance_indisponible'] = true;
                    $faits['produit_actif'] = false;
                }
            }
        }

        $contrat = $this->nullable($dossier['contrat'] ?? null);
        $faits['contrat_fourni'] = $contrat !== null;
        if ($contrat !== null && $realm !== null) {
            if ($this->contrats === null) {
                $faits['dependance_indisponible'] = true;
                $faits['contrat_rattache'] = false;
                $faits['contrat_actif'] = false;
            } else {
                $rattachements = array_values(array_filter(
                    $this->resoudreContrats($reference, $jour),
                    static fn (array $c): bool => $c['contrat_reference'] === $contrat,
                ));
                $faits['contrat_rattache'] = $rattachements !== [];
                try {
                    $ficheContrat = $this->contrats->resoudreContrat($contrat);
                    $faits['contrat_actif'] = ($ficheContrat['version_active'] ?? null) !== null;
                } catch (\Throwable) {
                    $faits['dependance_indisponible'] = true;
                    $faits['contrat_actif'] = false;
                }
            }
        }

        $finalite = $this->nullable($dossier['finalite'] ?? null);
        $realmSource = $this->nullable($dossier['realm_source'] ?? null);
        $realmCible = $this->nullable($dossier['realm_cible'] ?? null);
        $croisement = $realmSource !== null && $realmCible !== null && $realmSource !== $realmCible;
        $faits['franchissement_croise'] = $croisement;
        $faits['finalite_fournie'] = $finalite !== null;
        $faits['finalite_requise'] = $croisement;

        if ($croisement) {
            $franchissements = array_values(array_filter(
                $this->resoudreFranchissements($realmSource, $jour),
                static fn (array $f): bool => $f['realm_source_reference'] === $realmSource
                    && $f['realm_cible_reference'] === $realmCible
                    && ($finalite === null || $f['finalite_reference'] === $finalite),
            ));
            $faits['franchissement_refuse'] = array_filter(
                $franchissements,
                static fn (array $f): bool => $f['effet_reference'] === 'REFUSE',
            ) !== [];
            $faits['franchissement_permis'] = array_filter(
                $franchissements,
                static fn (array $f): bool => $f['effet_reference'] === 'PERMET',
            ) !== [];
        }

        $verification = $realm === null ? null : $this->resoudreVerificationCourante($reference, $jour);
        $faits['verification_expiree'] = $verification !== null && $verification['expiree'] === true
            && $verification['resultat_reference'] === 'CONFORME';

        return EvaluateurPortee::evaluer($dossier, $faits);
    }

    /**
     * Diagnostic non destructif utilisé par la readiness et l'exploitation
     * (fiche §62, §63).
     *
     * @return array<string,mixed>
     */
    public function diagnostiquerRegistre(): array
    {
        $nombreRealms = (int) $this->magasin->query('SELECT count(*) FROM realm')->fetchColumn();
        $realms = $this->magasin->query('SELECT reference FROM realm')->fetchAll(\PDO::FETCH_COLUMN);
        $actifs = 0;
        foreach ($realms as $reference) {
            if (($this->resoudreEtat((string) $reference)['etat'] ?? null) === 'ACTIF') {
                $actifs++;
            }
        }
        $relationsActives = (int) $this->magasin->query(
            "SELECT count(*) FROM realm_relation WHERE date_fin IS NULL"
        )->fetchColumn();
        $organisationsActives = (int) $this->magasin->query(
            'SELECT count(*) FROM realm_organisation WHERE date_fin IS NULL'
        )->fetchColumn();
        $produitsActifs = (int) $this->magasin->query(
            'SELECT count(*) FROM realm_produit WHERE date_fin IS NULL'
        )->fetchColumn();
        $franchissements = (int) $this->magasin->query(
            'SELECT count(*) FROM realm_franchissement WHERE date_fin IS NULL'
        )->fetchColumn();

        $aretes = $this->aretesHierarchiques();
        $cycles = ValidateurRealms::detecterCycles($aretes);

        $referencesOrphelines = [];
        $stOrgs = $this->magasin->query('SELECT DISTINCT realm_reference FROM realm_organisation WHERE date_fin IS NULL');
        foreach ($stOrgs->fetchAll(\PDO::FETCH_COLUMN) as $reference) {
            if ($this->ligneRealm((string) $reference) === null) {
                $referencesOrphelines[] = (string) $reference;
            }
        }

        return [
            'nombre_realms' => $nombreRealms,
            'nombre_realms_actifs' => $actifs,
            'nombre_relations_actives' => $relationsActives,
            'nombre_organisations_rattachees' => $organisationsActives,
            'nombre_produits_rattaches' => $produitsActifs,
            'nombre_franchissements' => $franchissements,
            'cycles_detectes' => $cycles,
            'references_orphelines' => $referencesOrphelines,
            'coherent' => $cycles === [] && $referencesOrphelines === [],
        ];
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — realm

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function inscrireRealm(array $dossier): array
    {
        foreach (['identite_reference', 'code_canonique', 'type_realm_reference', 'source', 'nom_affichage', 'classification_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $identite = trim((string) $dossier['identite_reference']);
        $code = trim((string) $dossier['code_canonique']);
        $type = (string) $dossier['type_realm_reference'];
        $classification = (string) $dossier['classification_reference'];

        if (!in_array($type, PolitiqueRealms::TYPES_REALM, true)) {
            return $this->refus('TYPE_REALM_INCONNU', 'type_realm_reference hors liste close');
        }
        if (!in_array($classification, PolitiqueRealms::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        if ($this->ligneRealmParIdentite($identite) !== null) {
            return $this->refus('IDENTITE_DEJA_LIEE', "l'identité `{$identite}` porte déjà une fiche de realm");
        }
        if ($this->ligneRealmParCode($code) !== null) {
            return $this->refus('CODE_DEJA_UTILISE', "le code canonique `{$code}` est déjà utilisé");
        }
        $identiteResolue = $this->identites->resoudreIdentite($identite);
        if ($identiteResolue === null) {
            return $this->refus('IDENTITE_INCONNUE', "l'identité canonique `{$identite}` n'existe pas");
        }
        if (($identiteResolue['type'] ?? null) !== 'realm') {
            return $this->refus('IDENTITE_TYPE_INVALIDE', "l'identité `{$identite}` n'est pas de type realm");
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }

        $source = (string) $dossier['source'];
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $nomAffichage = trim((string) $dossier['nom_affichage']);
        $description = $this->nullable($dossier['description'] ?? null);
        $organisationResponsable = $this->nullable($dossier['organisation_responsable_reference'] ?? null);
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        if ($organisationResponsable !== null && $this->organisations !== null) {
            try {
                $ficheOrg = $this->organisations->resoudreOrganisation($organisationResponsable);
            } catch (\Throwable) {
                return $this->refus('DEPENDANCE_INDISPONIBLE', 'CAP-CORE-002 indisponible');
            }
            if ($ficheOrg === null) {
                return $this->refus('ORGANISATION_INCONNUE', "organisation `{$organisationResponsable}` inconnue");
            }
        }

        return $this->transaction(function () use (
            $identite, $code, $type, $source, $producteur, $politique, $preuve,
            $nomAffichage, $description, $organisationResponsable, $classification, $date, $correlation,
        ): array {
            $reference = $this->allouerReference('realm');
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO realm
                 (reference,identite_reference,code_canonique,type_realm_reference,source_reference,
                  politique_inscription_reference,producteur_reference,preuve_reference,cree_le,modifie_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $identite, $code, $type, $source, $politique, $producteur, $preuve, $maintenant, $maintenant,
            ]);
            $this->inscrireRevision(
                $reference, $nomAffichage, $description, $organisationResponsable,
                $classification, $date, $producteur, $source, $preuve, $correlation,
            );
            $this->inscrireCycle($reference, 'PREPARATION', $date, null, null, $producteur, $politique, $preuve, $correlation);

            return [
                'reference' => $reference,
                'identite_reference' => $identite,
                'code_canonique' => $code,
                'etat' => 'PREPARATION',
                'type_realm_reference' => $type,
            ];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function modifierRealm(string $reference, array $dossier): array
    {
        $realm = $this->ligneRealm($reference);
        if ($realm === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $derniere = $this->derniereRevision($reference);
        $classification = (string) ($dossier['classification_reference'] ?? $derniere['classification_reference'] ?? '');
        if (!in_array($classification, PolitiqueRealms::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        $nomAffichage = trim((string) ($dossier['nom_affichage'] ?? $derniere['nom_affichage'] ?? ''));
        if ($nomAffichage === '') {
            return $this->refus('NOM_AFFICHAGE_ABSENT', 'nom_affichage absent');
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }
        $description = $this->nullable($dossier['description'] ?? $derniere['description'] ?? null);
        $organisationResponsable = $this->nullable(
            $dossier['organisation_responsable_reference'] ?? $derniere['organisation_responsable_reference'] ?? null
        );
        $producteur = (string) $dossier['producteur'];
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $nomAffichage, $description, $organisationResponsable,
            $classification, $date, $producteur, $source, $preuve, $correlation,
        ): array {
            $numero = $this->inscrireRevision(
                $reference, $nomAffichage, $description, $organisationResponsable,
                $classification, $date, $producteur, $source, $preuve, $correlation,
            );
            $this->toucher($reference);

            return ['reference' => $reference, 'numero_revision' => $numero, 'nom_affichage' => $nomAffichage];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function activerRealm(string $reference, array $dossier): array
    {
        $realm = $this->ligneRealm($reference);
        if ($realm === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $producteur = (string) $dossier['producteur'];
        if ($producteur === $reference) {
            return $this->refus('AUTO_ACTIVATION_INTERDITE', 'un realm ne peut jamais s’auto-activer');
        }
        if ($this->derniereRevision($reference) === null) {
            return $this->refus('REVISION_ABSENTE', 'aucune révision descriptive n’existe encore');
        }
        if (trim((string) $realm['source_reference']) === '') {
            return $this->refus('SOURCE_ABSENTE', 'aucune source déclarée');
        }

        $etat = $this->etatActuel($reference);
        if ($etat === 'ACTIF') {
            return ['reference' => $reference, 'etat' => 'ACTIF', 'idempotent' => true];
        }
        if (!in_array($etat, PolitiqueRealms::ETATS_ACTIVABLES, true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "un realm `{$etat}` ne s'active pas directement");
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motifDetail = $this->nullable($dossier['motif'] ?? null);
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $date, $motifDetail, $producteur, $politique, $preuve, $correlation): array {
            $this->inscrireCycle($reference, 'ACTIF', $date, null, $motifDetail, $producteur, $politique, $preuve, $correlation);
            $this->toucher($reference);

            return ['reference' => $reference, 'etat' => 'ACTIF', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function suspendreRealm(string $reference, array $dossier): array
    {
        return $this->transitionCycle($reference, 'SUSPENDU', ['ACTIF'], $dossier);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerRealm(string $reference, array $dossier): array
    {
        return $this->transitionCycle($reference, 'FERME', ['ACTIF', 'SUSPENDU'], $dossier);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function retirerRealm(string $reference, array $dossier): array
    {
        $realm = $this->ligneRealm($reference);
        if ($realm === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $motifReference = $this->nullable($dossier['motif_reference'] ?? null);
        if ($motifReference === null) {
            return $this->refus('MOTIF_ABSENT', 'un retrait exige un motif explicite');
        }
        $etat = $this->etatActuel($reference);
        if ($etat === 'RETIRE') {
            return ['reference' => $reference, 'etat' => 'RETIRE', 'idempotent' => true];
        }
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motifDetail = $this->nullable($dossier['motif_detail'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $date, $motifReference, $motifDetail, $producteur, $politique, $preuve, $correlation): array {
            $this->inscrireCycle($reference, 'RETIRE', $date, $motifReference, $motifDetail, $producteur, $politique, $preuve, $correlation);
            $this->toucher($reference);

            return ['reference' => $reference, 'etat' => 'RETIRE', 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — relations entre realms

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerRelation(array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['realm_source_reference', 'realm_cible_reference', 'type_relation_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $source = trim((string) $dossier['realm_source_reference']);
        $cible = trim((string) $dossier['realm_cible_reference']);
        if ($source === $cible) {
            return $this->refus('AUTO_RELATION_INTERDITE', "un realm n'entretient pas de relation avec lui-même");
        }
        if ($this->ligneRealm($source) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$source}` inconnu");
        }
        if ($this->ligneRealm($cible) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$cible}` inconnu");
        }
        $type = (string) $dossier['type_relation_reference'];
        if (!in_array($type, PolitiqueRealms::TYPES_RELATION, true)) {
            return $this->refus('TYPE_RELATION_INCONNU', 'type_relation_reference hors liste close');
        }
        if (in_array($type, PolitiqueRealms::TYPES_RELATION_HIERARCHIQUE, true)) {
            $aretes = $this->aretesHierarchiques();
            if (ValidateurRealms::relationCreeraitCycle($source, $cible, $aretes)) {
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
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $source, $cible, $type, $date, $sourceRef, $preuve, $producteur, $correlation,
        ): array {
            $relationRef = $this->allouerReference('relation');
            $this->magasin->prepare(
                'INSERT INTO realm_relation
                 (reference,realm_source_reference,realm_cible_reference,type_relation_reference,
                  date_debut,date_fin,acteur_reference,source_reference,preuve_reference,correlation_id,cree_le)
                 VALUES(?,?,?,?,?,NULL,?,?,?,?,?)'
            )->execute([
                $relationRef, $source, $cible, $type, $date, $producteur, $sourceRef, $preuve, $correlation, gmdate('c'),
            ]);

            return ['reference' => $relationRef, 'realm_source_reference' => $source, 'realm_cible_reference' => $cible, 'type_relation_reference' => $type];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerRelation(string $relation, array $dossier): array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm_relation WHERE reference = ?');
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
            $this->magasin->prepare('UPDATE realm_relation SET date_fin = ? WHERE reference = ?')->execute([$date, $relation]);

            return ['reference' => $relation, 'date_fin' => $date, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — périmètres et identifiants externes

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerPerimetre(string $reference, array $dossier): array
    {
        if ($this->ligneRealm($reference) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['dimension_reference', 'valeur_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $dimension = (string) $dossier['dimension_reference'];
        if (!in_array($dimension, PolitiqueRealms::DIMENSIONS_PERIMETRE, true)) {
            return $this->refus('DIMENSION_INCONNUE', 'dimension_reference hors liste close');
        }
        $valeur = trim((string) $dossier['valeur_reference']);
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $valeurExterne = $this->nullable($dossier['valeur_externe'] ?? null);
        $systemeExterne = $this->nullable($dossier['systeme_externe_reference'] ?? null);
        if ($valeurExterne !== null && $systemeExterne === null) {
            return $this->refus('SYSTEME_EXTERNE_ABSENT', 'une valeur externe doit préciser son système de référence');
        }
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $producteur = (string) $dossier['producteur'];

        return $this->transaction(function () use (
            $reference, $dimension, $valeur, $valeurExterne, $systemeExterne, $date, $source, $preuve, $producteur,
        ): array {
            $this->magasin->prepare(
                'INSERT INTO realm_perimetre
                 (realm_reference,dimension_reference,valeur_reference,valeur_externe,systeme_externe_reference,
                  date_debut,date_fin,acteur_reference,source_reference,preuve_reference,cree_le)
                 VALUES(?,?,?,?,?,?,NULL,?,?,?,?)'
            )->execute([
                $reference, $dimension, $valeur, $valeurExterne, $systemeExterne, $date, $producteur, $source, $preuve, gmdate('c'),
            ]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'realm_reference' => $reference, 'dimension_reference' => $dimension, 'valeur_reference' => $valeur];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerPerimetre(int $id, array $dossier): array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm_perimetre WHERE id = ?');
        $st->execute([$id]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return $this->refus('PERIMETRE_INCONNU', "périmètre `{$id}` inconnu");
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
            $this->magasin->prepare('UPDATE realm_perimetre SET date_fin = ? WHERE id = ?')->execute([$date, $id]);

            return ['id' => $id, 'date_fin' => $date, 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerIdentifiantExterne(string $reference, array $dossier): array
    {
        if ($this->ligneRealm($reference) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['systeme_reference', 'valeur'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $systeme = trim((string) $dossier['systeme_reference']);
        $valeur = trim((string) $dossier['valeur']);
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $jour = $date;
        $st = $this->magasin->prepare(
            'SELECT * FROM realm_identifiant_externe WHERE systeme_reference = ? AND valeur = ?'
        );
        $st->execute([$systeme, $valeur]);
        foreach ($st->fetchAll() as $existant) {
            if ($existant['date_fin'] === null || $existant['date_fin'] >= $jour) {
                return $this->refus('IDENTIFIANT_DEJA_DECLARE', 'ce couple système/valeur est déjà actif');
            }
        }
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];

        return $this->transaction(function () use ($reference, $systeme, $valeur, $date, $source, $preuve): array {
            $this->magasin->prepare(
                'INSERT INTO realm_identifiant_externe
                 (realm_reference,systeme_reference,valeur,date_debut,date_fin,source_reference,preuve_reference,cree_le)
                 VALUES(?,?,?,?,NULL,?,?,?)'
            )->execute([$reference, $systeme, $valeur, $date, $source, $preuve, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'realm_reference' => $reference, 'systeme_reference' => $systeme, 'valeur' => $valeur];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerIdentifiantExterne(int $id, array $dossier): array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm_identifiant_externe WHERE id = ?');
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
            $this->magasin->prepare('UPDATE realm_identifiant_externe SET date_fin = ? WHERE id = ?')->execute([$date, $id]);

            return ['id' => $id, 'date_fin' => $date, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — rattachement d'organisation

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function rattacherOrganisation(string $reference, array $dossier): array
    {
        if ($this->ligneRealm($reference) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['organisation_reference', 'role_reference', 'classification_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $organisation = trim((string) $dossier['organisation_reference']);
        $role = (string) $dossier['role_reference'];
        if (!in_array($role, PolitiqueRealms::ROLES_ORGANISATION, true)) {
            return $this->refus('ROLE_INCONNU', 'role_reference hors liste close');
        }
        $classification = (string) $dossier['classification_reference'];
        if (!in_array($classification, PolitiqueRealms::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        if ($this->organisations === null) {
            return $this->refus('DEPENDANCE_INDISPONIBLE', 'CAP-CORE-002 indisponible : rattachement refusé');
        }
        try {
            $ficheOrg = $this->organisations->resoudreOrganisation($organisation);
        } catch (\Throwable) {
            return $this->refus('DEPENDANCE_INDISPONIBLE', 'CAP-CORE-002 indisponible : rattachement refusé');
        }
        if ($ficheOrg === null) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$organisation}` inconnue");
        }
        if (($ficheOrg['etat'] ?? null) !== 'ACTIVE') {
            return $this->refus('ORGANISATION_INACTIVE', "organisation `{$organisation}` non active");
        }
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        if (in_array($role, PolitiqueRealms::ROLES_ORGANISATION_A_MANDAT, true)) {
            $acteur = (string) $dossier['producteur'];
            try {
                $representation = $this->organisations->verifierRepresentation($acteur, $organisation, 'realm.organisation.rattacher', $date);
            } catch (\Throwable) {
                $representation = ['opposable' => false];
            }
            if (($representation['opposable'] ?? false) !== true
                && $acteur !== PolitiqueRealms::AUTORITE) {
                return $this->refus('MANDAT_INSUFFISANT', "aucun mandat vérifié via CAP-CORE-003 pour engager `{$organisation}` en rôle `{$role}`");
            }
        }
        $doublon = array_filter(
            $this->resoudreOrganisations($reference, $date),
            static fn (array $o): bool => $o['organisation_reference'] === $organisation && $o['role_reference'] === $role,
        );
        if ($doublon !== []) {
            return ['reference' => array_values($doublon)[0]['reference'], 'idempotent' => true];
        }
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $organisation, $role, $classification, $date, $source, $preuve, $producteur, $politique, $correlation,
        ): array {
            $rattachementRef = $this->allouerReference('organisation');
            $this->magasin->prepare(
                'INSERT INTO realm_organisation
                 (reference,realm_reference,organisation_reference,role_reference,date_debut,date_fin,
                  classification_reference,acteur_reference,politique_reference,source_reference,preuve_reference,correlation_id,cree_le)
                 VALUES(?,?,?,?,?,NULL,?,?,?,?,?,?,?)'
            )->execute([
                $rattachementRef, $reference, $organisation, $role, $date,
                $classification, $producteur, $politique, $source, $preuve, $correlation, gmdate('c'),
            ]);

            return ['reference' => $rattachementRef, 'realm_reference' => $reference, 'organisation_reference' => $organisation, 'role_reference' => $role, 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function detacherOrganisation(string $rattachement, array $dossier): array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm_organisation WHERE reference = ?');
        $st->execute([$rattachement]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return $this->refus('RATTACHEMENT_INCONNU', "rattachement `{$rattachement}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($ligne['date_fin'] !== null) {
            return ['reference' => $rattachement, 'idempotent' => true];
        }
        $date = (string) ($dossier['date_fin'] ?? date('Y-m-d'));
        if (!$this->dateValide($date) || $date < $ligne['date_debut']) {
            return $this->refus('DATE_INVALIDE', 'date_fin doit suivre YYYY-MM-DD et ne pas précéder le début');
        }

        return $this->transaction(function () use ($rattachement, $date): array {
            $this->magasin->prepare('UPDATE realm_organisation SET date_fin = ? WHERE reference = ?')->execute([$date, $rattachement]);

            return ['reference' => $rattachement, 'date_fin' => $date, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — rattachement de produit

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function rattacherProduit(string $reference, array $dossier): array
    {
        if ($this->ligneRealm($reference) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['produit_reference', 'role_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $produit = trim((string) $dossier['produit_reference']);
        $role = (string) $dossier['role_reference'];
        if (!in_array($role, PolitiqueRealms::ROLES_PRODUIT, true)) {
            return $this->refus('ROLE_INCONNU', 'role_reference hors liste close');
        }
        if ($this->produits === null) {
            return $this->refus('DEPENDANCE_INDISPONIBLE', 'CAP-CORE-011 indisponible : rattachement refusé');
        }
        try {
            $ficheProduit = $this->produits->resoudreProduit($produit);
        } catch (\Throwable) {
            return $this->refus('DEPENDANCE_INDISPONIBLE', 'CAP-CORE-011 indisponible : rattachement refusé');
        }
        if ($ficheProduit === null) {
            return $this->refus('PRODUIT_INCONNU', "produit `{$produit}` inconnu");
        }
        if (($ficheProduit['etat'] ?? null) !== 'ACTIF') {
            return $this->refus('PRODUIT_INACTIF', "produit `{$produit}` non actif");
        }
        $producteur = (string) $dossier['producteur'];
        if ($producteur === $produit) {
            return $this->refus('AUTO_RATTACHEMENT_INTERDIT', 'un produit ne peut pas s’auto-rattacher');
        }
        $environnement = $this->nullable($dossier['environnement_reference'] ?? null);
        if ($environnement !== null) {
            try {
                $env = $this->produits->resoudreEnvironnementActif($produit, $environnement);
            } catch (\Throwable) {
                return $this->refus('DEPENDANCE_INDISPONIBLE', 'CAP-CORE-011 indisponible : rattachement refusé');
            }
            if ($env === null) {
                return $this->refus('ENVIRONNEMENT_INCONNU', "environnement `{$environnement}` inconnu pour `{$produit}`");
            }
        }
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $doublon = array_filter(
            $this->resoudreProduits($reference, $date),
            static fn (array $p): bool => $p['produit_reference'] === $produit && $p['role_reference'] === $role,
        );
        if ($doublon !== []) {
            return ['reference' => array_values($doublon)[0]['reference'], 'idempotent' => true];
        }
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $politique = (string) $dossier['politique'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use (
            $reference, $produit, $role, $environnement, $date, $source, $preuve, $producteur, $politique, $correlation,
        ): array {
            $rattachementRef = $this->allouerReference('produit');
            $this->magasin->prepare(
                'INSERT INTO realm_produit
                 (reference,realm_reference,produit_reference,role_reference,environnement_reference,date_debut,date_fin,
                  acteur_reference,politique_reference,source_reference,preuve_reference,correlation_id,cree_le)
                 VALUES(?,?,?,?,?,?,NULL,?,?,?,?,?,?)'
            )->execute([
                $rattachementRef, $reference, $produit, $role, $environnement, $date,
                $producteur, $politique, $source, $preuve, $correlation, gmdate('c'),
            ]);

            return ['reference' => $rattachementRef, 'realm_reference' => $reference, 'produit_reference' => $produit, 'role_reference' => $role, 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function detacherProduit(string $rattachement, array $dossier): array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm_produit WHERE reference = ?');
        $st->execute([$rattachement]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return $this->refus('RATTACHEMENT_INCONNU', "rattachement `{$rattachement}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($ligne['date_fin'] !== null) {
            return ['reference' => $rattachement, 'idempotent' => true];
        }
        $date = (string) ($dossier['date_fin'] ?? date('Y-m-d'));
        if (!$this->dateValide($date) || $date < $ligne['date_debut']) {
            return $this->refus('DATE_INVALIDE', 'date_fin doit suivre YYYY-MM-DD et ne pas précéder le début');
        }

        return $this->transaction(function () use ($rattachement, $date): array {
            $this->magasin->prepare('UPDATE realm_produit SET date_fin = ? WHERE reference = ?')->execute([$date, $rattachement]);

            return ['reference' => $rattachement, 'date_fin' => $date, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — contrat

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function rattacherContrat(string $reference, array $dossier): array
    {
        if ($this->ligneRealm($reference) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['contrat_reference', 'role_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $contrat = trim((string) $dossier['contrat_reference']);
        $role = (string) $dossier['role_reference'];
        if (!in_array($role, PolitiqueRealms::ROLES_CONTRAT, true)) {
            return $this->refus('ROLE_INCONNU', 'role_reference hors liste close');
        }
        if ($this->contrats === null) {
            return $this->refus('DEPENDANCE_INDISPONIBLE', 'CAP-CORE-009 indisponible : rattachement refusé');
        }
        $version = $this->nullable($dossier['version_reference'] ?? null);
        try {
            if ($version !== null) {
                $ficheVersion = $this->contrats->resoudreVersion($contrat, $version);
                $active = $ficheVersion !== null && in_array($ficheVersion['etat'] ?? null, ['ACTIVE', 'DEPRECIEE'], true);
            } else {
                $ficheContrat = $this->contrats->resoudreContrat($contrat);
                $active = $ficheContrat !== null && ($ficheContrat['version_active'] ?? null) !== null;
                $ficheVersion = $ficheContrat;
            }
        } catch (\Throwable) {
            return $this->refus('DEPENDANCE_INDISPONIBLE', 'CAP-CORE-009 indisponible : rattachement refusé');
        }
        if ($ficheVersion === null) {
            return $this->refus('CONTRAT_INCONNU', "contrat `{$contrat}` inconnu");
        }
        if (!$active) {
            return $this->refus('CONTRAT_INACTIF', "contrat `{$contrat}` sans version active ou dépréciée utilisable");
        }
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $preuve = (string) $dossier['preuve'];
        $politique = (string) $dossier['politique'];
        $producteur = (string) $dossier['producteur'];

        return $this->transaction(function () use ($reference, $contrat, $version, $role, $date, $preuve, $politique, $producteur): array {
            $this->magasin->prepare(
                'INSERT INTO realm_contrat
                 (realm_reference,contrat_reference,version_reference,role_reference,date_debut,date_fin,
                  acteur_reference,politique_reference,preuve_reference,cree_le)
                 VALUES(?,?,?,?,?,NULL,?,?,?,?)'
            )->execute([$reference, $contrat, $version, $role, $date, $producteur, $politique, $preuve, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'realm_reference' => $reference, 'contrat_reference' => $contrat, 'role_reference' => $role];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function detacherContrat(int $id, array $dossier): array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm_contrat WHERE id = ?');
        $st->execute([$id]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return $this->refus('RATTACHEMENT_INCONNU', "rattachement `{$id}` inconnu");
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
            $this->magasin->prepare('UPDATE realm_contrat SET date_fin = ? WHERE id = ?')->execute([$date, $id]);

            return ['id' => $id, 'date_fin' => $date, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — franchissement

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerFranchissement(array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['realm_source_reference', 'realm_cible_reference', 'objet_reference', 'type_objet_reference', 'effet_reference', 'finalite_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $source = trim((string) $dossier['realm_source_reference']);
        $cible = trim((string) $dossier['realm_cible_reference']);
        if ($this->ligneRealm($source) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$source}` inconnu");
        }
        if ($this->ligneRealm($cible) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$cible}` inconnu");
        }
        $effet = (string) $dossier['effet_reference'];
        if (!in_array($effet, PolitiqueRealms::EFFETS_FRANCHISSEMENT, true)) {
            return $this->refus('EFFET_INCONNU', 'effet_reference hors liste close');
        }
        $objet = trim((string) $dossier['objet_reference']);
        if ($objet === '*' || str_contains($objet, '*')) {
            return $this->refus('WILDCARD_INTERDIT', 'aucun objet universel implicite n’est autorisé');
        }
        $finalite = trim((string) $dossier['finalite_reference']);
        $contrat = $this->nullable($dossier['contrat_reference'] ?? null);
        if ($contrat !== null && $this->contrats !== null) {
            try {
                $ficheContrat = $this->contrats->resoudreContrat($contrat);
            } catch (\Throwable) {
                return $this->refus('DEPENDANCE_INDISPONIBLE', 'CAP-CORE-009 indisponible');
            }
            if ($ficheContrat === null || ($ficheContrat['version_active'] ?? null) === null) {
                return $this->refus('CONTRAT_INACTIF', "contrat `{$contrat}` inconnu ou sans version active");
            }
        }
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $politique = (string) $dossier['politique'];
        $source_ = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $producteur = (string) $dossier['producteur'];
        $typeObjet = trim((string) $dossier['type_objet_reference']);

        return $this->transaction(function () use (
            $source, $cible, $objet, $typeObjet, $effet, $finalite, $contrat, $date, $politique, $source_, $preuve, $producteur,
        ): array {
            $this->magasin->prepare(
                'INSERT INTO realm_franchissement
                 (realm_source_reference,realm_cible_reference,objet_reference,type_objet_reference,effet_reference,
                  finalite_reference,contrat_reference,date_debut,date_fin,politique_reference,source_reference,
                  preuve_reference,acteur_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,NULL,?,?,?,?,?)'
            )->execute([
                $source, $cible, $objet, $typeObjet, $effet, $finalite, $contrat, $date,
                $politique, $source_, $preuve, $producteur, gmdate('c'),
            ]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'realm_source_reference' => $source, 'realm_cible_reference' => $cible, 'effet_reference' => $effet];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerFranchissement(int $id, array $dossier): array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm_franchissement WHERE id = ?');
        $st->execute([$id]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return $this->refus('FRANCHISSEMENT_INCONNU', "franchissement `{$id}` inconnu");
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
            $this->magasin->prepare('UPDATE realm_franchissement SET date_fin = ? WHERE id = ?')->execute([$date, $id]);

            return ['id' => $id, 'date_fin' => $date, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées — vérification

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function enregistrerVerification(string $reference, array $dossier): array
    {
        if ($this->ligneRealm($reference) === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['type_verification_reference', 'resultat_reference', 'verifie_par_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $type = (string) $dossier['type_verification_reference'];
        if (!in_array($type, PolitiqueRealms::TYPES_VERIFICATION, true)) {
            return $this->refus('TYPE_VERIFICATION_INCONNU', 'type_verification_reference hors liste close');
        }
        $resultat = (string) $dossier['resultat_reference'];
        if (!in_array($resultat, PolitiqueRealms::RESULTATS_VERIFICATION, true)) {
            return $this->refus('RESULTAT_INCONNU', 'resultat_reference hors liste close');
        }
        $verificateur = trim((string) $dossier['verifie_par_reference']);
        if (PolitiqueRealms::AUTO_ATTESTATION_INTERDITE
            && ($verificateur === $reference || $verificateur === (string) ($dossier['producteur'] ?? ''))
            && $verificateur !== PolitiqueRealms::AUTORITE) {
            return $this->refus('AUTO_ATTESTATION_INTERDITE', 'une vérification forte ne peut pas être auto-attestée');
        }
        $preuve = (string) $dossier['preuve'];
        $verifieLe = (string) ($dossier['verifie_le'] ?? date('Y-m-d'));
        if (!$this->dateValide($verifieLe)) {
            return $this->refus('DATE_INVALIDE', 'verifie_le doit suivre YYYY-MM-DD');
        }
        $expireLe = $this->nullable($dossier['expire_le'] ?? null);
        if ($expireLe !== null && !$this->dateValide($expireLe)) {
            return $this->refus('DATE_INVALIDE', 'expire_le doit suivre YYYY-MM-DD');
        }
        $motif = $this->nullable($dossier['motif'] ?? null);

        return $this->transaction(function () use ($reference, $type, $resultat, $verificateur, $preuve, $verifieLe, $expireLe, $motif): array {
            $this->magasin->prepare(
                'INSERT INTO realm_verification
                 (realm_reference,type_verification_reference,resultat_reference,verifie_par_reference,
                  preuve_reference,verifie_le,expire_le,motif,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?)'
            )->execute([$reference, $type, $resultat, $verificateur, $preuve, $verifieLe, $expireLe, $motif, gmdate('c')]);
            $id = (int) $this->magasin->lastInsertId();

            return ['id' => $id, 'realm_reference' => $reference, 'resultat_reference' => $resultat];
        });
    }

    // ------------------------------------------------------------------
    // Internes

    private function transitionCycle(string $reference, string $cible, array $depuis, array $dossier): array
    {
        $realm = $this->ligneRealm($reference);
        if ($realm === null) {
            return $this->refus('REALM_INCONNU', "realm `{$reference}` inconnu");
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
        $motifReference = $this->nullable($dossier['motif_reference'] ?? null);
        $motifDetail = $this->nullable($dossier['motif_detail'] ?? $dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $correlation = $this->nullable($dossier['correlation_id'] ?? null);

        return $this->transaction(function () use ($reference, $cible, $date, $motifReference, $motifDetail, $producteur, $politique, $preuve, $correlation): array {
            $this->inscrireCycle($reference, $cible, $date, $motifReference, $motifDetail, $producteur, $politique, $preuve, $correlation);
            $this->toucher($reference);

            return ['reference' => $reference, 'etat' => $cible, 'idempotent' => false];
        });
    }

    /** @return list<array{0:string,1:string}> arêtes PARENT_DE actives (fiche §16, §60) */
    private function aretesHierarchiques(): array
    {
        $lignes = $this->magasin->query(
            "SELECT realm_source_reference, realm_cible_reference FROM realm_relation
             WHERE type_relation_reference = 'PARENT_DE' AND date_fin IS NULL"
        )->fetchAll();

        return array_map(
            static fn (array $r): array => [(string) $r['realm_source_reference'], (string) $r['realm_cible_reference']],
            $lignes,
        );
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
    private function ligneRealm(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm WHERE reference = ?');
        $st->execute([$reference]);
        $r = $st->fetch();

        return $r === false ? null : $r;
    }

    /** @return array<string,mixed>|null */
    private function ligneRealmParIdentite(string $identite): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm WHERE identite_reference = ?');
        $st->execute([$identite]);
        $r = $st->fetch();

        return $r === false ? null : $r;
    }

    /** @return array<string,mixed>|null */
    private function ligneRealmParCode(string $code): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM realm WHERE code_canonique = ?');
        $st->execute([$code]);
        $r = $st->fetch();

        return $r === false ? null : $r;
    }

    /** @param array<string,mixed> $realm @return array<string,mixed> */
    private function projeter(array $realm, ?string $date = null): array
    {
        return [
            'reference' => $realm['reference'],
            'identite_reference' => $realm['identite_reference'],
            'code_canonique' => $realm['code_canonique'],
            'type_realm_reference' => $realm['type_realm_reference'],
            'source_reference' => $realm['source_reference'],
            'etat' => $this->resoudreEtat((string) $realm['reference'], $date)['etat'] ?? null,
            'revision' => $this->derniereRevision((string) $realm['reference'], $date),
            'cree_le' => $realm['cree_le'],
            'modifie_le' => $realm['modifie_le'],
        ];
    }

    private function inscrireRevision(
        string $reference,
        string $nomAffichage,
        ?string $description,
        ?string $organisationResponsable,
        string $classification,
        string $date,
        string $acteur,
        string $source,
        string $preuve,
        ?string $correlation,
    ): int {
        $numero = 1 + (int) $this->magasin->query(
            'SELECT COALESCE(MAX(numero_revision),0) FROM realm_revision WHERE realm_reference = ' . $this->magasin->quote($reference)
        )->fetchColumn();
        $this->magasin->prepare(
            'INSERT INTO realm_revision
             (realm_reference,numero_revision,nom_affichage,description,organisation_responsable_reference,
              classification_reference,date_debut_validite,date_fin_validite,acteur_reference,source_reference,
              preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,NULL,?,?,?,?,?)'
        )->execute([
            $reference, $numero, $nomAffichage, $description, $organisationResponsable,
            $classification, $date, $acteur, $source, $preuve, $correlation, gmdate('c'),
        ]);

        return $numero;
    }

    /** @return array<string,mixed>|null */
    private function derniereRevision(string $reference, ?string $date = null): ?array
    {
        $sql = 'SELECT * FROM realm_revision WHERE realm_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_debut_validite <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY numero_revision DESC LIMIT 1';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $r = $st->fetch();

        return $r === false ? null : $r;
    }

    private function inscrireCycle(
        string $reference,
        string $etat,
        string $date,
        ?string $motifReference,
        ?string $motifDetail,
        string $acteur,
        string $politique,
        string $preuve,
        ?string $correlation,
    ): void {
        $this->magasin->prepare(
            'INSERT INTO realm_cycle
             (realm_reference,etat_reference,date_effet,motif_reference,motif_detail,acteur_reference,
              politique_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?)'
        )->execute([$reference, $etat, $date, $motifReference, $motifDetail, $acteur, $politique, $preuve, $correlation, gmdate('c')]);
    }

    /** @return array<string,mixed>|null */
    private function dernierCycle(string $reference, ?string $date = null): ?array
    {
        $sql = 'SELECT * FROM realm_cycle WHERE realm_reference = ?';
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

    private function etatActuel(string $reference): ?string
    {
        return $this->dernierCycle($reference)['etat_reference'] ?? null;
    }

    private function toucher(string $reference): void
    {
        $this->magasin->prepare('UPDATE realm SET modifie_le = ? WHERE reference = ?')->execute([gmdate('c'), $reference]);
    }

    private function allouerReference(string $type): string
    {
        $prefixe = PolitiqueRealms::PREFIXE[$type] ?? throw new ExceptionRealm("type de référence inconnu : {$type}");
        $this->magasin->prepare(
            'INSERT INTO compteur_reference_realm(type,dernier)
             VALUES(?,0) ON CONFLICT(type) DO NOTHING'
        )->execute([$type]);
        $sql = 'SELECT dernier FROM compteur_reference_realm WHERE type = ?';
        if ((string) $this->magasin->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $sql .= ' FOR UPDATE';
        }
        $st = $this->magasin->prepare($sql);
        $st->execute([$type]);
        $numero = ((int) $st->fetchColumn()) + 1;
        $this->magasin->prepare('UPDATE compteur_reference_realm SET dernier = ? WHERE type = ?')->execute([$numero, $type]);

        return sprintf('%s-%09d', $prefixe, $numero);
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
        $chaine = trim((string) $valeur);

        return $chaine === '' ? null : $chaine;
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
