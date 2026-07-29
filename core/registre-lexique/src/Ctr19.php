<?php

declare(strict_types=1);

namespace Gamad\RegistreLexique;

/**
 * Les opérations du contrat CTR-19 — Résolution de terme
 * (CAP-CORE-010, conception adoptée par ADOPTION-0057).
 *
 * La famille `CTR-19` est créée par le Titre XVIII de `CORE-ATLAS-0001` et
 * rattachée à `CAP-CORE-010`. L'Article 45 énonçait ses contrats attendus en
 * prose — « résolution de terme, version applicable, ambiguïté et changement
 * lexical » — sans qu'aucune famille de l'Article 69 ne les porte.
 *
 * Lecture et attestation seulement (INV-4). Ce service ne crée, ne modifie,
 * ne déprécie aucune entrée de `LEXICON-0001` et ne tranche aucune ambiguïté.
 *
 * Invariants portés :
 *   INV-63 une observation reportée n'est pas une observation tranchée ·
 *   INV-64 une version de référence se vérifie, elle ne se présume pas.
 */
final class Ctr19
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-010';

    private const LEXIQUE  = 'genesis-ii/lexique/LEXICON-0001-lexique-canonique-gamad-core.md';
    private const REGISTRE = 'genesis-ii/registres/lexique/REGISTRE-LEXICAL-INITIAL-0001.md';

    /**
     * Ce que l'Article 45 attend, et que le corpus n'établit pas. Ce sont les
     * « décisions ouvertes » de sa fiche, réservées à l'autorité.
     */
    public const CHAMPS_DECLARABLES = [
        'regles_de_numerotation',
        'statut_des_synonymes',
        'gouvernance_des_termes_locaux',
    ];

    public const NON_ETABLI  = 'NON ÉTABLI';
    public const NON_TRANCHE = 'NON TRANCHÉE';

    public function __construct(private string $corpus)
    {
    }

    /**
     * La version de référence du Lexique, VÉRIFIÉE et non présumée (INV-64).
     *
     * L'Article 6 du Registre lexical déclare l'empreinte de contenu du
     * Lexique en vigueur. C'est la première déclaration du corpus qu'un
     * service peut recalculer et confronter à sa source.
     *
     * Le service recalcule l'empreinte Git du fichier canonique et restitue la
     * concordance ou l'écart. Il NE MET À JOUR AUCUNE empreinte déclarée : un
     * écart est un fait à soumettre à l'autorité, non un défaut à corriger
     * d'office (INV-43).
     *
     * @return array<string,mixed>
     */
    public function versionDeReference(): array
    {
        $registre = $this->lire(self::REGISTRE);

        $version = null;
        $declaree = null;
        if (preg_match('/version\s+`([0-9.]+)`\s+de\s+`LEXICON-0001`/u', $registre, $m)) {
            $version = $m[1];
        }
        if (preg_match('/empreinte de contenu\s+`([0-9a-f]{40})`/u', $registre, $m)) {
            $declaree = $m[1];
        }

        $contenu  = $this->lire(self::LEXIQUE);
        $reelle   = $contenu === '' ? null : $this->empreinteGit($contenu);

        return [
            'version'            => $version,
            'empreinte_declaree' => $declaree,
            'empreinte_reelle'   => $reelle,
            'concordante'        => $declaree !== null && $reelle !== null && $declaree === $reelle,
            'source'             => 'REGISTRE-LEXICAL-INITIAL-0001, Article 6',
            'portee'             => 'Le service recalcule ; il ne met à jour aucune empreinte déclarée (INV-43).',
        ];
    }

    /**
     * Les entrées du Lexique canonique, dérivées de leur forme.
     *
     * @return array<int,array<string,string>> numéro d'entrée => terme, définition
     */
    public function entrees(): array
    {
        $entrees = [];
        if (!preg_match_all(
            '/^## Entrée (\d+) — (.+?)\s*$\n\n(.+?)$/mu',
            $this->lire(self::LEXIQUE),
            $matches,
            PREG_SET_ORDER,
        )) {
            return $entrees;
        }

        foreach ($matches as $m) {
            $entrees[(int) $m[1]] = ['terme' => trim($m[2]), 'definition' => trim($m[3])];
        }

        return $entrees;
    }

    /**
     * Résoudre un terme : sa définition, sa version applicable et le texte qui
     * la porte. C'est l'opération centrale de `CTR-19`.
     *
     * La comparaison est insensible à la casse et aux accents composés, mais
     * n'admet aucun rapprochement approximatif : un terme absent est restitué
     * comme absent, jamais rapproché du plus ressemblant. Rapprocher serait
     * trancher une ambiguïté que l'Article 45 réserve à l'autorité.
     *
     * @return array<string,mixed>
     */
    public function resoudreTerme(string $terme): array
    {
        $cherche = $this->normaliser($terme);
        $version = $this->versionDeReference();

        foreach ($this->entrees() as $numero => $entree) {
            if ($this->normaliser($entree['terme']) !== $cherche) {
                continue;
            }

            return [
                'terme'             => $entree['terme'],
                'trouve'            => true,
                'entree'            => $numero,
                'definition'        => $entree['definition'],
                'version_applicable' => $version['version'],
                'source'            => 'LEXICON-0001, Entrée ' . $numero,
            ];
        }

        return [
            'terme'              => $terme,
            'trouve'             => false,
            'entree'             => null,
            'definition'         => null,
            'version_applicable' => $version['version'],
            'source'             => null,
        ];
    }

    /**
     * Les observations lexicales que le corpus signale SANS LES TRANCHER, et
     * les textes qui les reprennent sans les trancher davantage (INV-63).
     *
     * L'Article 8 du Registre lexical signale « pour examen » un terme absent
     * des entrées du Lexique, et refuse expressément de l'ignorer comme de
     * l'ajouter d'office. Cette observation a été REPORTÉE au registre de
     * qualité, qui la mentionne « pour visibilité croisée » tout en déclarant
     * qu'elle ne relève pas de son objet.
     *
     * Un report n'est pas un arbitrage. Le service compte les arbitrages,
     * jamais les mentions : deux textes qui signalent la même observation sans
     * la trancher ne font pas un traitement.
     *
     * @return list<array<string,mixed>>
     */
    public function observationsNonTranchees(): array
    {
        $registre = $this->lire(self::REGISTRE);

        if (!preg_match('/^## Article \d+ — Observation non tranchée\s*$\n\n(.+?)$/mu', $registre, $m)) {
            return [];
        }
        $constat = trim($m[1]);

        if (!preg_match('/«\s*(.+?)\s*»/u', $constat, $t)) {
            return [];
        }
        $terme = $t[1];

        $resolution = $this->resoudreTerme($terme);
        $usages     = [];
        $reports    = [];

        foreach ($this->textesDuCorpus() as $chemin) {
            $contenu = $this->lire($chemin);
            if (!str_contains($contenu, $terme)) {
                continue;
            }
            if ($chemin === self::REGISTRE) {
                continue; // le texte qui signale l'observation n'en est pas un usage
            }
            // Un texte qui cite le registre lexical en même temps que le terme
            // reprend l'observation ; il ne l'emploie pas pour son compte.
            if (str_contains($contenu, 'REGISTRE-LEXICAL-INITIAL-0001')) {
                $reports[] = $chemin;
                continue;
            }
            $usages[] = $chemin;
        }

        return [[
            'terme'              => $terme,
            'constat'            => $constat,
            'statut'             => self::NON_TRANCHE,
            'present_au_lexique' => $resolution['trouve'],
            'signalee_par'       => self::REGISTRE,
            'reportee_dans'      => $reports,
            'employe_dans'       => $usages,
            'arbitrages'         => 0,
            'portee'             => 'Le service compte les arbitrages, jamais les mentions (INV-63).',
        ]];
    }

    /**
     * Les décisions lexicales et conflits terminologiques enregistrés.
     *
     * L'Article 7 déclare qu'aucun des deux n'existe. C'est une déclaration
     * motivée d'absence, au sens de `INV-59` : le registre est ouvert et vide,
     * et il le dit. Le service restitue l'absence AVEC son motif, et ne la
     * confond pas avec un registre inexistant.
     *
     * @return array<string,mixed>
     */
    public function decisionsEtConflits(): array
    {
        $registre = $this->lire(self::REGISTRE);

        $motif = null;
        if (preg_match(
            '/^## Article \d+ — Absence de décision lexicale et de conflit enregistré\s*$\n\n(.+?)$/mu',
            $registre,
            $m,
        )) {
            $motif = trim($m[1]);
        }

        return [
            'decisions_lexicales'  => 0,
            'conflits'             => 0,
            'absence_declaree'     => $motif !== null,
            'motif'                => $motif,
            'qualification'        => $motif === null
                ? self::NON_ETABLI
                : 'absence déclarée et motivée — registre ouvert et vide (INV-59)',
            'source'               => 'REGISTRE-LEXICAL-INITIAL-0001, Article 7',
        ];
    }

    /** @return array<string,string> */
    public function champs(): array
    {
        $champs = [];
        foreach (self::CHAMPS_DECLARABLES as $champ) {
            $champs[$champ] = self::NON_ETABLI;
        }

        return $champs;
    }

    /** @return array<string,mixed> */
    public function ecarts(): array
    {
        $version       = $this->versionDeReference();
        $observations  = $this->observationsNonTranchees();
        $decisions     = $this->decisionsEtConflits();

        return [
            'entrees'                  => count($this->entrees()),
            'version_de_reference'     => $version['version'],
            'empreinte_concordante'    => $version['concordante'],
            'observations_non_tranchees' => count($observations),
            'decisions_lexicales'      => $decisions['decisions_lexicales'],
            'conflits'                 => $decisions['conflits'],
            'absence_declaree'         => $decisions['absence_declaree'],
            'champs_non_etablis'       => array_keys($this->champs()),
            'controle_lexical_mecanise' => false,
            'portee' => "Le service résout, vérifie et relève. Il ne tranche aucune ambiguïté et ne modifie aucune entrée (INV-63).",
        ];
    }

    // ------------------------------------------------------------------ interne

    /**
     * L'empreinte Git d'un contenu — `sha1("blob " . taille . "\0" . contenu)`.
     *
     * Calculée en PHP, sans appel à `git` : la garde doit pouvoir s'exécuter
     * sur une copie hors dépôt, où aucun objet Git n'existe.
     */
    private function empreinteGit(string $contenu): string
    {
        return sha1('blob ' . strlen($contenu) . "\0" . $contenu);
    }

    private function normaliser(string $terme): string
    {
        $terme = trim($terme);
        $terme = preg_replace('/\s+/u', ' ', $terme) ?? $terme;

        return mb_strtolower($terme, 'UTF-8');
    }

    /**
     * Les textes du corpus, hors dépendances et hors répertoire Git.
     *
     * @return list<string> chemins relatifs au corpus
     */
    private function textesDuCorpus(): array
    {
        $racine = $this->corpus . '/genesis-ii';
        if (!is_dir($racine)) {
            return [];
        }

        $chemins  = [];
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $racine,
            \FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($iterateur as $fichier) {
            if (!$fichier instanceof \SplFileInfo || $fichier->getExtension() !== 'md') {
                continue;
            }
            $chemins[] = substr($fichier->getPathname(), strlen($this->corpus) + 1);
        }

        sort($chemins);

        return $chemins;
    }

    private function lire(string $chemin): string
    {
        $fichier = $this->corpus . '/' . $chemin;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
