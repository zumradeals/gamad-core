<?php

declare(strict_types=1);

namespace Gamad\RegistreAnnuaire;

/**
 * Les quatre opérations du contrat CTR-14 — Annuaire des capacités et Atlas
 * (CAP-CORE-020, conception adoptée par ADOPTION-0044).
 *
 * Lecture et attestation seulement : aucune écriture applicative du corpus
 * (INV-4). L'annuaire décrit ; il ne décide de rien.
 *
 * CE SERVICE NE LIT PAS L'INDEX DÉRIVÉ. Il relève l'Atlas, le Registre et le
 * contenu du dépôt, et les confronte. C'est la « comparaison Atlas–Registre–
 * réalité » que l'Article 55 exige parmi ses contrôles requis, et qu'aucun
 * mécanisme n'opérait.
 *
 * Invariants portés :
 *   INV-36 l'annuaire décrit, il ne fonde pas ·
 *   INV-37 quatre dimensions, jamais confondues ·
 *   INV-38 divergence nommée, jamais arbitrée ·
 *   INV-39 champ non établi déclaré tel.
 */
final class Ctr14
{
    /**
     * La capacité souveraine que ce module sert (INV-41).
     *
     * Une famille de contrat peut servir deux capacités — `CTR-10` sert
     * l'audit et l'intégrité. Le numéro de famille ne suffit donc pas à
     * rattacher un module ; le module le déclare lui-même.
     */
    public const CAPACITE = 'CAP-CORE-020';

    private const REGISTRE = 'genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md';
    private const ATLAS    = 'genesis-ii/atlas/CORE-ATLAS-0001-atlas-initial-gamad-core.md';
    private const CI       = '.github/workflows/gardes-comportement.yml';

    /** Les quatre dimensions d'état, distinctes et jamais mêlées (INV-37). */
    public const DIMENSIONS = ['conception', 'implementation', 'exploitation', 'preuve'];

    /**
     * Ce qu'aucun service ne dérive, et que le dossier d'admission déclare
     * tel plutôt que de le combler (Article 14 de la conception ; INV-39).
     */
    public const NON_DERIVABLE = 'NON DÉRIVABLE — appréciation humaine';

    /** L'état d'implémentation depuis lequel une admission est recevable (INV-69). */
    public const RECEVABLE_DEPUIS = 'IMPLÉMENTÉE NON ADMISE';

    /** L'état d'implémentation d'une capacité dont l'admission est prononcée. */
    public const ADMISE = 'ADMISE';

    /** L'état d'exploitation qui suppose une admission prononcée (INV-67). */
    public const EXPLOITEE = 'ACTIVE';

    /** @var array<string,array<string,mixed>>|null */
    private ?array $registre = null;

    /** @var array<string,mixed>|null Les écarts du corpus, relevés une fois. */
    private ?array $ecartsCorpus = null;

    /** @var array<string,array<string,string>>|null Les admissions, relevées une fois. */
    private ?array $admissionsCache = null;

    /** @var array<string,array<string,string>>|null */
    private ?array $atlas = null;

    /** @var array<string,array<string,string>>|null */
    private ?array $familles = null;

    /** @var list<array<string,string|null>>|null */
    private ?array $modules = null;

    public function __construct(
        private string $corpus,
    ) {
    }

    /**
     * Résout la fiche d'une capacité : identité, domaine, criticité, contrats
     * attendus, dépendances, et l'état courant de ses QUATRE dimensions.
     *
     * L'état courant procède du tableau de l'Article 31, puis de chaque Titre
     * de mise à jour post-adoption, appliqués dans l'ordre du document — qui
     * est l'ordre chronologique d'adoption.
     *
     * @return array<string,mixed>|null `null` si la capacité est inconnue
     */
    public function resoudreCapacite(string $reference): ?array
    {
        return $this->registre()[$reference] ?? null;
    }

    /**
     * Confronte l'Atlas et le Registre : deux sources décrivent les mêmes
     * capacités, et rien ne vérifiait qu'elles disent la même chose.
     *
     * Le service NOMME les divergences ; il n'en arbitre aucune (INV-38).
     * Trancher entre deux textes adoptés est un acte de l'autorité.
     *
     * @return list<array<string,mixed>>
     */
    public function comparerAtlas(): array
    {
        $atlas = $this->atlas();
        $registre = $this->registre();
        $references = array_unique(array_merge(array_keys($atlas), array_keys($registre)));
        sort($references);

        $lignes = [];
        foreach ($references as $ref) {
            $a = $atlas[$ref] ?? null;
            $r = $registre[$ref] ?? null;
            $divergences = [];

            if ($a === null) {
                $divergences[] = "absente de l'Atlas";
            } elseif ($r === null) {
                $divergences[] = 'absente du Registre';
            } else {
                if ($this->normaliser($a['libelle']) !== $this->normaliser((string) $r['libelle'])) {
                    $divergences[] = sprintf("libellé : Atlas « %s » / Registre « %s »", $a['libelle'], $r['libelle']);
                }
                if ($this->normaliser($a['domaine']) !== $this->normaliser((string) $r['domaine'])) {
                    $divergences[] = sprintf('domaine : Atlas %s / Registre %s', $a['domaine'], $r['domaine']);
                }
            }

            $lignes[] = [
                'capacite'    => $ref,
                'atlas'       => $a,
                'registre'    => $r === null ? null : ['libelle' => $r['libelle'], 'domaine' => $r['domaine']],
                'divergences' => $divergences,
                'verdict'     => $divergences === [] ? 'CONCORDE' : 'DIVERGENCE',
            ];
        }

        return $lignes;
    }

    /**
     * Relève, pour chaque numéro de contrat, les capacités qui le revendiquent
     * — que la revendication figure dans la fiche ou dans un Titre postérieur.
     *
     * @return array<string,list<string>>
     */
    public function attributions(): array
    {
        $parContrat = [];
        foreach ($this->registre() as $ref => $fiche) {
            foreach ($fiche['contrats'] as $contrat) {
                $parContrat[$contrat][] = $ref;
            }
        }
        ksort($parContrat);

        return $parContrat;
    }

