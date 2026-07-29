<?php

declare(strict_types=1);

namespace Gamad\RegistreDecisions;

/**
 * Les opérations du contrat CTR-05 — Cycle de décision
 * (CAP-CORE-008, conception adoptée par ADOPTION-0051).
 *
 * Lecture et attestation seulement : aucune écriture applicative du corpus
 * (INV-4). Le registre des décisions est le MIROIR des actes, jamais leur
 * source (INV-46). Décider est l'acte de l'autorité ; ce service en tient
 * l'inventaire et nomme ce qui manque.
 *
 * Ce service ne consomme aucun autre contrat. Il lit trois sources et les
 * confronte sans les réconcilier :
 *   · les actes présents sur le disque — l'existence ;
 *   · l'index consolidé des adoptions (Article 4) — la table tenue à jour ;
 *   · le tableau consolidé du Registre initial des décisions (Article 92) —
 *     la vue arrêtée à dix-sept adoptions, jamais prolongée.
 *
 * Ce que le service N'INVENTE PAS :
 *   · la clôture d'une décision ouverte que nul acte ne clôt (INV-47) ;
 *   · une réconciliation entre trois inventaires qui divergent (INV-48) ;
 *   · la traduction d'un statut vers le terme le plus proche du
 *     vocabulaire de l'Article 17 (INV-49) ;
 *   · la classe ou le niveau de risque d'une décision (INV-50).
 *
 * Invariants portés :
 *   INV-46 le registre dérive des actes, il n'en fonde aucun ·
 *   INV-47 une décision ouverte ne se clôt que par un acte qui la nomme ·
 *   INV-48 les inventaires sont confrontés, jamais réconciliés ·
 *   INV-49 un statut hors vocabulaire est nommé, jamais traduit ·
 *   INV-50 classe et niveau de risque ne sont pas déduits de l'objet.
 */
