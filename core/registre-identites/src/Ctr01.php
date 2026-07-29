<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Contrat CTR-01 — Identity Registry (CAP-CORE-001).
 *
 * Conception initiale adoptée par ADOPTION-0038 ; loi révisée adoptée par
 * ADOPTION-0064 ; politique d'inscription et échelle d'assurance arrêtées par
 * ADOPTION-0066.
 *
 * ------------------------------------------------------------------------
 * CE QUE LA RÉVISION A CHANGÉ, ET CE QU'ELLE N'A PAS CHANGÉ
 * ------------------------------------------------------------------------
 *
 * Les trois opérations d'origine — `resoudreIdentite`, `resoudreInventaire`,
 * `resoudreDenominations` — conservent leur signature et leur sémantique.
 * L'évolution est strictement additive (ADOPTION-0066, Art. 215) : la famille
 * `CTR-01` garde son numéro, aucun consommateur n'est affecté.
 *
 * Deux dispositions de la conception initiale sont dépassées, et deux
 * seulement : son Article 11 — « créer une identité demeure un acte signé » —
 * et son `M-19`. L'acte signé porte désormais sur la POLITIQUE d'inscription.
 *
 * INV-19 demeure entier. Le périmètre s'ouvre aux personnes, organisations et
 * acteurs des produits ; il ne s'ouvre à AUCUNE donnée nouvelle sur eux. Il
 * n'existe toujours aucune colonne de profil, de contenu, de dossier métier,
 * de réputation ni de jugement — donc aucune opération ne peut en restituer.
 *
 * ------------------------------------------------------------------------
 * INV-73 — LES DEUX RÉGIMES
 * ------------------------------------------------------------------------
 *
 * `DÉRIVÉ_DU_CORPUS` : les entités que le corpus déclare. Le corpus l'emporte,
 * toujours ; une divergence est un défaut de la base (INV-5).
 *
 * `INSCRIT_AU_REGISTRE` : les entités qu'aucun fichier ne déclare. Le registre
 * est leur source. Elles vivent dans un magasin que l'ingestion n'atteint
 * jamais (voir SchemaInscription).
 *
 * Aucune opération ne convertit un régime en l'autre.
 */
final class Ctr01
{
    /**
     * La capacité souveraine que ce module sert (INV-41).
     *
     * Une famille de contrat peut servir deux capacités — `CTR-10` sert
     * l'audit et l'intégrité. Le numéro de famille ne suffit donc pas à
     * rattacher un module ; le module le déclare lui-même.
     */
    public const CAPACITE = 'CAP-CORE-001';

    public const DERIVE   = 'DÉRIVÉ_DU_CORPUS';
    public const INSCRIT  = 'INSCRIT_AU_REGISTRE';

    public function __construct(
        private \PDO $pdo,
    ) {
    }

    // ==================================================================
    // LECTURES D'ORIGINE — signatures et sémantique inchangées
    // ==================================================================