    /**
     * Familles de contrat servant plus d'une capacité.
     *
     * Ce partage est RÉGULIER par construction : l'Article 69 de l'Atlas ne
     * numérote pas des contrats par capacité, il définit des familles, et trois
     * d'entre elles annoncent dans leur intitulé qu'elles en servent deux —
     * « Statut produit ou realm », « Audit et intégrité », « Risque et
     * incident ». Compter les revendications d'un même numéro et conclure à la
     * faute était l'erreur de la première version de ce service, inscrite comme
     * fait par ADOPTION-0044 et rectifiée par ADOPTION-0045.
     *
     * Ce qui fait la faute n'est pas le nombre : c'est le domaine (INV-40).
     * Voir usurpations().
     *
     * @return array<string,list<string>>
     */
    public function partages(): array
    {
        return array_filter($this->attributions(), fn (array $caps) => count($caps) > 1);
    }

    /**
     * Revendications fautives : une capacité porte une famille dont elle ne
     * garde pas le domaine (INV-40).
     *
     * Le domaine gardien de la famille, tel que l'Atlas l'établit, doit figurer
     * parmi les domaines de la capacité. Un partage entre capacités qui
     * satisfont toutes cette condition est régulier ; une revendication qui ne
     * la satisfait pas est une usurpation, fût-elle solitaire — et c'est ainsi
     * que l'emprunt de CTR-09 par CAP-CORE-006 a échappé trois actes durant à
     * un mécanisme qui ne cherchait que les doublons.
     *
     * Une famille qui ne déclare aucun code de domaine — « Transversal » — ne
     * fournit rien à vérifier : la condition est tenue pour satisfaite plutôt
     * que devinée.
     *
     * Le service NOMME l'usurpation ; il ne la corrige pas (INV-38).
     *
     * @return list<array<string,string>>
     */
    public function usurpations(): array
    {
        $familles = $this->familles();
        $releve = [];

        foreach ($this->registre() as $ref => $fiche) {
            $domainesCapacite = $this->codesDomaine((string) $fiche['domaine']);
            foreach ($fiche['contrats'] as $contrat) {
                if (!isset($familles[$contrat])) {
                    $releve[] = [
                        'capacite' => $ref,
                        'famille'  => $contrat,
                        'motif'    => 'FAMILLE INCONNUE',
                        'detail'   => 'aucune famille `' . $contrat . '` n\'est définie par l\'Atlas',
                    ];
                    continue;
                }
                $gardiens = $this->codesDomaine($familles[$contrat]['gardien']);
                if ($gardiens === [] || array_intersect($gardiens, $domainesCapacite) !== []) {
                    continue;
                }
                $releve[] = [
                    'capacite' => $ref,
                    'famille'  => $contrat,
                    'motif'    => 'USURPATION DE FAMILLE',
                    'detail'   => 'famille `' . $contrat . '` — ' . $familles[$contrat]['libelle']
                        . ' — gardée par ' . implode(', ', $gardiens)
                        . ' ; la capacité garde ' . (implode(', ', $domainesCapacite) ?: 'aucun domaine codé'),
                ];
            }
        }

        return $releve;
    }

    /**
     * Observe la réalité du dépôt pour une capacité : un module sert-il son
     * contrat, une garde éprouve-t-elle ce contrat, cette garde s'exécute-t-elle
     * en intégration continue.
     *
     * L'observation ne consulte aucune déclaration : elle regarde ce qui est
     * sur le disque. C'est le seul terme de la comparaison que le corpus ne
     * peut pas se raconter à lui-même.
     *
     * @return array<string,mixed>
     */
    public function observer(string $reference): array
    {
        $fiche = $this->resoudreCapacite($reference);
        $contrats = $fiche['contrats'] ?? [];

        // Le numéro de famille ne rattache plus un module à une capacité : une
        // famille peut en servir deux. C'est le module qui déclare la capacité
        // qu'il sert (INV-41), et cette déclaration est lue sur le disque.
        $module = null;
        $garde = null;
        $familleServie = null;
        foreach ($this->modules() as $decrit) {
            if ($decrit['capacite'] !== $reference) {
                continue;
            }
            $module = $decrit['module'];
            $familleServie = $decrit['famille'];
            $gardes = glob($this->corpus . '/core/' . $module . '/tests/*_p3.php') ?: [];
            $garde = $gardes === [] ? null : 'core/' . $module . '/tests/' . basename($gardes[0]);
            break;
        }

        $ci = false;
        if ($garde !== null) {
            $workflow = $this->corpus . '/' . self::CI;
            $ci = is_file($workflow) && str_contains((string) file_get_contents($workflow), $garde);
        }

        return [
            'capacite'       => $reference,
            'contrats'       => $contrats,
            'module'         => $module,
            'famille_servie' => $familleServie,
            'garde'          => $garde,
            'garde_en_ci'    => $ci,
            'code_present'   => $module !== null,
        ];
    }

    /**
     * Modules présents sur le disque, et la capacité que chacun DÉCLARE servir.
     *
     * L'observation ne lit ici aucune déclaration du corpus : elle lit le code
     * lui-même. Un module dépourvu de constante CAPACITE est relevé comme non
     * rattaché plutôt qu'attribué de force à la capacité dont le numéro de
     * famille se rapprocherait le plus.
     *
     * @return list<array<string,string|null>>
     */
    public function modules(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $releve = [];
        foreach (glob($this->corpus . '/core/*/src/Ctr*.php') ?: [] as $fichier) {
            $nom = basename($fichier, '.php');           // Ctr15
            if (!preg_match('/^Ctr(\d{2})$/', $nom, $m)) {
                continue;
            }
            $source = (string) file_get_contents($fichier);
            $capacite = preg_match("/const\s+CAPACITE\s*=\s*'(CAP-CORE-\d{3})'/", $source, $mc)
                ? $mc[1]
                : null;
            $releve[] = [
                'module'   => basename(dirname(dirname($fichier))),
                'classe'   => $nom,
                'famille'  => 'CTR-' . $m[1],
                'capacite' => $capacite,
            ];
        }

        return $this->modules = $releve;
    }

