<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Contrat CTR-01 — Identity Registry (CAP-CORE-001).
 *
 * Les trois lectures historiques sont conservées. Les entités du corpus sont
 * lues depuis l'index reconstructible ; les inscriptions et leurs événements
 * sont lues et écrites dans un registre persistant séparé (INV-73).
 *
 * Aucune colonne de profil, dossier métier, réputation ou jugement n'existe
 * dans le magasin. Le Core connaît l'identité minimale ; les produits gardent
 * l'usage et CAP-CORE-002 garde les dossiers d'organisation.
 */
final class Ctr01
{
    public const CAPACITE = 'CAP-CORE-001';
    public const DERIVE = 'DÉRIVÉ_DU_CORPUS';
    public const INSCRIT = 'INSCRIT_AU_REGISTRE';

    private \PDO $registre;

    /**
     * `$registre` doit être une connexion persistante distincte en
     * exploitation. Le repli sur `$index` maintient les anciens appelants et
     * permet de prouver que même en cohabitation Schema::create() ne supprime
     * pas les tables persistantes.
     *
     * `$organisations` est la connexion au magasin persistant de
     * `CAP-CORE-002` (`Gamad\RegistreOrganisations\Magasin`). Lorsqu'elle est
     * fournie, `resoudreLiensOrganisations()` délègue exclusivement à
     * `organisation_affiliation` et ne lit plus jamais `relation_organisation`
     * (fiche CAP-CORE-002 §14). Lorsqu'elle est absente — la majorité des
     * appelants existants du dépôt, non encore migrés dans ce chantier — la
     * lecture historique de `relation_organisation` reste inchangée : aucun
     * appelant existant n'est cassé par ce chantier. Un appelant qui doit
     * refléter la vérité de CAP-CORE-002 DOIT désormais construire `Ctr01`
     * avec ce quatrième argument.
     *
     * `$produits` est la connexion au magasin persistant de `CAP-CORE-011`
     * (`Gamad\RegistreProduits\Magasin`). Lorsqu'elle est fournie,
     * `produitReconnu()` reconnaît aussi tout produit dont le cycle de vie
     * réel (`produit_cycle`) est `ACTIF` — pas seulement les produits figés
     * dans l'index de baseline. Lorsqu'elle est absente, seule la
     * reconnaissance historique par baseline s'applique : aucun appelant
     * existant n'est cassé par ce chantier.
     */
    public function __construct(
        private \PDO $index,
        ?\PDO $registre = null,
        private ?\PDO $organisations = null,
        private ?\PDO $produits = null,
    ) {
        $this->registre = $registre ?? $index;
        SchemaInscription::migrer($this->registre);
    }

    // ------------------------------------------------------------------
    // Lectures historiques, signatures conservées

