<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

/**
 * Les quatre opérations du contrat CTR-10 — Preuves d'intégrité
 * (CAP-CORE-015, conception adoptée par ADOPTION-0043, Titre III, Article 14).
 *
 * Lecture et attestation seulement : aucune écriture applicative du corpus
 * (INV-4). Le service ne modifie aucun fichier, n'ajoute aucune déclaration et
 * ne corrige aucune empreinte.
 *
 * CE SERVICE NE LIT PAS L'INDEX DÉRIVÉ, ET C'EST DÉLIBÉRÉ. Il relève les
 * déclarations directement dans les actes d'adoption et recalcule les
 * empreintes depuis les fichiers. Il constitue ainsi une SECONDE
 * IMPLÉMENTATION, indépendante du contrôle Python C5 comme de l'ingestion de
 * CTR-04 (Article 23 de la conception). Deux implémentations indépendantes qui
 * concordent valent mieux qu'une seule mutualisée — a fortiori lorsque l'objet
 * vérifié est la vérification elle-même.
 *
 * Invariants portés :
 *   INV-31 empreinte nommée · INV-32 double conservation mesurée ·
 *   INV-33 migration par ajout · INV-34 attestation non signée déclarée telle ·
 *   INV-35 vérification par recalcul.
 *
 * Aucune clé, aucun secret, aucune signature (ADOPTION-0025, Art. 3.a).
 */
final class Ctr10
{
    /**
     * La capacité souveraine que ce module sert (INV-41).
     *
     * Une famille de contrat peut servir deux capacités — `CTR-10` sert
     * l'audit et l'intégrité. Le numéro de famille ne suffit donc pas à
     * rattacher un module ; le module le déclare lui-même.
     */
    public const CAPACITE = 'CAP-CORE-015';

    /** Registre adopté portant la politique des algorithmes (Titre XXVI). */
    private const REGISTRE_POLITIQUE =
        'genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md';

    /** @var list<array<string,mixed>>|null */
    private ?array $politique = null;

    /** @var array<string,array{valeur:string,acte:string,rang:int}>|null */
    private ?array $declarations = null;

    public function __construct(
        private string $corpus,
    ) {
    }

    /**
     * Restitue la politique des algorithmes : lesquels sont admis, affaiblis ou
     * révoqués, et lequel fait foi.
     *
     * La politique est DÉRIVÉE du registre adopté, jamais codée en dur : un
     * service qui porterait sa propre politique en constantes déciderait à la
     * place de l'autorité.
     *
     * @return list<array<string,mixed>>
     */
    public function politique(): array
    {
        if ($this->politique !== null) {
            return $this->politique;
        }

        $fichier = $this->corpus . '/' . self::REGISTRE_POLITIQUE;
        $texte = is_file($fichier) ? (string) file_get_contents($fichier) : '';

        $algorithmes = [];
        foreach (explode("\n", $texte) as $ligne) {
            $ligne = trim($ligne);
            if (!str_starts_with($ligne, '|')) {
                continue;
            }
            $c = array_map('trim', explode('|', trim($ligne, '|')));
            if (count($c) < 5) {
                continue;
            }
            // | `code` | libellé | `STATUT` | `oui`/`non` | motif |
            if (!preg_match('/^`([a-z0-9-]+)`$/', $c[0], $mc)) {
                continue;
            }
            if (!preg_match('/^`(ADMIS|AFFAIBLI|RÉVOQUÉ)`$/u', $c[2], $ms)) {
                continue;
            }
            $algorithmes[] = [
                'code'     => $mc[1],
                'libelle'  => $c[1],
                'statut'   => $ms[1],
                'fait_foi' => preg_match('/^`?oui`?$/i', $c[3]) === 1,
                'motif'    => $c[4],
            ];
        }

        return $this->politique = $algorithmes;
    }