final class Ctr05
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-008';

    private const ACTES  = 'genesis-ii/registre';
    private const INDEX  = 'genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md';
    private const DECISIONS = 'genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md';

    /**
     * Le vocabulaire d'état d'une décision, Article 17 du Registre initial des
     * décisions. Il est reproduit ici pour être CONFRONTÉ, non pour être
     * appliqué : le service n'attribue aucun de ces états, il constate qu'un
     * statut employé par le corpus y figure ou n'y figure pas.
     */
    public const ETATS_DECISION = [
        'PROPOSÉE', 'VALIDÉE', 'ADOPTÉE', 'REJETÉE', 'PUBLIÉE', 'EN VIGUEUR',
        'EN EXÉCUTION', 'EXÉCUTÉE', 'SUSPENDUE', 'CONTESTÉE', 'AMENDÉE',
        'REMPLACÉE', 'ABROGÉE', 'EXPIRÉE', 'CLOSE',
    ];

    /**
     * Champs que l'Article 27 exige d'une inscription et que le corpus
     * n'établit pas pour toute décision.
     *
     * La classe fait exception : dix-sept adoptions en portent une au tableau
     * de l'Article 92. Elle est donc établie pour celles-là et non établie
     * pour les autres — ce qui se dérive, et ne se présume pas.
     */
    public const CHAMPS_DECLARABLES = ['classe', 'niveau_risque', 'dossier', 'contestation'];

    public const NON_ETABLI = 'NON ÉTABLI';

    /** @var array<string,array<string,mixed>>|null */
    private ?array $index = null;

    /** @var array<string,array<string,string>>|null */
    private ?array $consolide = null;

    /** @var array<string,array<string,mixed>>|null */
    private ?array $ouvertes = null;

    public function __construct(private string $corpus)
    {
    }

    // ------------------------------------------------------- inventaire des actes

    /**
     * Les actes présents sur le disque, groupés par référence.
     *
     * Une référence peut porter plusieurs fichiers — `ADOPTION-0025` porte
     * l'acte et sa feuille d'exécution. Compter les fichiers donnerait un
     * inventaire faux d'une unité ; le service compte les références.
     *
     * @return array<string,list<string>> référence => fichiers
     */
    public function actes(): array
    {
        $dossier = $this->corpus . '/' . self::ACTES;
        if (!is_dir($dossier)) {
            return [];
        }

        $actes = [];
        foreach (scandir($dossier) ?: [] as $fichier) {
            if (!preg_match('/^(ADOPTION-\d{4})-.*\.md$/', $fichier, $m)) {
                continue;
            }
            $actes[$m[1]][] = $fichier;
        }
        ksort($actes);

        return $actes;
    }

    /**
     * L'index consolidé des adoptions, Article 4 — la table que chaque acte
     * complète d'une ligne.
     *
     * @return array<string,array<string,mixed>>
     */
    public function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $lignes = [];
        foreach (explode("\n", $this->lire(self::INDEX)) as $ligne) {
            $ligne = trim($ligne);
            if (!str_starts_with($ligne, '|')) {
                continue;
            }
            $c = array_map('trim', explode('|', trim($ligne, '|')));
            if (count($c) < 5 || !preg_match('/^`(ADOPTION-\d{4})`$/', $c[0], $m)) {
                continue;
            }
            $lignes[$m[1]] = [
                'reference' => $m[1],
                'objet'     => $this->sansAccolades($c[1]),
                'autorite'  => $c[2],
                'date'      => $c[3],
                'statut'    => $this->sansAccolades($c[4]),
            ];
        }
        ksort($lignes);

        return $this->index = $lignes;
    }

    /**
     * Le tableau consolidé de l'Article 92 du Registre initial des décisions.
     *
     * Il s'arrête à `ADOPTION-0017` et n'a jamais été prolongé. Ce n'est pas
     * une faute du tableau : c'est un fait que l'Article 133 soumettait déjà
     * à l'autorité. Le service le relève tel quel.
     *
     * @return array<string,array<string,string>>
     */
    public function consolide(): array
    {
        if ($this->consolide !== null) {
            return $this->consolide;
        }

        $lignes = [];
        foreach (explode("\n", $this->lire(self::DECISIONS)) as $ligne) {
            $ligne = trim($ligne);
            if (!str_starts_with($ligne, '|')) {
                continue;
            }
            $c = array_map('trim', explode('|', trim($ligne, '|')));
            if (count($c) < 5 || !preg_match('/^`(ADOPTION-\d{4})`$/', $c[0], $m)) {
                continue;
            }
            $lignes[$m[1]] = [
                'reference' => $m[1],
                'objet'     => $c[1],
                'classe'    => $c[2],
                'etat'      => $c[3],
                'limite'    => $c[4],
            ];
        }
        ksort($lignes);

        return $this->consolide = $lignes;
    }

    /**
     * L'inventaire des décisions formelles, confronté sur ses trois termes.
     *
     * @return array<string,array<string,mixed>>
     */
    public function decisions(): array
    {
        $actes     = $this->actes();
        $index     = $this->index();
        $consolide = $this->consolide();

        $references = array_unique(array_merge(
            array_keys($actes),
            array_keys($index),
            array_keys($consolide),
        ));
        sort($references);

        $decisions = [];
        foreach ($references as $reference) {
            $classe = $consolide[$reference]['classe'] ?? null;
            $decisions[$reference] = [
                'reference'   => $reference,
                'objet'       => $index[$reference]['objet'] ?? ($consolide[$reference]['objet'] ?? null),
                'autorite'    => $index[$reference]['autorite'] ?? null,
                'date'        => $index[$reference]['date'] ?? null,
                'statut'      => $index[$reference]['statut'] ?? null,
                'acte_present' => isset($actes[$reference]),
                'fichiers'    => $actes[$reference] ?? [],
                'inscrit_index'     => isset($index[$reference]),
                'inscrit_consolide' => isset($consolide[$reference]),
                'champs'      => $this->champs($classe),
            ];
        }

        return $decisions;
    }

    /** @return array<string,mixed>|null */
    public function resoudreDecision(string $reference): ?array
    {
        return $this->decisions()[$reference] ?? null;
    }

    /**
     * Les trois inventaires, confrontés et NON réconciliés (INV-48).
     *
     * Un service qui alignerait le tableau de l'Article 92 sur l'index ferait
     * disparaître l'écart au lieu de le montrer, et le corpus perdrait la
     * trace d'une question que l'Article 133 pose à l'autorité.
     *
     * @return array<string,mixed>
     */
    public function inventaire(): array
    {
        $actes     = $this->actes();
        $index     = $this->index();
        $consolide = $this->consolide();

        return [
            'actes'      => count($actes),
            'fichiers'   => array_sum(array_map('count', $actes)),
            'index'      => count($index),
            'consolide'  => count($consolide),
            'absents_index'     => array_values(array_diff(array_keys($actes), array_keys($index))),
            'absents_disque'    => array_values(array_diff(array_keys($index), array_keys($actes))),
            'absents_consolide' => array_values(array_diff(array_keys($index), array_keys($consolide))),
            'hors_index'        => array_values(array_diff(array_keys($consolide), array_keys($index))),
        ];
    }

    // -------------------------------------------------------- décisions ouvertes

    /**
     * Les décisions demeurées ouvertes, dérivées de la FORME arrêtée par
     * l'Article 153 — jamais cherchées dans la prose.
     *
     * Chercher une décision ouverte dans une phrase, ce serait décider
     * laquelle en est une. Le corpus a tranché ce point pour les attributions
     * de contrat (ADOPTION-0049) ; il vaut ici de la même façon.
     *
     * @return array<string,array<string,mixed>>
     */
    public function inscrites(): array
    {
        if ($this->ouvertes !== null) {
            return $this->ouvertes;
        }

        $texte = $this->lire(self::DECISIONS);

        $inscrites = [];
        foreach (explode("\n", $texte) as $ligne) {
            if (!preg_match(
                '/\*\*Décision ouverte\s*:\*\*\s*`(DECISION-\d{4})`\s*—\s*(.+?)\.\s*\*\*Source\s*:\*\*\s*(.+?)\.\s*$/u',
                trim($ligne),
                $m,
            )) {
                continue;
            }
            $inscrites[$m[1]] = [
                'reference' => $m[1],
                'objet'     => trim($m[2]),
                'source'    => trim($m[3]),
                'close_par' => null,
                'champs'    => $this->champs(null),
            ];
        }

        // La clôture s'ajoute à l'inscription ; elle ne l'efface pas (INV-47).
        foreach (explode("\n", $texte) as $ligne) {
            if (!preg_match(
                '/\*\*Décision close\s*:\*\*\s*`(DECISION-\d{4})`\s*—\s*\*\*Par\s*:\*\*\s*`([^`]+)`/u',
                trim($ligne),
                $m,
            )) {
                continue;
            }
            if (isset($inscrites[$m[1]])) {
                $inscrites[$m[1]]['close_par'] = $m[2];
            }
        }

        ksort($inscrites);

        return $this->ouvertes = $inscrites;
    }

    /**
     * Les décisions encore ouvertes.
     *
     * Ni le silence, ni l'ancienneté, ni l'exécution d'un acte voisin ne
     * closent une décision : seule une déclaration de clôture la clôt
     * (INV-47, Article 154 ; Article 7 — non-adoption tacite).
     *
     * @return array<string,array<string,mixed>>
     */
    public function ouvertes(): array
    {
        return array_filter($this->inscrites(), static fn (array $d) => $d['close_par'] === null);
    }

    /** @return array<string,array<string,mixed>> */
    public function closes(): array
    {
        return array_filter($this->inscrites(), static fn (array $d) => $d['close_par'] !== null);
    }

    /**
     * Une clôture qui désigne un acte absent du dépôt ne clôt rien.
     *
     * @return array<string,string> décision => acte invoqué
     */
    public function cloturesSansActe(): array
    {
        $actes = $this->actes();

        $orphelines = [];
        foreach ($this->closes() as $reference => $d) {
            if (!isset($actes[(string) $d['close_par']])) {
                $orphelines[$reference] = (string) $d['close_par'];
            }
        }

        return $orphelines;
    }

    // ------------------------------------------------------------------ lots

    /**
     * Les actes de lot et les incréments qu'ils énumèrent (INV-51, Article 163).
     *
     * Un acte de lot adopte plusieurs incréments à la fois. Ce qu'il n'énumère
     * pas, il ne l'adopte pas — quand bien même la fusion l'aurait porté dans
     * `main`. L'énumération est ce qui distingue un lot examiné d'un bloc avalé.
     *
     * Le service lit la FORME, jamais la prose de l'acte.
     *
     * @return array<string,list<array<string,string>>> acte => incréments
     */
    public function lots(): array
    {
        $lots = [];
        foreach (array_keys($this->actes()) as $reference) {
            foreach ($this->actes()[$reference] as $fichier) {
                $texte = $this->lire(self::ACTES . '/' . $fichier);
                foreach (explode("\n", $texte) as $ligne) {
                    if (!preg_match(
                        '/\*\*Incrément\s*:\*\*\s*(.+?)\.\s*\*\*Commit\s*:\*\*\s*`([0-9a-f]{7,40})`\.\s*'
                        . '\*\*Capacité\s*:\*\*\s*`(CAP-CORE-\d{3})`\.\s*\*\*Garde\s*:\*\*\s*`([^`]+)`/u',
                        trim($ligne),
                        $m,
                    )) {
                        continue;
                    }
                    $lots[$reference][] = [
                        'acte'      => $reference,
                        'objet'     => trim($m[1]),
                        'commit'    => $m[2],
                        'capacite'  => $m[3],
                        'garde'     => $m[4],
                    ];
                }
            }
        }
        ksort($lots);

        return $lots;
    }

    /**
     * Les incréments de lot dont une garantie manque (Article 163).
     *
     * Le lot ne peut amoindrir aucune des garanties par incrément. Trois se
     * vérifient sur le disque sans interpréter quoi que ce soit : la capacité
     * nommée existe, la garde nommée existe, et l'intégration continue
     * l'exécute. Une garde que l'intégration continue n'exécute pas n'éprouve
     * rien, et l'énumération doit le montrer.
     *
     * @return list<array<string,string>>
     */
    public function incrementsDefaillants(): array
    {
        $capacites = $this->lire('genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md');
        $ci        = $this->lire('.github/workflows/gardes-comportement.yml');

        $defaillants = [];
        foreach ($this->lots() as $increments) {
            foreach ($increments as $increment) {
                $motifs = [];
                if (!str_contains($capacites, '`' . $increment['capacite'] . '`')) {
                    $motifs[] = 'capacité inconnue du Registre';
                }
                if (!is_file($this->corpus . '/' . $increment['garde'])) {
                    $motifs[] = 'garde absente du disque';
                }
                if (!str_contains($ci, $increment['garde'])) {
                    $motifs[] = 'garde non exécutée en intégration continue';
                }
                if ($motifs !== []) {
                    $defaillants[] = $increment + ['motif' => implode(' ; ', $motifs)];
                }
            }
        }

        return $defaillants;
    }

    // ------------------------------------------------------------- vocabulaire

    /**
     * Les statuts employés par l'index qui ne figurent pas au vocabulaire de
     * l'Article 17 (INV-49).
     *
     * Ils sont NOMMÉS, jamais traduits. « LU ET ADOPTÉ — EN VIGUEUR » ressemble
     * à `ADOPTÉE` suivi de `EN VIGUEUR`, et cette ressemblance est précisément
     * le piège : traduire ferait dire au corpus ce qu'il n'a pas écrit, et
     * l'écart cesserait d'être visible.
     *
     * @return array<string,list<string>> statut employé => références
     */
    public function statutsHorsVocabulaire(): array
    {
        $hors = [];
        foreach ($this->index() as $reference => $ligne) {
            $statut = (string) $ligne['statut'];
            if ($statut === '' || in_array($statut, self::ETATS_DECISION, true)) {
                continue;
            }
            $hors[$statut][] = $reference;
        }
        ksort($hors);

        return $hors;
    }

    /**
     * Registre des écarts — la synthèse qu'attendent les contrôles requis de
     * l'Article 43.
     *
     * @return array<string,mixed>
     */
    public function ecarts(): array
    {
        $inventaire = $this->inventaire();
        $hors       = $this->statutsHorsVocabulaire();

        return [
            'decisions_formelles' => $inventaire['actes'],
            'inventaire'          => $inventaire,
            'inscrites'           => count($this->inscrites()),
            'ouvertes'            => count($this->ouvertes()),
            'closes'              => count($this->closes()),
            'clotures_sans_acte'  => $this->cloturesSansActe(),
            'lots'                => count($this->lots()),
            'increments_de_lot'   => array_sum(array_map('count', $this->lots())),
            'increments_defaillants' => $this->incrementsDefaillants(),
            'statuts_hors_vocabulaire' => $hors,
            'statuts_distincts'   => count(array_unique(array_column($this->index(), 'statut'))),
            'champs_non_etablis'  => array_keys(array_filter(
                $this->champs(null),
                static fn (string $v) => $v === self::NON_ETABLI,
            )),
            'inscription_exhaustive' => self::NON_ETABLI,
            'portee' => "Inventaire dérivé des actes, jamais autoritatif (INV-46). Il confronte ; il ne réconcilie pas.",
        ];
    }

    // ------------------------------------------------------------------ interne

    /**
     * Les champs que l'Article 27 exige et que le corpus n'établit pas.
     *
     * La classe est restituée lorsqu'un texte la porte — dix-sept adoptions
     * en portent une —, et NON ÉTABLI sinon. L'Article 132 réserve à
     * l'autorité la confirmation de ces dix-sept ; le service n'en étend
     * aucune aux trente-trois autres par ressemblance d'objet (INV-50).
     *
     * @return array<string,string>
     */
    private function champs(?string $classe): array
    {
        $champs = [];
        foreach (self::CHAMPS_DECLARABLES as $champ) {
            $champs[$champ] = self::NON_ETABLI;
        }
        if ($classe !== null && $classe !== '') {
            $champs['classe'] = $classe;
        }

        return $champs;
    }

    private function lire(string $chemin): string
    {
        $fichier = $this->corpus . '/' . $chemin;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }

    private function sansAccolades(string $valeur): string
    {
        return trim(str_replace(['`', '**'], '', $valeur));
    }
}