    /** @return array<string,mixed>|null */
    public function resoudreIdentite(string $reference, ?string $date = null): ?array
    {
        $st = $this->index->prepare('SELECT reference, type, libelle, source FROM entite WHERE reference = ?');
        $st->execute([$reference]);
        $e = $st->fetch();

        if ($e !== false) {
            $etat = $this->etatDerive($reference, $date);

            return [
                'reference'          => $e['reference'],
                'type'               => $e['type'],
                'libelle'            => $e['libelle'],
                'etat'               => $etat['valeur'] ?? null,
                'date_effet'         => $etat['date_effet'] ?? null,
                'adoption_reference' => $etat['adoption_reference'] ?? null,
                'source'             => $e['source'],
                'regime'             => self::DERIVE,
            ];
        }

        return $this->resoudreInscrite($reference, $date);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreInventaire(?string $type = null): array
    {
        $lignes = [];

        $sql = 'SELECT reference, type, libelle FROM entite';
        $args = [];
        if ($type !== null) {
            $sql .= ' WHERE type = ?';
            $args[] = $type;
        }
        $st = $this->index->prepare($sql);
        $st->execute($args);
        foreach ($st->fetchAll() as $e) {
            $etat = $this->etatDerive((string) $e['reference'], null);
            $lignes[] = [
                'reference' => $e['reference'],
                'type'      => $e['type'],
                'libelle'   => $e['libelle'],
                'etat'      => $etat['valeur'] ?? null,
                'regime'    => self::DERIVE,
            ];
        }

        $sql = 'SELECT reference, type, libelle FROM identite_inscrite';
        $args = [];
        if ($type !== null) {
            $sql .= ' WHERE type = ?';
            $args[] = $type;
        }
        $st = $this->registre->prepare($sql);
        $st->execute($args);
        foreach ($st->fetchAll() as $e) {
            $lignes[] = [
                'reference' => $e['reference'],
                'type'      => $e['type'],
                'libelle'   => $e['libelle'],
                'etat'      => $this->etatInscrit((string) $e['reference'], null),
                'regime'    => self::INSCRIT,
            ];
        }

        usort($lignes, static fn (array $a, array $b): int =>
            [$a['type'], $a['reference']] <=> [$b['type'], $b['reference']]
        );

        return $lignes;
    }

    /** @return list<array<string,mixed>> */
    public function resoudreDenominations(?string $reference = null): array
    {
        $sql = 'SELECT entite_reference, libelle, source FROM denomination';
        $args = [];
        if ($reference !== null) {
            $sql .= ' WHERE entite_reference = ?';
            $args[] = $reference;
        }
        $sql .= ' ORDER BY entite_reference, id';

        $st = $this->index->prepare($sql);
        $st->execute($args);
        $par = [];
        foreach ($st->fetchAll() as $d) {
            $par[$d['entite_reference']][$d['libelle']] = $d['source'];
        }

        $lignes = [];
        foreach ($par as $ref => $libelles) {
            $lignes[] = [
                'reference'  => $ref,
                'libelles'   => array_keys($libelles),
                'sources'    => array_values($libelles),
                'divergente' => count($libelles) > 1,
            ];
        }

        return $lignes;
    }

    // ------------------------------------------------------------------
    // Lectures révisées

    /** @return array<string,mixed>|null */
    public function resoudreRegimeVerite(string $reference): ?array
    {
        $st = $this->index->prepare('SELECT source FROM entite WHERE reference = ?');
        $st->execute([$reference]);
        $e = $st->fetch();
        if ($e !== false) {
            return [
                'reference'       => $reference,
                'regime'          => self::DERIVE,
                'reconstructible' => true,
                'source'          => $e['source'],
            ];
        }

        $st = $this->registre->prepare(
            'SELECT source_inscription FROM identite_inscrite WHERE reference = ?'
        );
        $st->execute([$reference]);
        $i = $st->fetch();

        return $i === false ? null : [
            'reference'       => $reference,
            'regime'          => self::INSCRIT,
            'reconstructible' => false,
            'source'          => $i['source_inscription'],
        ];
    }

    /**
     * Un produit voit uniquement ses propres relations. Il n'existe pas de
     * valeur `null` signifiant « tout voir » : une vue transversale doit être
     * construite par CAP-CORE-004, produit par produit, après autorisation.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreLiensProduits(
        string $reference,
        string $produitAppelant,
        ?string $relationType = null,
        ?string $date = null,
    ): array {
        $sql = 'SELECT * FROM relation_produit
                WHERE identite_reference = ? AND produit_reference = ?';
        $args = [$reference, $produitAppelant];
        if ($relationType !== null) {
            $sql .= ' AND relation_type = ?';
            $args[] = $relationType;
        }
        $sql .= ' ORDER BY date_debut, reference';

        $st = $this->registre->prepare($sql);
        $st->execute($args);
        $jour = $date ?? date('Y-m-d');
        $lignes = [];
        foreach ($st->fetchAll() as $r) {
            if ((string) $r['date_debut'] > $jour) {
                continue;
            }
            $cycle = $this->etatRelation((string) $r['reference'], $jour);
            $lignes[] = [
                'reference'          => $r['reference'],
                'produit_reference'  => $r['produit_reference'],
                'relation_type'      => $r['relation_type'],
                'etat'               => $cycle['etat'],
                'assurance'          => $r['niveau_assurance'],
                'source'             => $r['source'],
                'date_debut'         => $r['date_debut'],
                'date_fin'           => $cycle['date_fin'],
                'classification'     => $r['classification'],
            ];
        }

        return $lignes;
    }

    /** @return list<array<string,mixed>> */
    public function resoudreLiensOrganisations(
        string $reference,
        ?string $organisation = null,
        ?string $relationType = null,
        ?string $date = null,
    ): array {
        if ($this->organisations !== null) {
            return $this->resoudreLiensOrganisationsDepuisCapCore002($reference, $organisation, $relationType, $date);
        }

        $sql = 'SELECT * FROM relation_organisation WHERE identite_reference = ?';
        $args = [$reference];
        if ($organisation !== null) {
            $sql .= ' AND organisation_reference = ?';
            $args[] = $organisation;
        }
        if ($relationType !== null) {
            $sql .= ' AND relation_type = ?';
            $args[] = $relationType;
        }
        $sql .= ' ORDER BY date_debut, reference';

        $st = $this->registre->prepare($sql);
        $st->execute($args);
        $jour = $date ?? date('Y-m-d');
        $lignes = [];
        foreach ($st->fetchAll() as $r) {
            if ((string) $r['date_debut'] > $jour) {
                continue;
            }
            $cycle = $this->etatRelation((string) $r['reference'], $jour);
            $requiertMandat = in_array(
                $r['relation_type'],
                PolitiqueInscription::RELATIONS_A_MANDAT,
                true,
            );
            $lignes[] = [
                'reference'                => $r['reference'],
                'organisation_reference'   => $r['organisation_reference'],
                'relation_type'            => $r['relation_type'],
                'etat'                     => $cycle['etat'],
                'assurance'                => $r['niveau_assurance'],
                'mandat_reference'         => $r['mandat_reference'],
                'mandat_verifie'           => (bool) $r['mandat_verifie'],
                'opposable'                => !$requiertMandat || (bool) $r['mandat_verifie'],
                'source'                   => $r['source'],
                'date_debut'               => $r['date_debut'],
                'date_fin'                 => $cycle['date_fin'],
                'classification'           => $r['classification'],
            ];
        }

        return $lignes;
    }

    /** @return array<string,mixed>|null */
    public function resoudreIdentiteDepuisSujetProduit(
        string $produit,
        string $sujetLocalOpaque,
        ?string $date = null,
    ): ?array {
        $st = $this->registre->prepare(
            'SELECT identite_reference, reference FROM relation_produit
             WHERE produit_reference = ? AND sujet_local_opaque = ?
             ORDER BY date_debut DESC, reference DESC'
        );
        $st->execute([$produit, $sujetLocalOpaque]);
        $jour = $date ?? date('Y-m-d');
        foreach ($st->fetchAll() as $r) {
            if ($this->etatRelation((string) $r['reference'], $jour)['etat'] !== 'ACTIVE') {
                continue;
            }
            $reference = (string) $r['identite_reference'];
            $identite = $this->resoudreIdentite($reference);

            return [
                'reference' => $reference,
                'etat'      => $identite['etat'] ?? null,
                'assurance' => $this->resoudreAssurance($reference)['niveau'],
            ];
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function resoudreAssurance(string $reference, ?string $date = null): array
    {
        $sql = 'SELECT niveau, preuve_reference, source, producteur, date_effet
                FROM evenement_assurance_identite WHERE identite_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet, id';
        $st = $this->registre->prepare($sql);
        $st->execute($args);
        $evenements = $st->fetchAll();
        $dernier = $evenements === [] ? null : $evenements[count($evenements) - 1];

        return [
            'reference'  => $reference,
            'niveau'     => $dernier['niveau'] ?? 'A0',
            'source'     => $dernier['source'] ?? null,
            'date_effet' => $dernier['date_effet'] ?? null,
            'evenements' => array_map(
                static fn (array $e): array => [
                    'niveau'            => $e['niveau'],
                    'preuve_reference'  => $e['preuve_reference'],
                    'source'            => $e['source'],
                    'producteur'        => $e['producteur'],
                    'date_effet'        => $e['date_effet'],
                ],
                $evenements,
            ),
        ];
    }

    /** @return array<string,mixed> */
    public function resoudreEtatUtilisable(string $reference, string $finalite): array
    {
        $exige = PolitiqueInscription::FINALITES[$finalite] ?? null;
        if ($exige === null) {
            return [
                'utilisable' => false,
                'motif' => 'FINALITE_INCONNUE',
                'exigences_non_satisfaites' => ["finalité `{$finalite}` hors liste close"],
            ];
        }

        $identite = $this->resoudreIdentite($reference);
        if ($identite === null) {
            return [
                'utilisable' => false,
                'motif' => 'IDENTITE_INCONNUE',
                'exigences_non_satisfaites' => ['aucune identité ne porte cette référence'],
            ];
        }

        $manque = [];
        if (!in_array($identite['etat'], ['ACTIVE', 'VERIFIEE'], true)) {
            $manque[] = 'état incompatible avec la finalité';
        }
        $niveau = (string) $this->resoudreAssurance($reference)['niveau'];
        if (!PolitiqueInscription::auMoins($niveau, $exige)) {
            $manque[] = "assurance `{$niveau}` inférieure à `{$exige}`";
        }
        if (in_array($finalite, PolitiqueInscription::FINALITES_A_MANDAT, true)) {
            $avecMandat = array_filter(
                $this->resoudreLiensOrganisations($reference),
                static fn (array $r): bool => $r['etat'] === 'ACTIVE'
                    && $r['opposable'] === true
                    && in_array($r['relation_type'], PolitiqueInscription::RELATIONS_A_MANDAT, true),
            );
            if ($avecMandat === []) {
                $manque[] = 'aucun mandat vérifié par CAP-CORE-003';
            }
        }

        return [
            'utilisable' => $manque === [],
            'motif' => $manque === [] ? 'SATISFAITE' : 'EXIGENCES_NON_SATISFAITES',
            'finalite' => $finalite,
            'assurance' => $niveau,
            'exigences_non_satisfaites' => array_values($manque),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function resoudreRapprochementsProposes(?string $reference = null): array
    {
        $sql = 'SELECT * FROM rapprochement_identite';
        $args = [];
        if ($reference !== null) {
            $sql .= ' WHERE reference_a = ? OR reference_b = ?';
            $args = [$reference, $reference];
        }
        $sql .= ' ORDER BY date_effet, reference';
        $st = $this->registre->prepare($sql);
        $st->execute($args);

        return array_map(static fn (array $r): array => [
            'reference'     => $r['reference'],
            'reference_a'   => $r['reference_a'],
            'reference_b'   => $r['reference_b'],
            'qualification' => $r['qualification'],
            'preuves'       => array_values(array_filter(explode('|', (string) $r['preuves']))),
            'etat'          => $r['etat'],
            'decideur'      => $r['decideur'],
            'date_effet'    => $r['date_effet'],
        ], $st->fetchAll());
    }

    /**
     * Diagnostic interne utilisé par la garde et par l'exploitation. Il ne
     * répare jamais silencieusement une falsification.
     *
     * @return array{valide:bool,ecarts:list<string>}
     */
    public function verifierIntegritePersistante(): array
    {
        $ecarts = [];

        $st = $this->registre->query(
            "SELECT r.reference FROM rapprochement_identite r
             WHERE r.etat = 'VALIDEE' AND (r.decideur IS NULL OR r.decideur = '')"
        );
        foreach ($st->fetchAll() as $r) {
            $ecarts[] = "rapprochement {$r['reference']} validé sans décideur";
        }

        $st = $this->registre->query(
            'SELECT i.reference, a.niveau
             FROM identite_inscrite i
             JOIN evenement_assurance_identite a ON a.identite_reference = i.reference
             WHERE i.provisoire = 1'
        );
        foreach ($st->fetchAll() as $r) {
            if (PolitiqueInscription::auMoins((string) $r['niveau'], 'A2')) {
                $ecarts[] = "identité provisoire {$r['reference']} portée au niveau {$r['niveau']}";
            }
        }

        foreach (['relation_produit', 'relation_organisation'] as $table) {
            $st = $this->registre->query(
                "SELECT r.reference, r.date_debut, e.date_effet
                 FROM {$table} r
                 JOIN evenement_relation_identite e ON e.relation_reference = r.reference
                 WHERE e.evenement_type = 'CLOTURE' AND e.date_effet < r.date_debut"
            );
            foreach ($st->fetchAll() as $r) {
                $ecarts[] = "relation {$r['reference']} close avant sa date de début";
            }
        }

        return ['valide' => $ecarts === [], 'ecarts' => $ecarts];
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées

    /**
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function inscrireIdentite(array $dossier): array
    {
        $controle = $this->controlerInscription($dossier);
        if (isset($controle['refus'])) {
            return $controle;
        }

        $canal = (string) $dossier['canal'];
        $type = (string) $dossier['type'];
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $source = (string) $dossier['source'];
        $preuve = (string) $dossier['preuve'];
        $libelle = trim((string) $dossier['libelle']);
        $classification = (string) ($dossier['classification'] ?? 'INTERNE');
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $provisoire = (bool) ($dossier['provisoire'] ?? false);
        $etat = $provisoire ? 'PROVISOIRE' : 'ACTIVE';
        $niveau = (string) PolitiqueInscription::CANAUX[$canal]['assurance'];
        if ($provisoire && PolitiqueInscription::auMoins($niveau, 'A2')) {
            $niveau = PolitiqueInscription::PLAFOND_PROVISOIRE;
        }

        return $this->transaction($this->registre, function () use (
            $type,
            $libelle,
            $provisoire,
            $canal,
            $producteur,
            $politique,
            $source,
            $preuve,
            $classification,
            $date,
            $etat,
            $niveau,
        ): array {
            $reference = $this->allouerReference($type);
            $this->registre->prepare(
                'INSERT INTO identite_inscrite
                 (reference,type,libelle,regime,provisoire,canal,producteur,
                  politique_inscription,source_inscription,preuve_reference,
                  classification,date_creation)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $type, $libelle, self::INSCRIT, $provisoire ? 1 : 0,
                $canal, $producteur, $politique, $source, $preuve,
                $classification, $date,
            ]);
            $this->inscrireEvenementIdentite(
                $reference, 'CREATION', null, $etat, $source, $preuve,
                $politique, $producteur, $date,
            );
            $this->registre->prepare(
                'INSERT INTO evenement_assurance_identite
                 (identite_reference,niveau,preuve_reference,source,producteur,date_effet)
                 VALUES(?,?,?,?,?,?)'
            )->execute([$reference, $niveau, $preuve, $source, $producteur, $date]);

            return [
                'reference' => $reference,
                'type' => $type,
                'etat' => $etat,
                'assurance' => $niveau,
                'regime' => self::INSCRIT,
            ];
        });
    }

    /**
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function rattacherProduit(
        string $reference,
        string $produit,
        string $relationType,
        array $dossier,
    ): array {
        if (!$this->estType($reference, null)) {
            return $this->refus('IDENTITE_INCONNUE', "identité `{$reference}` inconnue");
        }
        if (!$this->estType($produit, 'produit')) {
            return $this->refus('PRODUIT_INCONNU', "produit `{$produit}` inconnu");
        }
        if (!in_array($relationType, PolitiqueInscription::RELATIONS_PRODUIT, true)) {
            return $this->refus('RELATION_NON_AUTORISEE', "relation `{$relationType}` hors liste close");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $producteur = (string) $dossier['producteur'];
        if ($producteur !== $produit && $producteur !== PolitiqueInscription::AUTORITE_INSCRIPTION) {
            return $this->refus('PRODUCTEUR_INCOMPETENT', 'seul le produit concerné ou l’autorité peut rattacher');
        }

        $relation = 'LNK-PRD-' . strtoupper(bin2hex(random_bytes(8)));
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $classification = (string) ($dossier['classification'] ?? 'INTERNE');
        if (!in_array($classification, PolitiqueInscription::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        $assurance = (string) $this->resoudreAssurance($reference, $date)['niveau'];

        return $this->transaction($this->registre, function () use (
            $relation, $reference, $produit, $relationType, $dossier,
            $date, $classification, $assurance, $producteur,
        ): array {
            $this->registre->prepare(
                'INSERT INTO relation_produit
                 (reference,identite_reference,produit_reference,relation_type,
                  sujet_local_opaque,niveau_assurance,source,preuve_reference,
                  politique_inscription,producteur,date_debut,classification)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $relation, $reference, $produit, $relationType,
                $dossier['sujet_local_opaque'] ?? null, $assurance,
                $dossier['source'], $dossier['preuve'], $dossier['politique'],
                $producteur, $date, $classification,
            ]);
            $this->inscrireEvenementRelation(
                $relation, 'PRODUIT', 'RATTACHEMENT', 'ACTIVE',
                (string) $dossier['source'], (string) $dossier['preuve'],
                $producteur, $date,
            );
            $etat = $this->etatCourant($reference);
            $this->inscrireEvenementIdentite(
                $reference, 'RATTACHEMENT_PRODUIT', $etat, $etat ?? 'ACTIVE',
                (string) $dossier['source'], (string) $dossier['preuve'],
                (string) $dossier['politique'], $producteur, $date,
            );

            return [
                'reference' => $relation,
                'identite_reference' => $reference,
                'produit_reference' => $produit,
                'relation_type' => $relationType,
                'etat' => 'ACTIVE',
                'assurance' => $assurance,
                'date_debut' => $date,
            ];
        });
    }

    /**
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function rattacherOrganisation(
        string $reference,
        string $organisation,
        string $relationType,
        array $dossier,
    ): array {
        if ($this->organisations !== null) {
            // Frontière corrigée (fiche CAP-CORE-002 §14) : une fois ce Ctr01
            // raccordé au registre des organisations, l'écriture d'une
            // affiliation ne passe plus par l'ancienne table. Elle relève de
            // `RegistreOrganisations::proposerAffiliation()` /
            // `activerAffiliation()`, seules à savoir gouverner une unité, une
            // classification et un cycle d'affiliation complets.
            return $this->refus(
                'DEPRECIE_CAP_CORE_002',
                'l’écriture de relations organisationnelles depuis CAP-CORE-001 est dépréciée ; '
                . 'utiliser RegistreOrganisations::proposerAffiliation() (CAP-CORE-002)',
            );
        }
        if (!$this->estType($reference, null)) {
            return $this->refus('IDENTITE_INCONNUE', "identité `{$reference}` inconnue");
        }
        if (!$this->estType($organisation, 'organisation')) {
            return $this->refus('ORGANISATION_INCONNUE', "organisation `{$organisation}` inconnue");
        }
        if (!in_array($relationType, PolitiqueInscription::RELATIONS_ORGANISATION, true)) {
            return $this->refus('RELATION_NON_AUTORISEE', "relation `{$relationType}` hors liste close");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $producteur = (string) $dossier['producteur'];
        if ($producteur !== $organisation && $producteur !== PolitiqueInscription::AUTORITE_INSCRIPTION) {
            return $this->refus('PRODUCTEUR_INCOMPETENT', 'seule l’organisation concernée ou l’autorité peut rattacher');
        }

        $relation = 'LNK-ORG-' . strtoupper(bin2hex(random_bytes(8)));
        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $classification = (string) ($dossier['classification'] ?? 'INTERNE');
        if (!in_array($classification, PolitiqueInscription::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        $mandat = isset($dossier['mandat_reference']) && $dossier['mandat_reference'] !== ''
            ? (string) $dossier['mandat_reference'] : null;
        // CAP-CORE-001 conserve éventuellement la référence fournie, mais ne
        // certifie jamais lui-même un pouvoir de représentation. Une future
        // intégration explicite avec CAP-CORE-003 pourra produire cette preuve.
        $mandatVerifie = false;
        $assurance = (string) $this->resoudreAssurance($reference, $date)['niveau'];

        return $this->transaction($this->registre, function () use (
            $relation, $reference, $organisation, $relationType, $dossier,
            $date, $classification, $mandat, $mandatVerifie, $assurance, $producteur,
        ): array {
            $this->registre->prepare(
                'INSERT INTO relation_organisation
                 (reference,identite_reference,organisation_reference,relation_type,
                  mandat_reference,mandat_verifie,niveau_assurance,source,
                  preuve_reference,politique_inscription,producteur,date_debut,classification)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $relation, $reference, $organisation, $relationType, $mandat,
                $mandatVerifie ? 1 : 0, $assurance, $dossier['source'],
                $dossier['preuve'], $dossier['politique'], $producteur,
                $date, $classification,
            ]);
            $this->inscrireEvenementRelation(
                $relation, 'ORGANISATION', 'RATTACHEMENT', 'ACTIVE',
                (string) $dossier['source'], (string) $dossier['preuve'],
                $producteur, $date,
            );
            $etat = $this->etatCourant($reference);
            $this->inscrireEvenementIdentite(
                $reference, 'RATTACHEMENT_ORGANISATION', $etat, $etat ?? 'ACTIVE',
                (string) $dossier['source'], (string) $dossier['preuve'],
                (string) $dossier['politique'], $producteur, $date,
            );

            return [
                'reference' => $relation,
                'identite_reference' => $reference,
                'organisation_reference' => $organisation,
                'relation_type' => $relationType,
                'etat' => 'ACTIVE',
                'assurance' => $assurance,
                'mandat_reference' => $mandat,
                'mandat_verifie' => $mandatVerifie,
                'opposable' => !in_array(
                    $relationType,
                    PolitiqueInscription::RELATIONS_A_MANDAT,
                    true,
                ) || $mandatVerifie,
            ];
        });
    }

    /**
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function cloreRelationProduit(
        string $relation,
        string $dateFin,
        array $dossier,
    ): array {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $st = $this->registre->prepare('SELECT * FROM relation_produit WHERE reference = ?');
        $st->execute([$relation]);
        $r = $st->fetch();
        if ($r === false) {
            return $this->refus('RELATION_INTROUVABLE', "relation `{$relation}` inconnue");
        }
        if (!$this->dateValide($dateFin)) {
            return $this->refus('DATE_INVALIDE', 'date_fin doit suivre YYYY-MM-DD');
        }
        $producteur = (string) $dossier['producteur'];
        if ($producteur !== $r['produit_reference']
            && $producteur !== PolitiqueInscription::AUTORITE_INSCRIPTION) {
            return $this->refus('PRODUCTEUR_INCOMPETENT', 'seul le produit concerné ou l’autorité peut clore');
        }
        if ($dateFin < $r['date_debut']) {
            return $this->refus('DATE_INVALIDE', 'la clôture précède le rattachement');
        }
        if ($this->etatRelation($relation, $dateFin)['etat'] !== 'ACTIVE') {
            return $this->refus('RELATION_DEJA_CLOSE', 'la relation n’est plus active');
        }

        return $this->transaction($this->registre, function () use (
            $relation, $dateFin, $dossier, $r, $producteur,
        ): array {
            $this->inscrireEvenementRelation(
                $relation, 'PRODUIT', 'CLOTURE', 'CLOSE',
                (string) $dossier['source'], (string) $dossier['preuve'],
                $producteur, $dateFin,
            );
            $etat = $this->etatCourant((string) $r['identite_reference']);
            $this->inscrireEvenementIdentite(
                (string) $r['identite_reference'], 'RETRAIT_PRODUIT',
                $etat, $etat ?? 'ACTIVE', (string) $dossier['source'],
                (string) $dossier['preuve'], (string) $dossier['politique'],
                $producteur, $dateFin,
            );

            return [
                'reference' => $relation,
                'relation_etat' => 'CLOSE',
                'date_fin' => $dateFin,
                'identite_reference' => $r['identite_reference'],
                'identite_etat' => $etat,
            ];
        });
    }

    /**
     * @param list<string> $preuves
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function proposerRapprochement(
        string $referenceA,
        string $referenceB,
        array $preuves,
        array $dossier,
    ): array {
        if ($referenceA === $referenceB) {
            return $this->refus('REFERENCES_IDENTIQUES', 'deux références distinctes sont requises');
        }
        if (!$this->estType($referenceA, null) || !$this->estType($referenceB, null)) {
            return $this->refus('IDENTITE_INCONNUE', 'une des références est inconnue');
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $qualification = (string) ($dossier['qualification'] ?? 'CORRESPONDANCE_POSSIBLE');
        if (!in_array($qualification, PolitiqueInscription::QUALIFICATIONS_RAPPROCHEMENT, true)) {
            return $this->refus('QUALIFICATION_INCONNUE', 'qualification hors liste close');
        }
        if ($qualification === 'AUCUNE_CORRESPONDANCE') {
            return [
                'reference_a' => $referenceA,
                'reference_b' => $referenceB,
                'qualification' => $qualification,
                'etat' => 'AUCUNE_ACTION',
            ];
        }
        $preuves = array_values(array_filter(
            array_map(static fn (mixed $p): string => trim((string) $p), $preuves),
        ));
        if ($preuves === []) {
            return $this->refus('PREUVES_ABSENTES', 'une probabilité seule ne suffit pas');
        }

        $reference = 'RAP-' . strtoupper(bin2hex(random_bytes(8)));
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }
        $this->registre->prepare(
            'INSERT INTO rapprochement_identite
             (reference,reference_a,reference_b,qualification,preuves,etat,
              decideur,source,politique_inscription,producteur,date_effet)
             VALUES(?,?,?,?,?,\'VALIDATION_REQUISE\',NULL,?,?,?,?)'
        )->execute([
            $reference, $referenceA, $referenceB, $qualification,
            implode('|', $preuves), $dossier['source'], $dossier['politique'],
            $dossier['producteur'], $date,
        ]);

        return [
            'reference' => $reference,
            'reference_a' => $referenceA,
            'reference_b' => $referenceB,
            'qualification' => $qualification,
            'preuves' => $preuves,
            'etat' => 'VALIDATION_REQUISE',
            'decideur' => null,
        ];
    }

    /** @return array<string,mixed> */
    public function inscrireEvenementAssurance(
        string $reference,
        string $niveau,
        string $preuve,
        string $source,
        ?string $date = null,
        string $producteur = 'CAP-CORE-005',
    ): array {
        if (!isset(PolitiqueInscription::ASSURANCE[$niveau])) {
            return $this->refus('NIVEAU_INCONNU', "niveau `{$niveau}` hors échelle A0–A3");
        }
        if (!$this->estType($reference, null)) {
            return $this->refus('IDENTITE_INCONNUE', "identité `{$reference}` inconnue");
        }
        if ($preuve === '' || $source === '') {
            return $this->refus('PREUVE_ABSENTE', 'preuve et source sont obligatoires');
        }
        if (!in_array($producteur, ['CAP-CORE-005', PolitiqueInscription::AUTORITE_INSCRIPTION], true)) {
            return $this->refus('PRODUCTEUR_INCOMPETENT', 'l’assurance relève de CAP-CORE-005 ou de l’autorité');
        }
        $identite = $this->resoudreInscrite($reference, null);
        if (($identite['provisoire'] ?? false)
            && PolitiqueInscription::auMoins($niveau, 'A2')) {
            return $this->refus('ASSURANCE_PROVISOIRE_DEPASSEE', 'une identité provisoire reste au plus A1');
        }

        $jour = $date ?? date('Y-m-d');
        if (!$this->dateValide($jour)) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }
        $this->registre->prepare(
            'INSERT INTO evenement_assurance_identite
             (identite_reference,niveau,preuve_reference,source,producteur,date_effet)
             VALUES(?,?,?,?,?,?)'
        )->execute([$reference, $niveau, $preuve, $source, $producteur, $jour]);

        return $this->resoudreAssurance($reference);
    }

    // ------------------------------------------------------------------
    // Délégation CAP-CORE-002 (fiche §14)

    /**
     * Projection compatible de `resoudreLiensOrganisations()`, entièrement
     * fondée sur `organisation_affiliation` / `organisation_affiliation_cycle`
     * de CAP-CORE-002. `organisation_reference` reste, comme dans l'ancienne
     * lecture, la référence d'IDENTITÉ (`IDN-ORG-*`) de l'organisation — pas
     * la référence de fiche `ORG-GAMAD-*`, interne à CAP-CORE-002 — afin que
     * les appelants existants n'aient rien à changer. `mandat_verifie` et
     * `opposable` ne sont plus jamais dérivés d'un booléen recopié : ils
     * restent `false` ici, faute de date d'acte à vérifier — un appelant qui a
     * besoin d'une réponse opposable doit appeler
     * `RegistreOrganisations::verifierRepresentation()`, seule habilitée à
     * consulter CAP-CORE-003.
     *
     * @return list<array<string,mixed>>
     */
    private function resoudreLiensOrganisationsDepuisCapCore002(
        string $reference,
        ?string $organisationIdentite,
        ?string $relationType,
        ?string $date,
    ): array {
        $jour = $date ?? date('Y-m-d');

        $sql = "SELECT a.*, o.identite_reference AS organisation_identite_reference
                FROM organisation_affiliation a
                JOIN organisation o ON o.reference = a.organisation_reference
                WHERE a.identite_reference = ?";
        $args = [$reference];
        if ($organisationIdentite !== null) {
            $sql .= ' AND o.identite_reference = ?';
            $args[] = $organisationIdentite;
        }
        if ($relationType !== null) {
            $sql .= ' AND a.type_affiliation_reference = ?';
            $args[] = $relationType;
        }
        $sql .= ' ORDER BY a.date_debut, a.reference';

        $st = $this->organisations->prepare($sql);
        $st->execute($args);
        $lignes = [];
        foreach ($st->fetchAll() as $a) {
            if ((string) $a['date_debut'] > $jour) {
                continue;
            }
            $etat = $this->etatAffiliationCapCore002((string) $a['reference'], $jour);
            $lignes[] = [
                'reference' => $a['reference'],
                'organisation_reference' => $a['organisation_identite_reference'],
                'relation_type' => $a['type_affiliation_reference'],
                'etat' => $etat,
                'assurance' => $a['niveau_assurance_reference'],
                'mandat_reference' => null,
                'mandat_verifie' => false,
                'opposable' => false,
                'source' => $a['source_reference'],
                'date_debut' => $a['date_debut'],
                'date_fin' => $etat === 'CLOSE' || $etat === 'REJETEE' ? $jour : null,
                'classification' => $a['classification_reference'],
            ];
        }

        return $lignes;
    }

    private function etatAffiliationCapCore002(string $reference, string $date): string
    {
        $st = $this->organisations->prepare(
            'SELECT etat_reference FROM organisation_affiliation_cycle
             WHERE affiliation_reference = ? AND date_effet <= ?
             ORDER BY date_effet DESC, id DESC LIMIT 1'
        );
        $st->execute([$reference, $date]);
        $etat = $st->fetchColumn();

        return $etat === false ? 'INCONNU' : (string) $etat;
    }

    // ------------------------------------------------------------------
    // Internes

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    private function controlerInscription(array $dossier): array
    {
        foreach (['canal', 'type', 'producteur', 'politique', 'source', 'preuve', 'libelle'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $canal = (string) $dossier['canal'];
        $type = (string) $dossier['type'];
        $producteur = (string) $dossier['producteur'];
        $regle = PolitiqueInscription::CANAUX[$canal] ?? null;
        if ($regle === null) {
            return $this->refus('CANAL_NON_AUTORISE', "canal `{$canal}` hors politique");
        }
        if (!in_array($type, $regle['types'], true)) {
            return $this->refus('TYPE_NON_AUTORISE', "le canal `{$canal}` n’inscrit pas `{$type}`");
        }
        if (in_array($canal, PolitiqueInscription::CANAUX_RESERVES, true)
            && $producteur !== PolitiqueInscription::AUTORITE_INSCRIPTION) {
            return $this->refus('CANAL_RESERVE', 'ce canal relève de l’autorité');
        }
        if ($canal === 'PRODUIT_RECONNU' && !$this->produitReconnu($producteur)) {
            return $this->refus('PRODUCTEUR_INCOMPETENT', 'le producteur n’est pas un produit reconnu');
        }
        if ($canal === 'ORGANISATION_RECONNUE' && !$this->estType($producteur, 'organisation')) {
            return $this->refus('PRODUCTEUR_INCOMPETENT', 'le producteur n’est pas une organisation inscrite');
        }
        $classification = (string) ($dossier['classification'] ?? 'INTERNE');
        if (!in_array($classification, PolitiqueInscription::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        if (isset($dossier['date']) && !$this->dateValide((string) $dossier['date'])) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
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

    private function estType(string $reference, ?string $type): bool
    {
        $i = $this->resoudreIdentite($reference);

        return $i !== null && ($type === null || $i['type'] === $type);
    }

    /**
     * Deux sources indépendantes, jamais fusionnées en une seule table :
     * la baseline historique (index reconstructible) et le cycle de vie réel
     * gouverné par CAP-CORE-011. Aucune des deux ne prime sur l'autre — un
     * produit est reconnu dès que l'une l'atteste.
     */
    private function produitReconnu(string $reference): bool
    {
        return $this->produitReconnuParBaseline($reference)
            || $this->produitReconnuParCapCore011($reference);
    }

    private function produitReconnuParBaseline(string $reference): bool
    {
        $produit = $this->resoudreIdentite($reference);

        return $produit !== null
            && $produit['type'] === 'produit'
            && is_string($produit['etat'])
            && str_contains($produit['etat'], 'RECONNU');
    }

    /**
     * CAP-CORE-011 (`Gamad\RegistreProduits`) reste l'unique source du cycle
     * de vie réel d'un produit ; CAP-CORE-001 ne le recopie jamais dans son
     * propre magasin. Cette lecture interroge directement `produit_cycle`,
     * comme `resoudreLiensOrganisationsDepuisCapCore002()` interroge
     * CAP-CORE-002 sans dupliquer ses tables. Seul l'état `ACTIF` reconnaît
     * le producteur : `PREPARATION`, `SUSPENDU` et `RETIRE` sont refusés.
     */
    private function produitReconnuParCapCore011(string $reference): bool
    {
        if ($this->produits === null) {
            return false;
        }

        $st = $this->produits->prepare(
            'SELECT etat FROM produit_cycle
             WHERE produit_reference = ?
             ORDER BY date_effet DESC, id DESC LIMIT 1'
        );
        $st->execute([$reference]);
        $etat = $st->fetchColumn();

        return $etat === 'ACTIF';
    }

    /** @return array<string,mixed>|null */
    private function etatDerive(string $reference, ?string $date): ?array
    {
        $sql = 'SELECT valeur, date_effet, adoption_reference
                FROM etat_entite WHERE entite_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';
        $st = $this->index->prepare($sql);
        $st->execute($args);

        return $st->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    private function resoudreInscrite(string $reference, ?string $date): ?array
    {
        $st = $this->registre->prepare('SELECT * FROM identite_inscrite WHERE reference = ?');
        $st->execute([$reference]);
        $i = $st->fetch();
        if ($i === false) {
            return null;
        }

        return [
            'reference'             => $i['reference'],
            'type'                  => $i['type'],
            'libelle'               => $i['libelle'],
            'etat'                  => $this->etatInscrit($reference, $date),
            'date_effet'            => $i['date_creation'],
            'adoption_reference'    => null,
            'source'                => $i['source_inscription'],
            'regime'                => self::INSCRIT,
            'provisoire'            => (bool) $i['provisoire'],
            'canal'                 => $i['canal'],
            'politique_inscription' => $i['politique_inscription'],
            'producteur'            => $i['producteur'],
            'preuve_reference'      => $i['preuve_reference'],
            'classification'        => $i['classification'],
        ];
    }

    private function etatInscrit(string $reference, ?string $date): ?string
    {
        $sql = 'SELECT etat_apres FROM evenement_cycle_identite
                WHERE identite_reference = ?
                AND evenement_type NOT IN
                    (\'RATTACHEMENT_PRODUIT\',\'RETRAIT_PRODUIT\',
                     \'RATTACHEMENT_ORGANISATION\',\'RETRAIT_ORGANISATION\')';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';
        $st = $this->registre->prepare($sql);
        $st->execute($args);
        $etat = $st->fetchColumn();

        return $etat === false ? null : (string) $etat;
    }

    private function etatCourant(string $reference): ?string
    {
        return $this->resoudreIdentite($reference)['etat'] ?? null;
    }

    /** @return array{etat:string,date_fin:?string} */
    private function etatRelation(string $reference, string $date): array
    {
        $st = $this->registre->prepare(
            'SELECT etat, evenement_type, date_effet
             FROM evenement_relation_identite
             WHERE relation_reference = ? AND date_effet <= ?
             ORDER BY date_effet DESC, id DESC LIMIT 1'
        );
        $st->execute([$reference, $date]);
        $e = $st->fetch();

        return $e === false
            ? ['etat' => 'INCONNU', 'date_fin' => null]
            : [
                'etat' => (string) $e['etat'],
                'date_fin' => $e['evenement_type'] === 'CLOTURE'
                    ? (string) $e['date_effet'] : null,
            ];
    }

    private function inscrireEvenementIdentite(
        string $reference,
        string $type,
        ?string $avant,
        string $apres,
        string $source,
        string $preuve,
        string $politique,
        string $acteur,
        string $date,
    ): void {
        if (!in_array($type, PolitiqueInscription::EVENEMENTS, true)) {
            throw new \LogicException("événement `{$type}` hors liste close");
        }
        $this->registre->prepare(
            'INSERT INTO evenement_cycle_identite
             (identite_reference,evenement_type,etat_avant,etat_apres,source,
              preuve_reference,politique_inscription,acteur_reference,date_effet)
             VALUES(?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference, $type, $avant, $apres, $source, $preuve,
            $politique, $acteur, $date,
        ]);
    }

    private function inscrireEvenementRelation(
        string $reference,
        string $categorie,
        string $type,
        string $etat,
        string $source,
        string $preuve,
        string $producteur,
        string $date,
    ): void {
        $this->registre->prepare(
            'INSERT INTO evenement_relation_identite
             (relation_reference,categorie,evenement_type,etat,source,
              preuve_reference,producteur,date_effet)
             VALUES(?,?,?,?,?,?,?,?)'
        )->execute([
            $reference, $categorie, $type, $etat, $source,
            $preuve, $producteur, $date,
        ]);
    }

    private function allouerReference(string $type): string
    {
        $prefixe = PolitiqueInscription::PREFIXE[$type]
            ?? PolitiqueInscription::PREFIXE['INDETERMINE'];
        $this->registre->prepare(
            'INSERT INTO compteur_reference_identite(type,dernier)
             VALUES(?,0) ON CONFLICT(type) DO NOTHING'
        )->execute([$type]);
        $sql = 'SELECT dernier FROM compteur_reference_identite WHERE type = ?';
        if ((string) $this->registre->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $sql .= ' FOR UPDATE';
        }
        $st = $this->registre->prepare($sql);
        $st->execute([$type]);
        $numero = ((int) $st->fetchColumn()) + 1;
        $this->registre->prepare(
            'UPDATE compteur_reference_identite SET dernier = ? WHERE type = ?'
        )->execute([$numero, $type]);

        return sprintf('%s-%09d', $prefixe, $numero);
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
    private function transaction(\PDO $pdo, callable $operation): mixed
    {
        $propre = !$pdo->inTransaction();
        if ($propre) {
            $pdo->beginTransaction();
        }
        try {
            $resultat = $operation();
            if ($propre) {
                $pdo->commit();
            }

            return $resultat;
        } catch (\Throwable $e) {
            if ($propre && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