    /**
     * Algorithmes utilisables pour un calcul : tout ce qui n'est pas révoqué.
     *
     * Un algorithme AFFAIBLI reste calculable — il faut bien pouvoir vérifier
     * les déclarations qui l'emploient. Seule la révocation le retire.
     *
     * @return list<string>
     */
    public function algorithmesActifs(): array
    {
        $actifs = [];
        foreach ($this->politique() as $a) {
            if ($a['statut'] !== 'RÉVOQUÉ') {
                $actifs[] = $a['code'];
            }
        }

        return $actifs;
    }

    /**
     * Émet les empreintes d'un objet du corpus, par chaque algorithme non
     * révoqué.
     *
     * `signee` est rendu à `false` et non omis : une attestation dont l'absence
     * de signature serait tacite finirait citée comme preuve d'origine
     * (INV-34, menace M-35).
     *
     * @return array<string,mixed>|null `null` si l'objet n'existe pas
     */
    public function emettre(string $objet): ?array
    {
        $fichier = $this->corpus . '/' . ltrim($objet, '/');
        if (!is_file($fichier)) {
            return null;
        }

        $empreintes = [];
        foreach ($this->algorithmesActifs() as $algorithme) {
            $empreintes[] = [
                'algorithme' => $algorithme,
                'valeur'     => Empreinte::calculerFichier($algorithme, $fichier),
                'origine'    => 'CALCULÉE',
            ];
        }

        return [
            'objet'      => $objet,
            'empreintes' => $empreintes,
            'moment'     => gmdate('c'),
            'signee'     => false,
            'portee'     => "Calcul local, non signé et non horodaté par un tiers de confiance.",
        ];
    }

    /**
     * Relève toutes les empreintes DÉCLARÉES par le corpus.
     *
     * Le périmètre est celui du contrôle `C5` — tout fichier `.md` du corpus
     * peut déclarer, et les feuilles de statut déclarent l'empreinte d'origine
     * de leur texte compagnon. Il est repris à dessein : deux implémentations
     * ne se contrôlent l'une l'autre que si elles portent sur la MÊME
     * affirmation. Un périmètre plus étroit produirait deux chiffres
     * incomparables, dont le désaccord n'apprendrait rien.
     *
     * Le rang départage : une déclaration portée par `ADOPTION-0042` dépasse
     * celle portée pour le même fichier par `ADOPTION-0041`, l'adoption la
     * plus récente étant celle qui lie le contenu publié. Hors registre
     * d'adoption — feuilles de statut comprises — le rang est 0 : déclaration
     * d'origine, dépassée par tout acte postérieur.
     *
     * @return array<string,array{valeur:string,declarant:string,rang:int}>
     */
    public function declarations(): array
    {
        if ($this->declarations !== null) {
            return $this->declarations;
        }

        $declarations = [];
        $retenir = function (string $chemin, string $valeur, string $declarant, int $rang) use (&$declarations): void {
            if (!isset($declarations[$chemin]) || $rang >= $declarations[$chemin]['rang']) {
                $declarations[$chemin] = ['valeur' => $valeur, 'declarant' => $declarant, 'rang' => $rang];
            }
        };

        $motifLigne = '/^\|\s*`([^`]+?\.(?:md|py|yml))`[^|]*\|(?:[^|]*\|)*?\s*`([0-9a-f]{40})`\s*\|\s*$/u';
        $motifStatut = '/^-\s*\*\*Empreinte Git du contenu adopté\s*:\*\*\s*`([0-9a-f]{40})`/u';

        foreach ($this->fichiersDuCorpus() as $relatif) {
            $texte = (string) file_get_contents($this->corpus . '/' . $relatif);
            $rang = $this->rangDeclarant($relatif);

            foreach (explode("\n", $texte) as $ligne) {
                if (preg_match($motifLigne, trim($ligne), $m)) {
                    $retenir($m[1], $m[2], $relatif, $rang);
                }
            }

            // Feuille de statut : déclaration d'origine de son texte compagnon.
            if (str_ends_with($relatif, '-STATUT.md')) {
                $compagnon = $this->texteCompagnon($relatif);
                if ($compagnon === null) {
                    continue; // lien non établi : mieux vaut non contrôlé que mal rapporté
                }
                foreach (explode("\n", $texte) as $ligne) {
                    if (preg_match($motifStatut, trim($ligne), $m)) {
                        $retenir($compagnon, $m[1], $relatif, 0);
                        break; // une seule déclaration par feuille
                    }
                }
            }
        }
        ksort($declarations);

        return $this->declarations = $declarations;
    }

