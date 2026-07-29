<?php

declare(strict_types=1);

namespace Gamad\RegistreEvenements;

/**
 * Les opérations du contrat CTR-07 — Événement commun
 * (CAP-CORE-014, conception adoptée par ADOPTION-0057).
 *
 * `CTR-07` est gardée par `DOM-06` et rattachée à `CAP-CORE-014` par le Titre
 * XXX du Registre initial des capacités souveraines, sur constat
 * d'`ADOPTION-0049`. `CAP-CORE-014` garde `DOM-06` et `DOM-09` : `INV-40` est
 * satisfait, et la famille n'est partagée avec aucune autre capacité.
 *
 * Lecture et attestation seulement (INV-4).
 *
 * CE SERVICE N'A PRESQUE RIEN À LIRE, ET C'EST SON OBJET.
 * `CAP-CORE-014` est la seule des vingt capacités à posséder une famille de
 * contrat adoptée sans posséder AUCUN registre : ni journal, ni modèle, ni
 * type d'événement, ni convention de version, ni politique de conservation —
 * et aucune déclaration motivée de cette absence.
 *
 * Invariant porté :
 *   INV-65 une famille de contrat adoptée n'est pas un registre établi.
 */
final class Ctr07
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-014';

    private const CAPACITES  = 'genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md';
    private const ATLAS      = 'genesis-ii/atlas/CORE-ATLAS-0001-atlas-initial-gamad-core.md';
    private const REGISTRES  = 'genesis-ii/registres';

    private const INCIDENTS   = 'genesis-ii/registres/securite/REGISTRE-INITIAL-INCIDENTS-SECURITE-0001.md';
    private const SAUVEGARDES = 'genesis-ii/registres/securite/REGISTRE-INITIAL-SAUVEGARDES-RESTAURATIONS-0001.md';

    /**
     * Ce que l'Article 48 attend, et que le corpus n'établit pas. Ce sont ses
     * décisions ouvertes, réservées à l'autorité.
     */
    public const CHAMPS_DECLARABLES = [
        'types_d_evenement',
        'convention_de_version',
        'garanties_de_livraison',
        'ordre',
        'conservation',
        'catalogue_des_consommateurs',
    ];

    public const NON_ETABLI = 'NON ÉTABLI';

    /** Les trois espèces d'absence que le corpus distingue. */
    public const ABSENCE_MOTIVEE   = 'ABSENCE DÉCLARÉE ET MOTIVÉE';
    public const ABSENCE_EXCLUSION = 'ABSENCE PAR EXCLUSION DE MISSION';
    public const ABSENCE_NON_DECLAREE = 'ABSENCE NON DÉCLARÉE';

    public function __construct(private string $corpus)
    {
    }

    /**
     * La famille de contrat rattachée, telle que le corpus la déclare.
     *
     * Ce rattachement TIENT : il est dérivable depuis le Titre XXX du Registre
     * des capacités. Le service le restitue tel quel — c'est la moitié de
     * `INV-65`, celle qui est établie.
     *
     * @return array<string,mixed>
     */
    public function familleRattachee(): array
    {
        $capacites = $this->lire(self::CAPACITES);

        $rattachee = null;
        if (preg_match(
            '/\*\*Rattachement\s*:\*\*\s*`' . self::CAPACITE . '`\s*—\s*famille attribuée\s*`(CTR-\d+)`/u',
            $capacites,
            $m,
        )) {
            $rattachee = $m[1];
        }

        $objet = null;
        $gardien = null;
        if ($rattachee !== null && preg_match(
            '/\|\s*`' . preg_quote($rattachee, '/') . '`\s*\|\s*(.+?)\s*\|\s*`(DOM-\d+)`\s*\|/u',
            $this->lire(self::ATLAS),
            $m,
        )) {
            $objet   = trim($m[1]);
            $gardien = $m[2];
        }

        return [
            'famille'  => $rattachee,
            'objet'    => $objet,
            'gardien'  => $gardien,
            'adoptee'  => $rattachee !== null,
            'source'   => 'REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001, Titre XXX ; ADOPTION-0049',
        ];
    }

    /**
     * Le journal d'événements — et le fait qu'il n'existe pas (INV-65).
     *
     * Le service cherche un registre d'événements dans le corpus, et cherche
     * une déclaration motivée de son absence. Il ne trouve ni l'un ni l'autre.
     *
     * L'existence de `CTR-07` n'établit ni les types, ni le mécanisme, ni la
     * conservation. Une famille de contrat adoptée n'est pas un registre
     * établi : c'est la seconde moitié de `INV-65`, celle qui ne l'est pas.
     *
     * @return array<string,mixed>
     */
    public function journal(): array
    {
        $registre = $this->registreDEvenements();
        $motivee  = $this->declarationMotiveeDAbsence();

        return [
            'registre'            => $registre,
            'existe'              => $registre !== null,
            'declaration_motivee' => $motivee,
            'espece'              => $this->especeDAbsence($registre !== null, $motivee !== null, false),
            'types_etablis'       => 0,
            'source'              => 'REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001, Article 48',
            'portee'              => 'Le service n\'invente aucun type d\'événement ni aucune convention (INV-65).',
        ];
    }

    /**
     * Les trois espèces d'absence que le corpus distingue, dérivées de leurs
     * textes plutôt qu'énoncées de mémoire.
     *
     * `INV-59` séparait déjà les deux premières. La troisième est la plus
     * dangereuse, parce qu'elle ne se distingue d'un oubli par aucun signe :
     * rien n'est écrit, donc rien ne peut être vérifié.
     *
     * @return list<array<string,mixed>>
     */
    public function especesDAbsence(): array
    {
        $incidents = $this->lire(self::INCIDENTS);
        $sauvegardes = $this->lire(self::SAUVEGARDES);

        $incidentsMotivee = (bool) preg_match(
            '/^## Article \d+ — (?:Absence|Aucun incident)[^\n]*$/mu',
            $incidents,
        );
        $sauvegardesExclusion = (bool) preg_match(
            '/^## Article \d+ — Exclusion explicite de mission\s*$/mu',
            $sauvegardes,
        );

        $journal = $this->journal();

        return [
            [
                'capacite' => 'CAP-CORE-018',
                'objet'    => 'registre des incidents',
                'registre_existe' => $incidents !== '',
                'espece'   => $this->especeDAbsence($incidents !== '', $incidentsMotivee, false),
            ],
            [
                'capacite' => 'CAP-CORE-019',
                'objet'    => 'registre des sauvegardes',
                'registre_existe' => $sauvegardes !== '',
                'espece'   => $this->especeDAbsence($sauvegardes !== '', false, $sauvegardesExclusion),
            ],
            [
                'capacite' => self::CAPACITE,
                'objet'    => 'journal d\'événements communs',
                'registre_existe' => $journal['existe'],
                'espece'   => $journal['espece'],
            ],
        ];
    }

    /**
     * Les données minimales et les données EXCLUES que l'Article 48 énonce.
     *
     * `CAP-CORE-014` est l'une des rares fiches à porter une ligne « Données
     * exclues ». Le service la restitue : ce qu'un journal ne doit jamais
     * porter vaut, ici, autant que ce qu'il doit porter.
     *
     * @return array<string,list<string>>
     */
    public function donnees(): array
    {
        $fiche = $this->fiche();

        return [
            'minimales' => $this->champsDeLigne($fiche, 'Données minimales'),
            'exclues'   => $this->champsDeLigne($fiche, 'Données exclues'),
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
        $famille = $this->familleRattachee();
        $journal = $this->journal();
        $donnees = $this->donnees();

        return [
            'famille_adoptee'      => $famille['adoptee'],
            'famille'              => $famille['famille'],
            'journal_etabli'       => $journal['existe'],
            'espece_d_absence'     => $journal['espece'],
            'declaration_motivee'  => $journal['declaration_motivee'] !== null,
            'types_etablis'        => $journal['types_etablis'],
            'donnees_minimales'    => count($donnees['minimales']),
            'donnees_exclues'      => count($donnees['exclues']),
            'champs_non_etablis'   => array_keys($this->champs()),
            'especes_distinguees'  => count($this->especesDAbsence()),
            'portee' => 'Une famille de contrat adoptée n\'est pas un registre établi. Le service distingue ce que le corpus a adopté de ce qu\'il n\'a pas établi (INV-65).',
        ];
    }

    // ------------------------------------------------------------------ interne

    private function especeDAbsence(bool $registre, bool $motivee, bool $exclusion): string
    {
        if ($exclusion) {
            return self::ABSENCE_EXCLUSION;
        }
        if ($registre && $motivee) {
            return self::ABSENCE_MOTIVEE;
        }

        return self::ABSENCE_NON_DECLAREE;
    }

    /**
     * Un registre d'événements dans le corpus, s'il en existe un.
     *
     * La recherche porte sur le titre de niveau 1 des registres, et non sur le
     * nom de fichier : un registre se reconnaît à ce qu'il déclare être.
     */
    private function registreDEvenements(): ?string
    {
        $racine = $this->corpus . '/' . self::REGISTRES;
        if (!is_dir($racine)) {
            return null;
        }

        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $racine,
            \FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($iterateur as $fichier) {
            if (!$fichier instanceof \SplFileInfo || $fichier->getExtension() !== 'md') {
                continue;
            }
            $chemin = substr($fichier->getPathname(), strlen($this->corpus) + 1);
            if (!preg_match('/^#\s+(.+)$/mu', $this->lire($chemin), $m)) {
                continue;
            }
            // La reconnaissance est INSENSIBLE AUX ACCENTS. Un registre
            // intitulé « REGISTRE DES EVENEMENTS » sans accents est le même
            // registre ; le manquer ferait déclarer une absence là où le
            // corpus porte un texte, et `INV-65` restituerait un faux.
            if (preg_match('/\bEVENEMENTS?\b/iu', $this->sansAccents($m[1]))) {
                return $chemin;
            }
        }

        return null;
    }

    /**
     * Une déclaration motivée d'absence de journal, s'il en existe une.
     *
     * Au sens de `INV-59` : un texte adopté qui déclare l'absence ET son
     * motif. Une mention en passant n'en est pas une.
     */
    private function declarationMotiveeDAbsence(): ?string
    {
        $registre = $this->registreDEvenements();
        if ($registre === null) {
            return null;
        }

        if (preg_match('/^## Article \d+ — (?:Absence|Exclusion)[^\n]*$/mu', $this->lire($registre))) {
            return $registre;
        }

        return null;
    }

    /** La fiche de la capacité au Registre initial des capacités. */
    private function fiche(): string
    {
        if (!preg_match(
            '/^## Article \d+ — ' . self::CAPACITE . '[^\n]*$\n\n(.+?)(?=\n\n---|\n\n## )/msu',
            $this->lire(self::CAPACITES),
            $m,
        )) {
            return '';
        }

        return $m[1];
    }

    /** @return list<string> */
    private function champsDeLigne(string $fiche, string $ligne): array
    {
        if (!preg_match('/^\-\s*\*\*' . preg_quote($ligne, '/') . '\s*:\*\*\s*(.+?)$/mu', $fiche, $m)) {
            return [];
        }

        $valeurs = preg_split('/,\s*|\s+et\s+/u', rtrim(trim($m[1]), '.')) ?: [];

        return array_values(array_filter(array_map('trim', $valeurs), static fn (string $v): bool => $v !== ''));
    }

    /**
     * Un texte débarrassé de ses signes diacritiques, pour que la
     * reconnaissance d'un intitulé ne dépende pas de sa saisie.
     */
    private function sansAccents(string $texte): string
    {
        $translitteration = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);

        return is_string($translitteration) ? $translitteration : $texte;
    }

    private function lire(string $chemin): string
    {
        $fichier = $this->corpus . '/' . $chemin;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
