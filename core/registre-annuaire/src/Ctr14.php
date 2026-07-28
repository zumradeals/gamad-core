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
    private const REGISTRE = 'genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md';
    private const ATLAS    = 'genesis-ii/atlas/CORE-ATLAS-0001-atlas-initial-gamad-core.md';
    private const CI       = '.github/workflows/gardes-comportement.yml';

    /** Les quatre dimensions d'état, distinctes et jamais mêlées (INV-37). */
    public const DIMENSIONS = ['conception', 'implementation', 'exploitation', 'preuve'];

    /** @var array<string,array<string,mixed>>|null */
    private ?array $registre = null;

    /** @var array<string,array<string,string>>|null */
    private ?array $atlas = null;

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
     * Numéros de contrat revendiqués par plus d'une capacité.
     *
     * `ADOPTION-0032`, Art. 2.1 a arrêté la règle : les numéros sont attribués
     * dans l'ordre chronologique d'adoption de la conception qui les définit,
     * jamais par correspondance avec le numéro de la capacité servie, et ne
     * sont **jamais réemployés**. Une collision est donc une violation de cette
     * règle, ou une ambiguïté antérieure à elle.
     *
     * Le service la NOMME ; il ne la tranche pas (INV-38). Départager deux
     * textes adoptés est un acte de l'autorité.
     *
     * @return array<string,list<string>>
     */
    public function collisions(): array
    {
        return array_filter($this->attributions(), fn (array $caps) => count($caps) > 1);
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
        $collisions = $this->collisions();

        // Un contrat revendiqué par plusieurs capacités ne permet pas de dire à
        // laquelle appartient le code qui le sert. L'observation s'abstient
        // plutôt que de trancher.
        $contestes = array_values(array_filter($contrats, fn (string $c) => isset($collisions[$c])));
        $contrats = array_values(array_filter($contrats, fn (string $c) => !isset($collisions[$c])));

        $module = null;
        $garde = null;
        foreach ($contrats as $contrat) {
            $classe = 'Ctr' . substr($contrat, 4); // CTR-09 -> Ctr09
            foreach (glob($this->corpus . '/core/*/src/' . $classe . '.php') ?: [] as $trouve) {
                $module = basename(dirname(dirname($trouve)));
                $gardes = glob($this->corpus . '/core/' . $module . '/tests/*_p3.php') ?: [];
                $garde = $gardes === [] ? null : 'core/' . $module . '/tests/' . basename($gardes[0]);
                break 2;
            }
        }

        $ci = false;
        if ($garde !== null) {
            $workflow = $this->corpus . '/' . self::CI;
            $ci = is_file($workflow) && str_contains((string) file_get_contents($workflow), $garde);
        }

        return [
            'capacite'          => $reference,
            'contrats'          => $contrats,
            'contrats_contestes' => $contestes,
            'module'            => $module,
            'garde'             => $garde,
            'garde_en_ci'       => $ci,
            'code_present'      => $module !== null,
            'observable'        => $contestes === [],
        ];
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
            if (!$observe['observable']) {
                // Contrat contesté : la comparaison au réel est impossible sans
                // trancher, et trancher n'appartient pas au service.
                $lignes[] = [
                    'capacite'    => $ref,
                    'declare'     => $etats,
                    'observe'     => $observe,
                    'divergences' => ['CONTRAT CONTESTÉ — ' . implode(', ', $observe['contrats_contestes'])
                        . ' revendiqué(s) par plusieurs capacités ; la comparaison au réel est suspendue'],
                    'verdict'     => 'INDETERMINE',
                ];
                continue;
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
    public function ecarts(): array
    {
        $reel = $this->comparerReel();
        $atlas = $this->comparerAtlas();

        $divergentes = array_values(array_filter($reel, fn (array $l) => $l['verdict'] === 'DIVERGENCE'));
        $indetermines = array_values(array_filter($reel, fn (array $l) => $l['verdict'] === 'INDETERMINE'));
        $divergencesAtlas = array_values(array_filter($atlas, fn (array $l) => $l['verdict'] === 'DIVERGENCE'));

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
            'divergentes'          => count($divergentes),
            'divergences_par_type' => $parType,
            'atlas_divergent'      => count($divergencesAtlas),
            'indeterminees'        => count($indetermines),
            'collisions_contrat'   => $this->collisions(),
            'champs_non_etablis'   => $champsNonEtablis,
            'portee'               => "Annuaire dérivé, jamais autoritatif (INV-36). Il nomme les divergences ; il n'en arbitre aucune.",
        ];
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