    /**
     * Résout une entité et son état, éventuellement à une date passée.
     * Une entité dissoute demeure consultable (INV-21).
     *
     * Cherche d'abord l'index dérivé, puis le magasin d'inscription. L'ordre
     * n'est pas indifférent : une entité que le corpus déclare est TOUJOURS
     * restituée depuis le corpus (INV-5), et le magasin ne peut pas la couvrir.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreIdentite(string $reference, ?string $date = null): ?array
    {
        $st = $this->pdo->prepare('SELECT reference, type, libelle, source FROM entite WHERE reference = ?');
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

    /**
     * Inventaire des entités connues, éventuellement d'un seul type.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreInventaire(?string $type = null): array
    {
        $sql = 'SELECT reference, type, libelle FROM entite';
        $args = [];
        if ($type !== null) {
            $sql .= ' WHERE type = ?';
            $args[] = $type;
        }
        $sql .= ' ORDER BY type, reference';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);

        $lignes = [];
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
        $sql .= ' ORDER BY type, reference';

        $st = $this->pdo->prepare($sql);
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

        return $lignes;
    }

    /**
     * Dénominations portées par une même référence dans le corpus.
     *
     * Le service SIGNALE les divergences, il ne les tranche pas : retenir une
     * dénomination canonique est une qualification, réservée à l'autorité
     * (ADOPTION-0037, Art. 3). `divergente` vaut vrai dès qu'une référence
     * porte plus d'une dénomination.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreDenominations(?string $reference = null): array
    {
        $sql = 'SELECT entite_reference, libelle, source FROM denomination';
        $args = [];
        if ($reference !== null) {
            $sql .= ' WHERE entite_reference = ?';
            $args[] = $reference;
        }
        $sql .= ' ORDER BY entite_reference, id';

        $st = $this->pdo->prepare($sql);
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

    // ==================================================================
    // LECTURES AJOUTÉES PAR LA LOI RÉVISÉE
    // ==================================================================

    /**
     * Le régime de vérité d'une entité (INV-73).
     *
     * `reconstructible` dit si une réindexation la reproduirait. Une entité
     * inscrite ne l'est PAS, et c'est le fait que le corpus doit connaître :
     * sa sauvegarde n'est pas le dépôt Git.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreRegimeVerite(string $reference): ?array
    {
        $st = $this->pdo->prepare('SELECT source FROM entite WHERE reference = ?');
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

        $st = $this->pdo->prepare('SELECT source_inscription FROM identite_inscrite WHERE reference = ?');
        $st->execute([$reference]);
        $i = $st->fetch();
        if ($i === false) {
            return null;
        }

        return [
            'reference'       => $reference,
            'regime'          => self::INSCRIT,
            'reconstructible' => false,
            'source'          => $i['source_inscription'],
        ];
    }

    /**
     * Relations d'usage d'une identité avec des produits.
     *
     * `$appelant` filtre sur le produit qui interroge : un produit ne voit que
     * SES relations. Passer `null` restitue tout et n'est légitime que pour une
     * autorité habilitée — l'évaluation de cette habilitation relève de
     * `CAP-CORE-004`, que le présent service ne remplace pas.
     *
     * Une relation expirée n'est JAMAIS restituée comme active : son état est
     * recalculé sur la date demandée, et jamais lu tel quel.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreLiensProduits(
        string $reference,
        ?string $appelant = null,
        ?string $relationType = null,
        ?string $date = null,
    ): array {
        $sql = 'SELECT produit_reference, relation_type, etat, source, date_debut, date_fin, classification
                FROM relation_produit WHERE identite_reference = ?';
        $args = [$reference];
        if ($appelant !== null) {
            $sql .= ' AND produit_reference = ?';
            $args[] = $appelant;
        }
        if ($relationType !== null) {
            $sql .= ' AND relation_type = ?';
            $args[] = $relationType;
        }
        $sql .= ' ORDER BY date_debut, id';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);

        $jour = $date ?? date('Y-m-d');
        $lignes = [];
        foreach ($st->fetchAll() as $r) {
            $lignes[] = [
                'produit_reference' => $r['produit_reference'],
                'relation_type'     => $r['relation_type'],
                'etat'              => $this->etatRelation($r, $jour),
                'source'            => $r['source'],
                'date_debut'        => $r['date_debut'],
                'date_fin'          => $r['date_fin'],
                'classification'    => $r['classification'],
            ];
        }

        return $lignes;
    }

    /**
     * Relations d'une identité avec des organisations.
     *
     * `mandat_reference` vaut `null` tant qu'aucun mandat n'est vérifié par
     * `CAP-CORE-003`. Le service restitue alors `opposable = false` : une
     * relation REPRESENTANT ou DIRIGEANT sans mandat est un lien inscrit, non
     * une représentation. Les deux faits sont restitués séparément, et le
     * premier n'est jamais présenté comme valant le second (INV-77).
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreLiensOrganisations(
        string $reference,
        ?string $organisation = null,
        ?string $relationType = null,
        ?string $date = null,
    ): array {
        $sql = 'SELECT organisation_reference, relation_type, etat, mandat_reference, source,
                       date_debut, date_fin, classification
                FROM relation_organisation WHERE identite_reference = ?';
        $args = [$reference];
        if ($organisation !== null) {
            $sql .= ' AND organisation_reference = ?';
            $args[] = $organisation;
        }
        if ($relationType !== null) {
            $sql .= ' AND relation_type = ?';
            $args[] = $relationType;
        }
        $sql .= ' ORDER BY date_debut, id';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);

        $jour = $date ?? date('Y-m-d');
        $lignes = [];
        foreach ($st->fetchAll() as $r) {
            $aMandat = in_array($r['relation_type'], PolitiqueInscription::RELATIONS_A_MANDAT, true);
            $lignes[] = [
                'organisation_reference' => $r['organisation_reference'],
                'relation_type'          => $r['relation_type'],
                'etat'                   => $this->etatRelation($r, $jour),
                'mandat_reference'       => $r['mandat_reference'],
                'opposable'              => !$aMandat || $r['mandat_reference'] !== null,
                'source'                 => $r['source'],
                'date_debut'             => $r['date_debut'],
                'date_fin'               => $r['date_fin'],
                'classification'         => $r['classification'],
            ];
        }

        return $lignes;
    }

    /**
     * Retrouve la référence canonique depuis le sujet local d'un produit.
     *
     * Réservée au produit concerné : l'appelant DOIT nommer le produit, et le
     * service ne cherche que dans les relations de ce produit. Un produit ne
     * résout pas les sujets locaux d'un autre.
     *
     * Le sujet local est opaque au Core : il n'est jamais interprété, jamais
     * restitué à un tiers, et ne sert qu'à cette correspondance.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreIdentiteDepuisSujetProduit(string $produit, string $sujetLocalOpaque): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT identite_reference FROM relation_produit
             WHERE produit_reference = ? AND sujet_local_opaque = ?
             ORDER BY date_debut DESC, id DESC LIMIT 1'
        );
        $st->execute([$produit, $sujetLocalOpaque]);
        $r = $st->fetch();
        if ($r === false) {
            return null;
        }

        $reference = (string) $r['identite_reference'];

        // Exposition minimale (loi révisée, Art. 30) : le produit reçoit le
        // résultat nécessaire, et lui seul. Ni les organisations liées, ni les
        // autres produits utilisés, ni les rapprochements.
        return [
            'reference' => $reference,
            'etat'      => $this->etatInscrit($reference, null),
            'assurance' => $this->resoudreAssurance($reference)['niveau'],
        ];
    }

    /**
     * Niveau d'assurance courant, et les événements qui l'établissent.
     *
     * INV-78 — le niveau procède EXCLUSIVEMENT d'un événement de preuve. Il ne
     * se déduit ni du nombre de produits utilisés, ni de l'ancienneté, ni du
     * volume d'activité. Une identité sans événement est `A0` : non pas
     * « faible parce que peu active », mais « non établie faute de preuve ».
     *
     * @return array<string,mixed>
     */
    public function resoudreAssurance(string $reference): array
    {
        $st = $this->pdo->prepare(
            'SELECT niveau, preuve, source, date_effet FROM evenement_assurance
             WHERE identite_reference = ? ORDER BY date_effet, id'
        );
        $st->execute([$reference]);
        $evenements = $st->fetchAll();

        $niveau = 'A0';
        foreach ($evenements as $e) {
            if (PolitiqueInscription::auMoins((string) $e['niveau'], $niveau)) {
                $niveau = (string) $e['niveau'];
            }
        }

        $dernier = $evenements === [] ? null : $evenements[count($evenements) - 1];

        return [
            'reference'   => $reference,
            'niveau'      => $niveau,
            'source'      => $dernier['source'] ?? null,
            'date_effet'  => $dernier['date_effet'] ?? null,
            'evenements'  => array_map(
                static fn (array $e): array => [
                    'niveau'     => $e['niveau'],
                    'preuve'     => $e['preuve'],
                    'date_effet' => $e['date_effet'],
                ],
                $evenements,
            ),
        ];
    }