    /**
     * Confronte l'état DÉCLARÉ par le Registre à la réalité OBSERVÉE, pour
     * toutes les capacités ou pour l'une d'elles.
     *
     * Cinq divergences sont nommées, chacune répondant à un risque que
     * l'Article 55 énumère :
     *
     *   CAPACITÉ FANTÔME     — implémentation déclarée, aucun module ;
     *   CODE NON DÉCLARÉ     — module présent, implémentation déclarée non commencée ;
     *   PREUVE NON FONDÉE    — preuve P3 déclarée, aucune garde propre ;
     *   GARDE NON EXÉCUTÉE   — garde présente, absente de l'intégration continue ;
     *   PREUVE SOUS-DÉCLARÉE — garde présente et éprouvée, preuve déclarée moindre.
     *
     * @return list<array<string,mixed>>
     */
    public function comparerReel(?string $reference = null): array
    {
        $references = $reference !== null ? [$reference] : array_keys($this->registre());

        $lignes = [];
        foreach ($references as $ref) {
            $fiche = $this->resoudreCapacite($ref);
            if ($fiche === null) {
                continue;
            }
            $observe = $this->observer($ref);
            $etats = $fiche['etats'];
            $implementation = (string) ($etats['implementation'] ?? '');
            $preuve = (string) ($etats['preuve'] ?? '');
            $codee = $implementation !== '' && $implementation !== 'NON COMMENCÉE';
            $p3 = str_starts_with($preuve, 'P3');

            $divergences = [];
            foreach ($this->usurpations() as $u) {
                if ($u['capacite'] === $ref) {
                    $divergences[] = $u['motif'] . ' — ' . $u['detail'];
                }
            }
            if ($observe['code_present']
                && $observe['famille_servie'] !== null
                && !in_array($observe['famille_servie'], $fiche['contrats'], true)) {
                $divergences[] = 'MODULE HORS FAMILLE — `' . $observe['module'] . '` sert `'
                    . $observe['famille_servie'] . '`, que la capacité ne revendique pas';
            }
            if ($codee && !$observe['code_present']) {
                $divergences[] = 'CAPACITÉ FANTÔME — implémentation `' . $implementation . '` déclarée, aucun module ne sert son contrat';
            }
            if (!$codee && $observe['code_present']) {
                $divergences[] = 'CODE NON DÉCLARÉ — module `' . $observe['module'] . '` présent, implémentation déclarée `' . $implementation . '`';
            }
            if ($p3 && $observe['garde'] === null) {
                $divergences[] = 'PREUVE NON FONDÉE — preuve `' . $preuve . '` déclarée, aucune garde propre';
            }
            if ($observe['garde'] !== null && !$observe['garde_en_ci']) {
                $divergences[] = 'GARDE NON EXÉCUTÉE — `' . $observe['garde'] . '` absente de l\'intégration continue';
            }
            if (!$p3 && $observe['garde'] !== null) {
                $divergences[] = 'PREUVE SOUS-DÉCLARÉE — garde `' . $observe['garde'] . '` présente, preuve déclarée `' . ($preuve ?: 'non établie') . '`';
            }

            // INV-67 — l'admission est expresse ou n'est pas. Un état déclaré
            // `ADMISE` que nulle inscription ne porte serait une admission
            // tacite, c'est-à-dire la chose même que l'invariant refuse.
            $exploitation = (string) ($etats['exploitation'] ?? '');
            $admise = $implementation === self::ADMISE;
            if ($admise && !isset($this->admissions()[$ref])) {
                $divergences[] = 'ADMISSION NON INSCRITE — implémentation déclarée `'
                    . self::ADMISE . '`, aucune inscription à la forme de l\'Article 174';
            }
            if ($exploitation === self::EXPLOITEE && !$admise) {
                $divergences[] = 'EXPLOITATION SANS ADMISSION — exploitation déclarée `'
                    . self::EXPLOITEE . '`, implémentation `' . ($implementation ?: 'non établie') . '`';
            }

            $lignes[] = [
                'capacite'    => $ref,
                'declare'     => $etats,
                'observe'     => $observe,
                'divergences' => $divergences,
                'verdict'     => $divergences === [] ? 'CONCORDE' : 'DIVERGENCE',
            ];
        }

        return $lignes;
    }

    /**
     * Registre des écarts : la synthèse que l'Article 55 attend parmi ses
     * preuves `G0`, et le relevé des champs que le corpus n'établit pas.
     *
     * Un champ non établi est DÉCLARÉ tel, jamais comblé par une valeur
     * plausible (INV-39). L'annuaire qui invente un responsable crée une
     * responsabilité qui n'existe pas.
     *
     * @return array<string,mixed>
     */
    /**
     * Décompte des capacités par criticité, codées et restantes.
     *
     * Cette opération existe pour une raison précise : `ADOPTION-0053` a
     * affirmé « sept des huit `RACINE` » là où le corpus en porte dix, dont
     * huit codées. Le chiffre avait été écrit de mémoire, non dérivé.
     *
     * Un chiffre qui figure dans un acte doit pouvoir être relu du corpus.
     * Celui-ci l'est désormais, et la garde de la capacité l'éprouve.
     *
     * @return array<string,array<string,mixed>> criticité => décompte
     */
    public function parCriticite(): array
    {
        $decompte = [];
        foreach ($this->comparerReel() as $l) {
            $fiche = $this->resoudreCapacite((string) $l['capacite']);
            if ($fiche === null) {
                continue;
            }
            $criticite = (string) $fiche['criticite'];
            if (!isset($decompte[$criticite])) {
                $decompte[$criticite] = ['criticite' => $criticite, 'total' => 0, 'codees' => [], 'restantes' => []];
            }
            $decompte[$criticite]['total']++;
            $decompte[$criticite][$l['observe']['module'] !== null ? 'codees' : 'restantes'][] = (string) $l['capacite'];
        }
        ksort($decompte);

        return $decompte;
    }