    /**
     * Rang du texte déclarant : le numéro de l'acte, ou 0 hors registre
     * d'adoption. Un constat d'exécution compagnon porte le rang de l'acte
     * qu'il accompagne — il existe pour déclarer les conséquences d'un acte
     * signé sans rouvrir celui-ci.
     */
    private function rangDeclarant(string $relatif): int
    {
        if (!str_starts_with($relatif, 'genesis-ii/registre/')) {
            return 0;
        }

        return preg_match('/^ADOPTION-(\d{4})-.+\.md$/', basename($relatif), $m) ? (int) $m[1] : 0;
    }

    /**
     * Texte adopté que décrit une feuille de statut : le fichier du même
     * répertoire partageant son radical.
     *
     * Une correspondance unique est exigée. Le radical ne suffit pas toujours
     * à désigner un seul fichier ; en cas d'ambiguïté la déclaration demeure
     * non contrôlée, ce qui vaut mieux que de la rapporter au mauvais texte.
     */
    private function texteCompagnon(string $relatifStatut): ?string
    {
        if (!preg_match('/^(.+?)-STATUT\.md$/', basename($relatifStatut), $m)) {
            return null;
        }
        $repertoire = dirname($relatifStatut);
        $candidats = [];
        foreach (glob($this->corpus . '/' . $repertoire . '/' . $m[1] . '*.md') ?: [] as $c) {
            if (!str_ends_with($c, '-STATUT.md')) {
                $candidats[] = $repertoire . '/' . basename($c);
            }
        }

        return count($candidats) === 1 ? $candidats[0] : null;
    }