    /**
     * Cette identité peut-elle servir à CETTE fin ?
     *
     * La question n'est pas « cette identité est-elle de bonne qualité ? ».
     * Un service qui y répondrait noterait les personnes, ce qu'INV-19 exclut.
     * Celui-ci confronte un état et un niveau à une exigence déclarée, et
     * ÉNUMÈRE ce qui manque plutôt que de rendre un verdict opaque.
     *
     * @return array<string,mixed>
     */
    public function resoudreEtatUtilisable(string $reference, string $finalite): array
    {
        $manque = [];

        $exige = PolitiqueInscription::FINALITES[$finalite] ?? null;
        if ($exige === null) {
            return [
                'utilisable' => false,
                'motif'      => 'FINALITE_INCONNUE',
                'exigences_non_satisfaites' => ['finalité `' . $finalite . '` absente de la liste close'],
            ];
        }

        if ($this->resoudreIdentite($reference) === null) {
            return [
                'utilisable' => false,
                'motif'      => 'IDENTITE_INCONNUE',
                'exigences_non_satisfaites' => ['aucune entité ne porte cette référence'],
            ];
        }

        $etat = $this->etatCourant($reference);
        if ($etat !== null && !in_array($etat, ['ACTIVE', 'VERIFIEE'], true)) {
            $manque[] = 'état `' . $etat . '` incompatible avec un usage';
        }

        $niveau = $this->resoudreAssurance($reference)['niveau'];
        if (!PolitiqueInscription::auMoins($niveau, $exige)) {
            $manque[] = 'assurance `' . $niveau . '` inférieure au niveau `' . $exige . '` exigé';
        }

        if (in_array($finalite, PolitiqueInscription::FINALITES_A_MANDAT, true)) {
            $opposables = array_filter(
                $this->resoudreLiensOrganisations($reference),
                static fn (array $r): bool => $r['opposable'] && $r['etat'] === 'ACTIVE'
                    && in_array($r['relation_type'], PolitiqueInscription::RELATIONS_A_MANDAT, true),
            );
            if ($opposables === []) {
                $manque[] = 'aucun mandat vérifié par CAP-CORE-003';
            }
        }

        return [
            'utilisable' => $manque === [],
            'motif'      => $manque === [] ? 'SATISFAITE' : 'EXIGENCES_NON_SATISFAITES',
            'finalite'   => $finalite,
            'assurance'  => $niveau,
            'exigences_non_satisfaites' => array_values($manque),
        ];
    }