    public function ecarts(): array
    {
        $reel = $this->comparerReel();
        $atlas = $this->comparerAtlas();

        $divergentes = array_values(array_filter($reel, fn (array $l) => $l['verdict'] === 'DIVERGENCE'));
        $divergencesAtlas = array_values(array_filter($atlas, fn (array $l) => $l['verdict'] === 'DIVERGENCE'));
        $nonRattaches = array_values(array_filter(
            $this->modules(),
            fn (array $m) => $m['capacite'] === null,
        ));

        $parType = [];
        foreach ($divergentes as $l) {
            foreach ($l['divergences'] as $d) {
                $type = trim(explode('—', $d)[0]);
                $parType[$type][] = $l['capacite'];
            }
        }

        // Champs que le corpus n'établit pour aucune capacité (INV-39).
        $champsNonEtablis = [];
        foreach (['responsable', 'operateur', 'sortie'] as $champ) {
            $etablis = 0;
            foreach ($this->registre() as $fiche) {
                if (($fiche['champs'][$champ] ?? null) !== null) {
                    $etablis++;
                }
            }
            if ($etablis === 0) {
                $champsNonEtablis[] = $champ;
            }
        }

        return [
            'capacites'            => count($this->registre()),
            'capacites_codees'     => count(array_filter($reel, fn (array $l) => $l['observe']['code_present'])),
            'par_criticite'        => $this->parCriticite(),
            'divergentes'          => count($divergentes),
            'divergences_par_type' => $parType,
            'atlas_divergent'      => count($divergencesAtlas),
            'familles'             => count($this->familles()),
            'familles_partagees'   => $this->partages(),
            'usurpations'          => $this->usurpations(),
            'modules_non_rattaches' => array_map(fn (array $m) => $m['module'], $nonRattaches),
            'champs_non_etablis'   => $champsNonEtablis,
            'portee'               => "Annuaire dérivé, jamais autoritatif (INV-36). Il nomme les divergences ; il n'en arbitre aucune.",
        ];
    }

    /**
     * Les admissions INSCRITES, relevées à la forme de l'Article 174 du
     * Registre initial des décisions.
     *
     * L'inscription vit au Registre initial des capacités souveraines :
     * l'admission d'une implémentation est le STATUT d'une capacité, et son
     * retrait est sa SORTIE — les deux termes de l'objet de `CTR-14`, à qui
     * `ADOPTION-0060` a rattaché l'admission d'une implémentation souveraine.
     *
     * Le service ne relève que ce qui porte la forme. Une admission écrite en
     * prose n'est pas une admission : c'est la leçon d'`ADOPTION-0059`, où
     * vingt-quatre décisions avaient attendu quatre actes faute de forme.
     *
     * @return array<string,array<string,string>> capacité => admission
     */
    public function admissions(): array
    {
        if ($this->admissionsCache !== null) {
            return $this->admissionsCache;
        }

        $admissions = [];
        foreach (explode("\n", $this->lire(self::REGISTRE)) as $ligne) {
            if (!preg_match(
                '/\*\*Admission\s*:\*\*\s*`(CAP-CORE-\d{3})`\s*\.\s*'
                . '\*\*Commit admis\s*:\*\*\s*`([0-9a-f]{7,40})`\s*\.\s*'
                . '\*\*Famille\s*:\*\*\s*`(CTR-\d{2})`\s*\.\s*'
                . '\*\*Responsable\s*:\*\*\s*(.+?)\s*\.\s*'
                . '\*\*Audit\s*:\*\*\s*(.+?)\s*\.\s*'
                . '\*\*Réexamen\s*:\*\*\s*(.+?)\s*\.\s*$/u',
                trim($ligne),
                $m,
            )) {
                continue;
            }
            $admissions[$m[1]] = [
                'capacite'     => $m[1],
                'commit_admis' => $m[2],
                'famille'      => $m[3],
                'responsable'  => trim($m[4]),
                'audit'        => trim($m[5]),
                'reexamen'     => trim($m[6]),
            ];
        }

        return $this->admissionsCache = $admissions;
    }

    /**
     * Le DOSSIER d'admission d'une capacité : les neuf pièces que l'Article 13
     * de `CONCEPTION-CONTROLE-ADMISSION-0001` déclare dérivables, et les
     * quatre questions que son Article 14 déclare hors de portée d'un service.
     *
     * CE SERVICE NE CONCLUT PAS (`INV-72`). Il n'émet aucun avis, ne qualifie
     * aucun dossier de suffisant et ne propose aucune admission. Le motif est
     * celui que `ADOPTION-0057` avait porté au code du service d'audit, qui ne
     * prononce aucune levée : un service écrit par le concepteur ne conclut
     * pas sur l'ouvrage du concepteur.
     *
     * La qualité de l'audit n'est pas analysée ici : elle est CONSOMMÉE de
     * `CAP-CORE-013`, seule capacité dont c'est la mission. Deux analyseurs du
     * même fait finiraient par diverger, et le corpus porterait alors deux
     * vérités sur l'indépendance de son propre audit.
     *
     * @return array<string,mixed>
     */
    public function dossierAdmission(string $reference): array
    {
        $comparaison = $this->comparerReel($reference);
        if ($comparaison === []) {
            return [
                'capacite' => $reference,
                'complet'  => false,
                'motif'    => 'capacité inconnue du Registre',
            ];
        }

        $ligne = $comparaison[0];
        $observe = $ligne['observe'];
        $admission = $this->admissions()[$reference] ?? null;
        $commitCourant = $this->commitDuModule($observe['module']);

        // INV-68 — une admission nomme un commit et ne lui survit pas.
        $etatAdmission = 'AUCUNE ADMISSION INSCRITE';
        if ($admission !== null) {
            // Le commit admis est confronté à l'HISTOIRE du module, et pas
            // seulement à sa tête. Un commit que le module n'a jamais porté
            // n'est pas une admission caduque : c'est une admission qui ne
            // désigne rien, et les deux ne se confondent pas.
            $admission['commit_admis_connu'] = $this->commitConnuDuModule(
                $observe['module'],
                $admission['commit_admis'],
            );

            if (!$admission['commit_admis_connu']) {
                $etatAdmission = 'ADMISSION SANS OBJET — le commit admis n\'appartient pas à l\'histoire du module';
            } else {
                $etatAdmission = $commitCourant !== null
                    && str_starts_with($commitCourant, $admission['commit_admis'])
                        ? 'ADMISE — commit inchangé'
                        : 'ADMISSION CADUQUE — le module a changé depuis le commit admis (INV-68)';
            }
        }

        $acte = $this->acteAdoptant($observe['module']);

        // Les NEUF pièces de l'Article 13 de la conception, une clé par pièce.
        $pieces = [
            'identite'             => [
                'capacite'       => $reference,
                'module'         => $observe['module'],
                'famille_servie' => $observe['famille_servie'],
            ],
            'commit_presente'      => $commitCourant,
            'acte_adoptant'        => $acte,
            'garde'                => [
                'chemin' => $observe['garde'],
                'en_ci'  => $observe['garde_en_ci'],
            ],
            'contre_epreuve'       => $this->contreEpreuve($acte),
            'concordance'          => $ligne['verdict'],
            'ecarts_ouverts'       => $this->ecartsDeLaCapacite($reference),
            'exclusions_de_mission' => $this->exclusionsDeMission($reference),
            'audit'                => $this->qualiteDeLAudit(),
        ];

        $manquantes = [];
        if ($observe['module'] === null || $observe['famille_servie'] === null) {
            $manquantes[] = 'identite';
        }
        if ($commitCourant === null) {
            $manquantes[] = 'commit_presente';
        }
        if ($acte === null) {
            $manquantes[] = 'acte_adoptant';
        }
        if ($observe['garde'] === null || !$observe['garde_en_ci']) {
            $manquantes[] = 'garde';
        }
        if (!$pieces['contre_epreuve']['declaree']) {
            $manquantes[] = 'contre_epreuve';
        }

        return [
            'capacite'        => $reference,
            'etat_declare'    => $ligne['declare'],
            'admission'       => $admission,
            'etat_admission'  => $etatAdmission,
            'pieces'          => $pieces,
            'pieces_manquantes' => $manquantes,
            'dossier_complet' => $manquantes === [],

            // INV-69 · INV-70 — recevabilité et mention, qui ne se dérivent pas.
            // Une capacité DÉJÀ admise n'est pas « recevable » : elle n'a plus
            // à se présenter. Les deux faux sont distincts et le dossier les
            // distingue, faute de quoi une capacité admise se lirait comme une
            // capacité écartée.
            'recevable_a_l_admission' => $ligne['declare']['implementation'] === self::RECEVABLE_DEPUIS,
            'deja_admise'             => $ligne['declare']['implementation'] === self::ADMISE,
            'mention_d_audit_requise' => $this->qualiteDeLAudit()['independante'] === false,

            // Article 14 de la conception — ce qu'aucun service ne dérive.
            'non_derivable' => [
                'completude'      => self::NON_DERIVABLE,
                'proportionnalite' => self::NON_DERIVABLE,
                'responsable'     => self::NON_DERIVABLE,
                'opportunite'     => self::NON_DERIVABLE,
            ],

            'portee' => "Le service assemble le dossier et ne conclut pas (INV-72). "
                . "Un dossier complet ne vaut pas admission : il la rend examinable. "
                . "L'admission est prononcée par l'autorité seule (ADOPTION-0061).",
        ];
    }