    /**
     * Tous les fichiers `.md` du corpus, en chemins relatifs triés.
     *
     * @return list<string>
     */
    private function fichiersDuCorpus(): array
    {
        $racine = $this->corpus . '/genesis-ii';
        if (!is_dir($racine)) {
            return [];
        }

        $fichiers = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'md') {
                $fichiers[] = substr($f->getPathname(), strlen($this->corpus) + 1);
            }
        }
        sort($fichiers);

        return $fichiers;
    }

    /**
     * Vérifie un objet déclaré, ou tous les objets déclarés.
     *
     * `concorde` vaut `null` lorsque rien n'est déclaré pour l'objet : rien n'a
     * été comparé, et rendre `false` affirmerait une discordance jamais
     * constatée. `couverture` compte les algorithmes portés par la DÉCLARATION,
     * non par le calcul — c'est la mesure d'écart de INV-32.
     *
     * @return list<array<string,mixed>>
     */
    public function verifier(?string $objet = null): array
    {
        $declarations = $this->declarations();
        if ($objet !== null) {
            $declarations = isset($declarations[$objet])
                ? [$objet => $declarations[$objet]]
                : [];
        }

        $lignes = [];
        foreach ($declarations as $chemin => $d) {
            $fichier = $this->corpus . '/' . $chemin;
            $present = is_file($fichier);
            $algorithme = Empreinte::algorithmeProbable($d['valeur']) ?? Empreinte::ALGORITHME_HISTORIQUE;

            $calculees = [];
            if ($present) {
                foreach ($this->algorithmesActifs() as $a) {
                    $calculees[] = ['algorithme' => $a, 'valeur' => Empreinte::calculerFichier($a, $fichier)];
                }
            }

            $reelle = null;
            foreach ($calculees as $c) {
                if ($c['algorithme'] === $algorithme) {
                    $reelle = $c['valeur'];
                }
            }
            $concorde = $present && $reelle !== null ? $reelle === $d['valeur'] : null;

            $lignes[] = [
                'objet'     => $chemin,
                'declaree'  => [
                    'algorithme' => $algorithme,
                    'valeur'     => $d['valeur'],
                    'declarant'  => $d['declarant'],
                    'rang'       => $d['rang'],
                    'origine'    => 'DÉCLARÉE',
                ],
                'calculee'   => $calculees,
                'present'    => $present,
                'concorde'   => $concorde,
                'couverture' => 1, // une seule empreinte déclarée par objet à ce jour
                'verdict'    => match (true) {
                    !$present         => 'FICHIER ABSENT',
                    $concorde === true  => 'CONCORDE',
                    default             => 'DISCORDANCE',
                },
            ];
        }

        return $lignes;
    }

    /**
     * Atteste l'intégrité d'un objet : verdict explicable, rattaché à sa preuve
     * et à l'acte qui la déclare.
     *
     * L'attestation porte sa propre limite dans son corps (INV-34). Une
     * attestation qui tairait qu'elle n'est pas signée serait plus dangereuse
     * qu'aucune attestation, car elle serait citée comme preuve d'origine.
     *
     * @return array<string,mixed>|null `null` si l'objet n'est pas déclaré
     */
    public function attester(string $objet): ?array
    {
        $lignes = $this->verifier($objet);
        if ($lignes === []) {
            return null;
        }
        $l = $lignes[0];

        return [
            'objet'   => $l['objet'],
            'verdict' => $l['verdict'],
            'preuve'  => [
                'declaree' => $l['declaree'],
                'calculee' => $l['calculee'],
            ],
            'moment'  => gmdate('c'),
            'signee'  => false,
            'portee'  => "Cette attestation constate une concordance calculée localement à l'instant indiqué. "
                . "Elle n'est ni signée ni horodatée par un tiers : elle ne prouve ni son origine, ni son moment. "
                . "Elle ne vaut pas preuve d'origine (INV-34).",
        ];
    }

    /**
     * Inventaire des preuves racines (Article 18 de la conception ; preuve G0
     * attendue par l'Article 50 du registre des capacités).
     *
     * L'inventaire ne comble aucun écart : il le chiffre et le nomme. Combler
     * est un acte de l'autorité, non une opération du service.
     *
     * @return array<string,mixed>
     */
    public function inventaire(): array
    {
        $declarations = $this->declarations();
        $verifications = $this->verifier();

        $concordent = 0;
        $discordent = 0;
        $absents    = 0;
        foreach ($verifications as $v) {
            match ($v['verdict']) {
                'CONCORDE'       => $concordent++,
                'DISCORDANCE'    => $discordent++,
                default          => $absents++,
            };
        }

        $tous = $this->fichiersDuCorpus();

        $sansPreuve = array_values(array_filter($tous, fn (string $c) => !isset($declarations[$c])));
        $actesSansPreuve = array_values(array_filter(
            $sansPreuve,
            fn (string $c) => str_starts_with($c, 'genesis-ii/registre/ADOPTION-'),
        ));

        // INV-32 : combien d'objets portent une DÉCLARATION en double algorithme.
        $doubleConservation = 0;
        foreach ($verifications as $v) {
            if ($v['couverture'] >= 2) {
                $doubleConservation++;
            }
        }

        return [
            'objets_du_corpus'      => count($tous),
            'objets_declares'       => count($declarations),
            'objets_sans_preuve'    => count($sansPreuve),
            'actes_sans_preuve'     => count($actesSansPreuve),
            'concordent'            => $concordent,
            'discordent'            => $discordent,
            'fichiers_absents'      => $absents,
            'double_conservation'   => $doubleConservation,
            'algorithmes'           => $this->politique(),
            'liste_sans_preuve'     => $sansPreuve,
            'portee'                => "Inventaire dérivé, jamais autoritatif (INV-5). Il chiffre un écart ; il ne le comble pas.",
        ];
    }
}