    /**
     * Rapprochements proposés entre deux références.
     *
     * INV-80 — le service SIGNALE ; il ne fusionne pas. Un rapprochement naît
     * à l'état `PROPOSE` et ne quitte cet état que par une décision nommant
     * son décideur.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreRapprochementsProposes(?string $reference = null): array
    {
        $sql = 'SELECT reference_a, reference_b, preuves, etat, decideur, date_effet FROM rapprochement';
        $args = [];
        if ($reference !== null) {
            $sql .= ' WHERE reference_a = ? OR reference_b = ?';
            $args = [$reference, $reference];
        }
        $sql .= ' ORDER BY date_effet, id';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);

        return array_map(
            static fn (array $r): array => [
                'reference_a' => $r['reference_a'],
                'reference_b' => $r['reference_b'],
                'preuves'     => array_values(array_filter(explode('|', (string) $r['preuves']))),
                'etat'        => $r['etat'],
                'decideur'    => $r['decideur'],
                'date_effet'  => $r['date_effet'],
            ],
            $st->fetchAll(),
        );
    }

    // ==================================================================
    // COMMANDES GOUVERNÉES — INV-79
    // ==================================================================

    /**
     * Inscrit une identité selon une politique adoptée.
     *
     * Les six conditions cumulatives d'INV-79 sont vérifiées AVANT toute
     * écriture, et le refus est la position par défaut (Article 19 de l'Atlas).
     * Une commande qui ne les satisfait pas ne s'exécute pas : elle ne
     * s'exécute pas partiellement non plus.
     *
     * @param array<string,mixed> $dossier
     * @return array<string,mixed> l'identité inscrite, ou `['refus' => motif]`
     */
    public function inscrireIdentite(array $dossier): array
    {
        $canal      = (string) ($dossier['canal'] ?? '');
        $type       = (string) ($dossier['type'] ?? '');
        $producteur = (string) ($dossier['producteur'] ?? '');
        $politique  = (string) ($dossier['politique'] ?? '');
        $preuve     = (string) ($dossier['preuve'] ?? '');
        $libelle    = (string) ($dossier['libelle'] ?? '');
        $classification = (string) ($dossier['classification'] ?? 'INTERNE');
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));

        // 2. La politique d'inscription est nommée. Sans elle, rien ne s'écrit.
        if ($politique === '') {
            return $this->refus('POLITIQUE_ABSENTE', 'aucune politique d\'inscription nommée (INV-79)');
        }

        // 1. Le canal est autorisé, et il est compétent pour ce type.
        $regle = PolitiqueInscription::CANAUX[$canal] ?? null;
        if ($regle === null) {
            return $this->refus('CANAL_NON_AUTORISE', "canal `{$canal}` absent de la politique (ADOPTION-0066, Art. 212)");
        }
        if (!in_array($type, $regle['types'], true)) {
            return $this->refus(
                'TYPE_NON_AUTORISE',
                "le canal `{$canal}` n'inscrit pas le type `{$type}` (ADOPTION-0066, Art. 212)"
            );
        }

        // 4. Le producteur est nommé, et les canaux réservés exigent l'autorité.
        if ($producteur === '') {
            return $this->refus('PRODUCTEUR_ABSENT', 'aucun producteur nommé (INV-79)');
        }
        if (in_array($canal, PolitiqueInscription::CANAUX_RESERVES, true)
            && $producteur !== PolitiqueInscription::AUTORITE_INSCRIPTION) {
            return $this->refus(
                'CANAL_RESERVE',
                "le canal `{$canal}` est exercé par l'autorité d'inscription seule (ADOPTION-0066, Art. 214)"
            );
        }

        // 6. Une preuve est laissée. Une inscription sans preuve n'en est pas une.
        if ($preuve === '') {
            return $this->refus('PREUVE_ABSENTE', 'aucune preuve d\'inscription (INV-79)');
        }
        if ($libelle === '') {
            return $this->refus('LIBELLE_ABSENT', 'aucune dénomination fournie');
        }

        // 5. Les valeurs hors liste close sont refusées.
        if (!in_array($classification, PolitiqueInscription::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', "classification `{$classification}` hors liste close");
        }

        $reference = $this->allouerReference($type);

        $this->pdo->prepare(
            'INSERT INTO identite_inscrite
             (reference,type,libelle,regime,canal,producteur,politique_inscription,source_inscription,classification,date_creation)
             VALUES(?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference, $type, $libelle, self::INSCRIT, $canal, $producteur,
            $politique, $preuve, $classification, $date,
        ]);

        // 3. Un événement de cycle est produit, en ajout seul.
        $this->inscrireEvenement($reference, 'CREATION', null, 'ACTIVE', $preuve, $politique, $producteur, $date);

        // L'assurance INITIALE est celle du canal, et jamais davantage (INV-78).
        $this->pdo->prepare(
            'INSERT INTO evenement_assurance(identite_reference,niveau,preuve,source,date_effet) VALUES(?,?,?,?,?)'
        )->execute([$reference, $regle['assurance'], $preuve, "canal {$canal}", $date]);

        return [
            'reference' => $reference,
            'etat'      => 'ACTIVE',
            'assurance' => $regle['assurance'],
            'regime'    => self::INSCRIT,
        ];
    }

    /**
     * Rattache une identité à un produit par une relation d'usage.
     *
     * Le rattachement ne modifie NI l'identité, NI son assurance : utiliser un
     * produit ne prouve rien sur celui qui l'utilise (INV-78).
     *
     * @return array<string,mixed>
     */
    public function rattacherProduit(
        string $reference,
        string $produit,
        string $relationType,
        string $preuve,
        ?string $sujetLocalOpaque = null,
        string $classification = 'INTERNE',
        ?string $date = null,
    ): array {
        if ($this->resoudreIdentite($reference) === null) {
            return $this->refus('IDENTITE_INCONNUE', "aucune entité ne porte `{$reference}`");
        }
        if (!in_array($relationType, PolitiqueInscription::RELATIONS_PRODUIT, true)) {
            return $this->refus('RELATION_NON_AUTORISEE', "type `{$relationType}` hors liste close (loi révisée, Art. 21)");
        }
        if ($preuve === '') {
            return $this->refus('PREUVE_ABSENTE', 'aucune source pour la relation (INV-77)');
        }
        if (!in_array($classification, PolitiqueInscription::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', "classification `{$classification}` hors liste close");
        }

        $jour = $date ?? date('Y-m-d');
        $this->pdo->prepare(
            'INSERT INTO relation_produit
             (identite_reference,produit_reference,relation_type,etat,sujet_local_opaque,source,date_debut,date_fin,classification)
             VALUES(?,?,?,?,?,?,?,NULL,?)'
        )->execute([$reference, $produit, $relationType, 'ACTIVE', $sujetLocalOpaque, $preuve, $jour, $classification]);

        $this->inscrireEvenement($reference, 'RATTACHEMENT_PRODUIT', null, 'ACTIVE', $preuve, null, $produit, $jour);

        return [
            'identite_reference' => $reference,
            'produit_reference'  => $produit,
            'relation_type'      => $relationType,
            'etat'               => 'ACTIVE',
            'date_debut'         => $jour,
        ];
    }

    /**
     * Rattache une identité à une organisation.
     *
     * `mandat_reference` demeure `null` sauf mandat vérifié par `CAP-CORE-003`.
     * Le service NE VÉRIFIE PAS le mandat — ce n'est pas son domaine — et ne
     * l'invente donc jamais : il inscrit ce qu'on lui donne et restitue
     * l'absence comme une absence.
     *
     * @return array<string,mixed>
     */
    public function rattacherOrganisation(
        string $reference,
        string $organisation,
        string $relationType,
        string $preuve,
        ?string $mandatReference = null,
        string $classification = 'INTERNE',
        ?string $date = null,
    ): array {
        if ($this->resoudreIdentite($reference) === null) {
            return $this->refus('IDENTITE_INCONNUE', "aucune entité ne porte `{$reference}`");
        }
        if (!in_array($relationType, PolitiqueInscription::RELATIONS_ORGANISATION, true)) {
            return $this->refus('RELATION_NON_AUTORISEE', "type `{$relationType}` hors liste close (loi révisée, Art. 21)");
        }
        if ($preuve === '') {
            return $this->refus('PREUVE_ABSENTE', 'aucune source pour la relation (INV-77)');
        }
        if (!in_array($classification, PolitiqueInscription::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', "classification `{$classification}` hors liste close");
        }

        $jour = $date ?? date('Y-m-d');
        $this->pdo->prepare(
            'INSERT INTO relation_organisation
             (identite_reference,organisation_reference,relation_type,etat,mandat_reference,source,date_debut,date_fin,classification)
             VALUES(?,?,?,?,?,?,?,NULL,?)'
        )->execute([$reference, $organisation, $relationType, 'ACTIVE', $mandatReference, $preuve, $jour, $classification]);

        $this->inscrireEvenement($reference, 'RATTACHEMENT_ORGANISATION', null, 'ACTIVE', $preuve, null, $organisation, $jour);

        return [
            'identite_reference'     => $reference,
            'organisation_reference' => $organisation,
            'relation_type'          => $relationType,
            'mandat_reference'       => $mandatReference,
            'opposable'              => !in_array($relationType, PolitiqueInscription::RELATIONS_A_MANDAT, true)
                || $mandatReference !== null,
            'etat'                   => 'ACTIVE',
        ];
    }

    /**
     * Clôt une relation d'usage à un produit.
     *
     * INV-76 — la clôture d'un compte ne clôt JAMAIS l'identité. La relation
     * reçoit une date de fin ; l'identité n'est pas touchée, et son état
     * demeure celui que ses propres événements établissent.
     *
     * La ligne de relation reçoit ici sa date de fin : c'est le seul `UPDATE`
     * du module, et il n'efface rien — la date de début, la source et la
     * classification demeurent, et l'événement de retrait est ajouté.
     *
     * @return array<string,mixed>
     */
    public function cloreRelationProduit(
        string $reference,
        string $produit,
        string $dateFin,
        string $motif,
    ): array {
        $st = $this->pdo->prepare(
            'SELECT id FROM relation_produit
             WHERE identite_reference = ? AND produit_reference = ? AND date_fin IS NULL
             ORDER BY date_debut DESC, id DESC LIMIT 1'
        );
        $st->execute([$reference, $produit]);
        $r = $st->fetch();
        if ($r === false) {
            return $this->refus('RELATION_INTROUVABLE', "aucune relation active entre `{$reference}` et `{$produit}`");
        }
        if ($motif === '') {
            return $this->refus('MOTIF_ABSENT', 'aucun motif de clôture');
        }

        $this->pdo->prepare('UPDATE relation_produit SET date_fin = ?, etat = ? WHERE id = ?')
            ->execute([$dateFin, 'CLOSE', $r['id']]);

        $this->inscrireEvenement($reference, 'RETRAIT_PRODUIT', 'ACTIVE', 'CLOSE', $motif, null, $produit, $dateFin);

        $identite = $this->resoudreIdentite($reference);

        return [
            'identite_reference' => $reference,
            'produit_reference'  => $produit,
            'relation_etat'      => 'CLOSE',
            'date_fin'           => $dateFin,
            // INV-76 : ce que la clôture n'a pas touché, et qu'on montre.
            'identite_etat'      => $identite['etat'] ?? null,
        ];
    }

    /**
     * Propose un rapprochement entre deux références.
     *
     * INV-80 — jamais `VALIDE`. Un rapprochement naît `PROPOSE`, exige des
     * preuves ÉNUMÉRÉES, et ne quitte cet état que par une décision nommant
     * son décideur. Un score seul n'est pas une preuve, et la commande refuse
     * une liste vide.
     *
     * @param list<string> $preuves
     * @return array<string,mixed>
     */
    public function proposerRapprochement(string $referenceA, string $referenceB, array $preuves): array
    {
        if ($referenceA === $referenceB) {
            return $this->refus('REFERENCES_IDENTIQUES', 'un rapprochement suppose deux références distinctes');
        }
        foreach ([$referenceA, $referenceB] as $ref) {
            if ($this->resoudreIdentite($ref) === null) {
                return $this->refus('IDENTITE_INCONNUE', "aucune entité ne porte `{$ref}`");
            }
        }
        $preuves = array_values(array_filter(array_map('trim', $preuves), static fn (string $p): bool => $p !== ''));
        if ($preuves === []) {
            return $this->refus('PREUVES_ABSENTES', 'aucune preuve énumérée : une probabilité ne rapproche pas (INV-80)');
        }

        $jour = date('Y-m-d');
        $this->pdo->prepare(
            'INSERT INTO rapprochement(reference_a,reference_b,preuves,etat,decideur,date_effet)
             VALUES(?,?,?,?,NULL,?)'
        )->execute([$referenceA, $referenceB, implode('|', $preuves), 'PROPOSE', $jour]);

        return [
            'reference_a' => $referenceA,
            'reference_b' => $referenceB,
            'preuves'     => $preuves,
            'etat'        => 'PROPOSE',
            'decideur'    => null,
        ];
    }

    /**
     * Inscrit un événement d'assurance — le SEUL chemin d'écriture du niveau.
     *
     * INV-78 : aucune autre commande n'écrit dans `evenement_assurance`, et il
     * n'existe pas de colonne d'assurance ailleurs. Un produit qui voudrait
     * élever le niveau d'un de ses utilisateurs n'a aucun moyen de le faire.
     *
     * @return array<string,mixed>
     */
    public function inscrireEvenementAssurance(
        string $reference,
        string $niveau,
        string $preuve,
        string $source,
        ?string $date = null,
    ): array {
        if (!isset(PolitiqueInscription::ASSURANCE[$niveau])) {
            return $this->refus('NIVEAU_INCONNU', "niveau `{$niveau}` hors échelle (ADOPTION-0066, Art. 213)");
        }
        if ($this->resoudreIdentite($reference) === null) {
            return $this->refus('IDENTITE_INCONNUE', "aucune entité ne porte `{$reference}`");
        }
        if ($preuve === '' || $source === '') {
            return $this->refus('PREUVE_ABSENTE', 'un niveau d\'assurance sans preuve ni source n\'est pas inscriptible (INV-78)');
        }

        $jour = $date ?? date('Y-m-d');
        $this->pdo->prepare(
            'INSERT INTO evenement_assurance(identite_reference,niveau,preuve,source,date_effet) VALUES(?,?,?,?,?)'
        )->execute([$reference, $niveau, $preuve, $source, $jour]);

        $this->inscrireEvenement($reference, 'VERIFICATION', null, 'ACTIVE', $preuve, null, $source, $jour);

        return $this->resoudreAssurance($reference);
    }

    // ==================================================================
    // interne
    // ==================================================================

    /** @return array<string,mixed> */
    private function refus(string $motif, string $detail): array
    {
        return ['refus' => $motif, 'detail' => $detail];
    }

    /** @return array<string,mixed>|null */
    private function etatDerive(string $reference, ?string $date): ?array
    {
        $sql = 'SELECT valeur, date_effet, adoption_reference FROM etat_entite WHERE entite_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);

        return $st->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    private function resoudreInscrite(string $reference, ?string $date): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT reference, type, libelle, source_inscription, politique_inscription, canal, classification, date_creation
             FROM identite_inscrite WHERE reference = ?'
        );
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
            'canal'                 => $i['canal'],
            'politique_inscription' => $i['politique_inscription'],
            'classification'        => $i['classification'],
        ];
    }

    /** État courant d'une identité inscrite, dérivé de ses événements (INV-21). */
    private function etatInscrit(string $reference, ?string $date): ?string
    {
        $sql = 'SELECT etat_apres FROM evenement_cycle WHERE identite_reference = ?
                AND evenement_type NOT IN (\'RATTACHEMENT_PRODUIT\',\'RETRAIT_PRODUIT\',
                                           \'RATTACHEMENT_ORGANISATION\',\'RETRAIT_ORGANISATION\')';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';

        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $r = $st->fetch();

        return $r === false ? null : (string) $r['etat_apres'];
    }

    /** État courant, quel que soit le régime. */
    private function etatCourant(string $reference): ?string
    {
        $identite = $this->resoudreIdentite($reference);

        return $identite === null ? null : ($identite['etat'] ?? null);
    }

    /**
     * État d'une relation à une date donnée.
     *
     * Une relation dont la date de fin est passée est `CLOSE`, quelle que soit
     * la valeur inscrite : l'état est RECALCULÉ, jamais lu tel quel. Une
     * relation expirée n'est ainsi jamais restituée comme active, même si
     * personne n'a pensé à la clore.
     *
     * @param array<string,mixed> $r
     */
    private function etatRelation(array $r, string $jour): string
    {
        $fin = $r['date_fin'] ?? null;
        if ($fin !== null && (string) $fin <= $jour) {
            return 'CLOSE';
        }

        return (string) $r['etat'];
    }

    private function inscrireEvenement(
        string $reference,
        string $type,
        ?string $avant,
        string $apres,
        string $source,
        ?string $politique,
        string $acteur,
        string $date,
    ): void {
        if (!in_array($type, PolitiqueInscription::EVENEMENTS, true)) {
            throw new \LogicException("événement `{$type}` hors liste close (loi révisée, Art. 22)");
        }

        $this->pdo->prepare(
            'INSERT INTO evenement_cycle
             (identite_reference,evenement_type,etat_avant,etat_apres,source,politique_inscription,acteur_reference,date_effet)
             VALUES(?,?,?,?,?,?,?,?)'
        )->execute([$reference, $type, $avant, $apres, $source, $politique, $acteur, $date]);
    }

    /**
     * Alloue une référence canonique jamais encore attribuée.
     *
     * INV-17 — une référence libérée par une clôture ou une dissolution n'est
     * JAMAIS réattribuée. Le compteur repart donc du plus grand numéro déjà
     * porté par le type, et non du nombre de lignes vivantes.
     */
    private function allouerReference(string $type): string
    {
        $prefixe = PolitiqueInscription::PREFIXE[$type] ?? PolitiqueInscription::PREFIXE['INDETERMINE'];

        $st = $this->pdo->prepare(
            'SELECT reference FROM identite_inscrite WHERE reference LIKE ? ORDER BY reference DESC LIMIT 1'
        );
        $st->execute([$prefixe . '-%']);
        $dernier = $st->fetch();

        $numero = 1;
        if ($dernier !== false && preg_match('/-(\d+)$/', (string) $dernier['reference'], $m)) {
            $numero = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%06d', $prefixe, $numero);
    }
}