    /**
     * Les écarts ouverts qui touchent la capacité, pris de `ecarts()` — la
     * source que l'Article 13 de la conception nomme pour cette pièce.
     *
     * Le dossier ne tient pas son propre décompte d'écarts : deux inventaires
     * du même fait divergeraient, et le dossier finirait par présenter à
     * l'admission un état plus clément que celui que l'annuaire publie.
     *
     * @return array<string,string> type d'écart => portée
     */
    private function ecartsDeLaCapacite(string $reference): array
    {
        $this->ecartsCorpus ??= $this->ecarts();

        $siens = [];
        foreach ($this->ecartsCorpus['divergences_par_type'] as $type => $capacites) {
            if (in_array($reference, $capacites, true)) {
                $siens[$type] = 'écart ouvert sur cette capacité';
            }
        }

        return $siens;
    }

    /**
     * La contre-épreuve de falsification déclarée par l'acte qui a adopté
     * l'incrément, et son témoin (`ADOPTION-0032`, Art. 3).
     *
     * Le service relève ce que l'acte DÉCLARE. Il ne rejoue pas la
     * falsification et ne certifie pas qu'elle a eu lieu : une contre-épreuve
     * se constate à l'exécution, par l'autorité, non par le service qui la lit.
     *
     * @return array<string,mixed>
     */
    private function contreEpreuve(?string $acte): array
    {
        $absente = [
            'declaree' => false,
            'temoin'   => false,
            'source'   => $acte,
            'portee'   => "L'acte adoptant ne déclare aucune contre-épreuve de falsification.",
        ];

        if ($acte === null) {
            return ['declaree' => false, 'temoin' => false, 'source' => null, 'portee' => 'Acte adoptant non résolu.'];
        }

        $fichiers = glob($this->corpus . '/genesis-ii/registre/' . $acte . '-*.md') ?: [];
        if ($fichiers === []) {
            return $absente;
        }

        // La SECTION de contre-épreuve, et elle seule. Un acte qui se borne à
        // lister une garde dans son tableau de vérification ne l'a pas
        // falsifiée : confondre les deux ferait passer une mention pour une
        // preuve.
        $texte = (string) file_get_contents($fichiers[0]);
        if (!preg_match(
            '/^##[^\n]*(?:Contre-épreuve de falsification|par falsification)[^\n]*$(.*?)(?=^## |\z)/msu',
            $texte,
            $m,
        )) {
            return $absente;
        }

        $section = $m[1];
        if (preg_match('/Aucune contre-épreuve n\'est déclarée/ui', $section)) {
            return $absente;
        }

        return [
            'declaree' => true,
            'temoin'   => (bool) preg_match('/témoin|intact|sain/ui', $section),
            'source'   => $acte,
            'portee'   => "Déclaration relevée de l'acte adoptant ; la contre-épreuve n'est pas rejouée ici.",
        ];
    }

    /**
     * Les exclusions de mission que la conception adoptée déclare pour cette
     * capacité — ce que le service n'a PAS le droit de connaître ou de
     * produire (`INV-61`, `INV-66`).
     *
     * Elles appartiennent au périmètre et ne comptent pas comme manque
     * (`INV-69`). Un dossier qui les tairait présenterait comme lacunaire un
     * service qui se borne comme le corpus le lui ordonne.
     *
     * @return list<string> les documents de conception qui en déclarent
     */
    private function exclusionsDeMission(string $reference): array
    {
        $declarants = [];
        foreach (glob($this->corpus . '/genesis-ii/conception/*.md') ?: [] as $fichier) {
            $texte = (string) file_get_contents($fichier);
            if (!str_contains($texte, $reference)) {
                continue;
            }
            if (preg_match('/exclusion de mission|interdiction absolue/ui', $texte)) {
                $declarants[] = basename($fichier, '.md');
            }
        }

        return $declarants;
    }

    /**
     * La qualité de l'audit, consommée de `CAP-CORE-013` et jamais recalculée.
     *
     * `ADOPTION-0061` a rendu la mention obligatoire pour toute admission :
     * tant que l'autorité de décision et `FCT-CORE-021` sont le même titulaire,
     * une inscription d'admission qui l'omettrait serait irrégulière.
     *
     * @return array<string,mixed>
     */
    private function qualiteDeLAudit(): array
    {
        $classe = $this->corpus . '/core/registre-audit/src/Ctr10.php';
        if (!is_file($classe)) {
            return ['independante' => null, 'source' => self::NON_DERIVABLE];
        }
        require_once $classe;
        $ctr10 = new \Gamad\RegistreAudit\Ctr10($this->corpus);
        $etat = $ctr10->independanceDeLAudit();

        return [
            'independante'   => $etat['independante'],
            'detenteur'      => $etat['detenteur'],
            'risque_associe' => $etat['risque_associe'],
            'source'         => 'CAP-CORE-013 — ' . $etat['source'],
        ];
    }

    /**
     * L'acte qui a adopté le module, relevé des actes eux-mêmes.
     *
     * La dérivation ne devine rien : elle prend le commit qui a INTRODUIT le
     * module, puis cherche cette empreinte parmi celles que les actes
     * déclarent à leur constat d'exécution. Un module dont aucune empreinte
     * déclarée ne porte le commit est rendu `null` — jamais rattaché par
     * ressemblance de nom à l'acte qui s'en rapprocherait le plus (INV-43).
     */
    private function acteAdoptant(?string $module): ?string
    {
        $introduction = $this->commitDuModule($module, true);
        if ($introduction === null) {
            return null;
        }

        foreach (glob($this->corpus . '/genesis-ii/registre/ADOPTION-*.md') ?: [] as $acte) {
            $texte = (string) file_get_contents($acte);
            if (!preg_match_all('/`([0-9a-f]{7,40})`/', $texte, $m)) {
                continue;
            }
            foreach ($m[1] as $empreinte) {
                if (str_starts_with($introduction, $empreinte)) {
                    return preg_match('/^(ADOPTION-\d{4})/', basename($acte), $ma) ? $ma[1] : null;
                }
            }
        }

        return null;
    }

    /**
     * Le commit admis appartient-il à l'histoire du module ?
     *
     * Une admission qui nomme un commit que le module n'a jamais porté ne
     * désigne rien. Le distinguer d'une admission caduque importe : la seconde
     * a été vraie et a cessé de l'être, la première n'a jamais rien admis.
     * Les confondre laisserait passer une inscription fautive sous le couvert
     * d'une évolution normale du code.
     */
    private function commitConnuDuModule(?string $module, string $commit): bool
    {
        if ($module === null || $commit === '' || !is_dir($this->corpus . '/.git')) {
            return false;
        }

        $sortie = (string) @shell_exec(sprintf(
            'git -C %s log --format=%%H -- %s 2>/dev/null',
            escapeshellarg($this->corpus),
            escapeshellarg('core/' . $module),
        ));

        foreach (explode("\n", $sortie) as $ligne) {
            if (str_starts_with(trim($ligne), $commit)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Le commit du module, tel que le dépôt le porte — le dernier, ou celui
     * qui l'a introduit.
     *
     * Hors dépôt Git — une copie de corpus, par exemple — l'empreinte n'est
     * pas dérivable. Elle est alors rendue `null`, et le dossier la compte
     * parmi ses pièces manquantes : un dossier d'admission sans commit ne
     * satisfait pas `INV-68`, et le dire vaut mieux que l'inventer (INV-39).
     */
    private function commitDuModule(?string $module, bool $introduction = false): ?string
    {
        if ($module === null || !is_dir($this->corpus . '/.git')) {
            return null;
        }
        // L'introduction est le PLUS ANCIEN commit qui ajoute un fichier du
        // module — `-1` seul rendrait le plus récent, donc l'acte le plus
        // tardif à y avoir ajouté un fichier, et non celui qui l'a livré.
        $sortie = @shell_exec(sprintf(
            'git -C %s log %s --format=%%H -- %s 2>/dev/null',
            escapeshellarg($this->corpus),
            $introduction ? '--diff-filter=A --reverse' : '-1',
            escapeshellarg('core/' . $module),
        ));

        $lignes = array_values(array_filter(array_map('trim', explode("\n", (string) $sortie))));
        $commit = $lignes === [] ? '' : $lignes[0];

        return preg_match('/^[0-9a-f]{40}$/', $commit) === 1 ? $commit : null;
    }

    // ------------------------------------------------------------------ interne

    /**
     * Relève l'Atlas : référence, libellé, domaine.
     *
     * @return array<string,array<string,string>>
     */
    private function atlas(): array
    {
        if ($this->atlas !== null) {
            return $this->atlas;
        }

        $atlas = [];
        foreach ($this->lignesTableau(self::ATLAS) as $c) {
            if (count($c) >= 3 && preg_match('/^`(CAP-CORE-\d{3})`$/', $c[0], $m)) {
                $atlas[$m[1]] = ['libelle' => $c[1], 'domaine' => $c[2]];
            }
        }

        return $this->atlas = $atlas;
    }

    /**
     * Relève les familles de contrat : référence, libellé, domaine gardien.
     *
     * La table de l'Article 69 de l'Atlas, complétée par les familles ajoutées
     * en fin de texte (Titre XIV). Seules sont retenues les lignes appartenant
     * à un tableau dont l'en-tête est celui des familles : l'Atlas porte
     * d'autres tableaux dont la première colonne est aussi un `CTR-XX` — le
     * relevé des emprunts, celui des partages —, et les confondre ferait
     * dépendre le contrôle de l'ordre des colonnes d'un tableau d'illustration.
     *
     * Cette opération est PUBLIQUE : le registre des contrats (CTR-06) la
     * consomme plutôt que de dupliquer le relevé. Deux analyseurs du même
     * tableau finiraient par diverger, et le corpus porterait alors deux
     * vérités sur ses propres contrats.
     *
     * @return array<string,array<string,string>>
     */
    public function familles(): array
    {
        if ($this->familles !== null) {
            return $this->familles;
        }

        $familles = [];
        $dansTableDesFamilles = false;
        foreach (explode("\n", $this->lire(self::ATLAS)) as $ligne) {
            $ligne = trim($ligne);
            if (!str_starts_with($ligne, '|')) {
                $dansTableDesFamilles = false;
                continue;
            }
            $c = array_map('trim', explode('|', trim($ligne, '|')));
            if (isset($c[1]) && $this->normaliser($c[0]) === 'référence'
                && str_contains($this->normaliser($c[1]), 'famille de contrat')) {
                $dansTableDesFamilles = true;
                continue;
            }
            if (!$dansTableDesFamilles || count($c) < 3) {
                continue;
            }
            if (preg_match('/^`(CTR-\d{2})`$/', $c[0], $m)) {
                $familles[$m[1]] = [
                    'reference' => $m[1],
                    'libelle'   => $this->sansAccolades($c[1]),
                    'gardien'   => $c[2],
                    'objet'     => $c[3] ?? '',
                ];
            }
        }

        return $this->familles = $familles;
    }

    /**
     * Codes de domaine contenus dans une cellule — « `DOM-02` / `DOM-08` »
     * en porte deux, « Transversal » aucun.
     *
     * @return list<string>
     */
    private function codesDomaine(string $cellule): array
    {
        preg_match_all('/DOM-\d{2}/', $cellule, $m);

        return array_values(array_unique($m[0]));
    }

    /**
     * Relève le Registre : fiche de chaque capacité et suite de ses états.
     *
     * @return array<string,array<string,mixed>>
     */
    private function registre(): array
    {
        if ($this->registre !== null) {
            return $this->registre;
        }

        $texte = $this->lire(self::REGISTRE);
        $capacites = [];

        // 1. Tableau de situation (Article 31) : identité et états initiaux.
        foreach ($this->lignesTableau(self::REGISTRE) as $c) {
            if (count($c) < 9 || !preg_match('/^`(CAP-CORE-\d{3})`$/', $c[0], $m)) {
                continue;
            }
            $capacites[$m[1]] = [
                'reference'  => $m[1],
                'libelle'    => $c[1],
                'domaine'    => $c[2],
                'criticite'  => $this->sansAccolades($c[3]),
                'etats'      => [
                    'conception'     => $this->sansAccolades($c[5]),
                    'implementation' => $this->sansAccolades($c[6]),
                    'exploitation'   => $this->sansAccolades($c[7]),
                    'preuve'         => $this->sansAccolades($c[8]),
                ],
                'contrats'   => [],
                'dependances' => null,
                'champs'     => ['responsable' => null, 'operateur' => null, 'sortie' => null],
            ];
        }

        // 1 bis. Capacités inscrites par un Titre de mise à jour postérieur.
        //        Le tableau de l'Article 31 est un texte adopté : il ne se
        //        réécrit pas, et une vingt et unième capacité ne peut donc pas
        //        y prendre place. Elle est inscrite en fin de texte, à une
        //        forme dérivable, et relevée ici au même titre qu'une ligne du
        //        tableau. Sans cela elle serait inscrite en prose, donc
        //        invisible au contrôle — le défaut exact qu'ADOPTION-0059 a
        //        réparé pour les décisions ouvertes.
        //
        //        Une inscription ne remplace jamais une ligne de l'Article 31 :
        //        elle CRÉE. Une capacité déjà présente au tableau est laissée
        //        intacte, faute de quoi un Titre postérieur pourrait réécrire
        //        en silence l'identité ou la criticité d'une capacité adoptée.
        foreach (explode("\n", $texte) as $ligne) {
            if (!preg_match(
                '/\*\*Inscription\s*:\*\*\s*`(CAP-CORE-\d{3})`\s*—\s*(.+?)\.\s*'
                . '\*\*Domaine\s*:\*\*\s*(.+?)\.\s*'
                . '\*\*Criticité\s*:\*\*\s*`([^`]+)`\.\s*'
                . '\*\*Conception\s*:\*\*\s*`([^`]+)`\.\s*'
                . '\*\*Implémentation\s*:\*\*\s*`([^`]+)`\.\s*'
                . '\*\*Exploitation\s*:\*\*\s*`([^`]+)`\.\s*'
                . '\*\*Preuve\s*:\*\*\s*`([^`]+)`\./u',
                trim($ligne),
                $m,
            )) {
                continue;
            }
            if (isset($capacites[$m[1]])) {
                continue;
            }
            $capacites[$m[1]] = [
                'reference'  => $m[1],
                'libelle'    => trim($m[2]),
                'domaine'    => trim($m[3]),
                'criticite'  => $this->sansAccolades($m[4]),
                'etats'      => [
                    'conception'     => $this->sansAccolades($m[5]),
                    'implementation' => $this->sansAccolades($m[6]),
                    'exploitation'   => $this->sansAccolades($m[7]),
                    'preuve'         => $this->sansAccolades($m[8]),
                ],
                'contrats'   => [],
                'dependances' => null,
                'champs'     => ['responsable' => null, 'operateur' => null, 'sortie' => null],
            ];
        }

        // 2. Fiches (Articles 36 à 55) : contrats attendus et dépendances.
        $fiche = null;
        foreach (explode("\n", $texte) as $ligne) {
            $ligne = trim($ligne);
            if (preg_match('/^## Article \d+ — (CAP-CORE-\d{3})\s*:/u', $ligne, $m)) {
                $fiche = $m[1];
                continue;
            }
            if ($fiche === null || !isset($capacites[$fiche])) {
                continue;
            }
            if (preg_match('/^- \*\*Contrats attendus\s*:\*\*(.+)$/u', $ligne, $m)) {
                preg_match_all('/`(CTR-\d{2})`/', $m[1], $mc);
                $capacites[$fiche]['contrats'] = array_values(array_unique($mc[1]));
            }
            if (preg_match('/^- \*\*Dépendances\s*:\*\*\s*(.+)$/u', $ligne, $m)) {
                $capacites[$fiche]['dependances'] = trim($m[1], ' .');
            }
        }

        // 2 bis. Contrats attribués par les Titres de mise à jour post-adoption.
        //        Un numéro peut être attribué ailleurs que dans la fiche — c'est
        //        le cas de CTR-09, donné par ADOPTION-0032. Ne lire que les
        //        fiches ferait apparaître ces capacités comme dépourvues de
        //        contrat, donc fantômes, ce qu'elles ne sont pas.
        $titre = null;
        foreach (explode("\n", $texte) as $ligne) {
            $ligne = trim($ligne);
            if (preg_match('/^\|\s*`(CAP-CORE-\d{3})`\s*—/u', $ligne, $m)) {
                $titre = $m[1];
                continue;
            }
            if ($titre === null || !isset($capacites[$titre])) {
                continue;
            }
            if (preg_match('/\*\*Contrat(?:\s+identifié)?\s*:?\*{0,2}\s*:?\s*`(CTR-\d{2})`/u', $ligne, $m)
                || preg_match('/\*\*Contrat\s+`(CTR-\d{2})`\s*:\*\*/u', $ligne, $m)) {
                if (!in_array($m[1], $capacites[$titre]['contrats'], true)) {
                    $capacites[$titre]['contrats'][] = $m[1];
                }
            }
        }

        // 2 ter. Réattributions déclarées par un Titre postérieur. Une famille
        //        attribuée par un texte adopté n'est jamais effacée de ce
        //        texte ; elle est RETIRÉE par une déclaration plus récente, qui
        //        nomme la capacité, la famille retirée et celle qui la
        //        remplace. Sans ce mécanisme, corriger une attribution fautive
        //        obligerait à réécrire l'article qui l'a portée.
        foreach (explode("\n", $texte) as $ligne) {
            if (!preg_match(
                '/\*\*Réattribution\s*:\*\*\s*`(CAP-CORE-\d{3})`\s*—\s*famille retirée\s*`(CTR-\d{2})`,\s*famille attribuée\s*`(CTR-\d{2})`/u',
                trim($ligne),
                $m,
            )) {
                continue;
            }
            [, $ref, $retiree, $attribuee] = $m;
            if (!isset($capacites[$ref])) {
                continue;
            }
            $contrats = array_values(array_filter(
                $capacites[$ref]['contrats'],
                static fn (string $c) => $c !== $retiree,
            ));
            if (!in_array($attribuee, $contrats, true)) {
                $contrats[] = $attribuee;
            }
            $capacites[$ref]['contrats'] = $contrats;
        }

        // 2 quater. Rattachements déclarés par un Titre postérieur. Une famille
        //           peut avoir été attribuée par une fiche SANS figurer au
        //           champ qui la recense — l'Article 48 nomme `CTR-07` dans sa
        //           ligne « État actuel », et la fiche de CAP-CORE-014 ne porte
        //           aucune ligne « Contrats attendus ». Un tel rattachement ne
        //           retire rien : il n'est donc pas une réattribution, et une
        //           déclaration distincte le porte.
        //
        //           La forme est exigée, non déduite : le service ne lit pas la
        //           prose d'une fiche pour y chercher une attribution. Déduire
        //           un rattachement d'une phrase serait le comblement que
        //           INV-43 interdit ; le porter à la forme dérivable est un
        //           acte de l'autorité (ADOPTION-0049).
        foreach (explode("\n", $texte) as $ligne) {
            if (!preg_match(
                '/\*\*Rattachement\s*:\*\*\s*`(CAP-CORE-\d{3})`\s*—\s*famille attribuée\s*`(CTR-\d{2})`/u',
                trim($ligne),
                $m,
            )) {
                continue;
            }
            [, $ref, $attribuee] = $m;
            if (!isset($capacites[$ref]) || in_array($attribuee, $capacites[$ref]['contrats'], true)) {
                continue;
            }
            $capacites[$ref]['contrats'][] = $attribuee;
        }

        // 3. Titres de mise à jour post-adoption, dans l'ordre du document —
        //    qui est l'ordre chronologique d'adoption. Le dernier prévaut.
        foreach (explode("\n", $texte) as $ligne) {
            $ligne = trim($ligne);
            if (!preg_match('/^\|\s*`(CAP-CORE-\d{3})`\s*—/u', $ligne, $m)) {
                continue;
            }
            $ref = $m[1];
            if (!isset($capacites[$ref])) {
                continue;
            }
            $cellules = array_map('trim', explode('|', trim($ligne, '|')));
            $constate = end($cellules);
            if (!is_string($constate)) {
                continue;
            }
            foreach ([
                'conception'     => 'Conception',
                'implementation' => 'implémentation',
                'exploitation'   => 'exploitation',
                'preuve'         => 'preuve',
            ] as $dimension => $etiquette) {
                if (preg_match('/' . $etiquette . '\s+\*{0,2}`([^`]+)`/ui', $constate, $mv)) {
                    $capacites[$ref]['etats'][$dimension] = $mv[1];
                }
            }
        }

        return $this->registre = $capacites;
    }

    /**
     * Cellules de chaque ligne de tableau d'un fichier du corpus.
     *
     * @return list<list<string>>
     */
    private function lignesTableau(string $chemin): array
    {
        $lignes = [];
        foreach (explode("\n", $this->lire($chemin)) as $ligne) {
            $ligne = trim($ligne);
            if (str_starts_with($ligne, '|')) {
                $lignes[] = array_map('trim', explode('|', trim($ligne, '|')));
            }
        }

        return $lignes;
    }

    private function lire(string $chemin): string
    {
        $fichier = $this->corpus . '/' . $chemin;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }

    private function sansAccolades(string $valeur): string
    {
        return trim($valeur, ' `*');
    }

    /**
     * Comparaison insensible aux variantes typographiques d'apostrophe, que
     * l'Atlas et le Registre n'écrivent pas toujours de la même façon. Une
     * divergence d'apostrophe n'est pas une divergence de fond, et la
     * rapporter comme telle noierait les vraies.
     */
    private function normaliser(string $valeur): string
    {
        return mb_strtolower(str_replace(['’', "'"], "'", trim($valeur)), 'UTF-8');
    }
}
