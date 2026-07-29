<?php

declare(strict_types=1);

namespace Gamad\RegistreContrats;

use Gamad\RegistreAnnuaire\Ctr14;

/**
 * Les opérations du contrat CTR-06 — Catalogue de contrats
 * (CAP-CORE-009, conception adoptée par ADOPTION-0047).
 *
 * Lecture et attestation seulement : aucune écriture applicative du corpus
 * (INV-4). Le catalogue dérive, il ne crée aucun contrat (INV-42).
 *
 * CE SERVICE CONSOMME CTR-14 POUR LE RELEVÉ DES FAMILLES, et le déclare.
 * L'Atlas est la source ; l'annuaire en porte déjà l'analyseur, éprouvé et
 * gardé. Dupliquer cet analyseur donnerait au corpus deux vérités sur ses
 * propres contrats, qui divergeraient au premier ajout de famille.
 *
 * Ce que le service N'INVENTE PAS :
 *   · un producteur pour une famille qu'aucun module ne sert (INV-43) ;
 *   · un titulaire pour une famille qu'aucune capacité ne revendique (INV-43) ;
 *   · une version, une politique de compatibilité, une stratégie d'erreur ou
 *     une procédure de sortie que le corpus n'établit pas (INV-45).
 *
 * Invariants portés :
 *   INV-42 le catalogue dérive, il ne crée rien ·
 *   INV-43 un contrat sans producteur est déclaré tel ·
 *   INV-44 une dépendance est observée dans le code, jamais déduite ·
 *   INV-45 version et compatibilité ne sont pas inventées.
 */
final class Ctr06
{
    /**
     * La capacité souveraine que ce module sert (INV-41).
     *
     * Une famille de contrat peut servir deux capacités — `CTR-10` sert
     * l'audit et l'intégrité. Le numéro de famille ne suffit donc pas à
     * rattacher un module ; le module le déclare lui-même.
     */
    public const CAPACITE = 'CAP-CORE-009';

    /**
     * Le registre initial des contrats, attendu parmi les preuves G0 de
     * l'Article 44. Il n'est pas encore adopté ; tant qu'il ne l'est pas, les
     * champs qu'il établirait demeurent NON ÉTABLI (INV-45).
     */
    private const REGISTRE_CONTRATS = 'genesis-ii/registres/contrats/REGISTRE-INITIAL-CONTRATS-0001.md';

    /** Champs que seul le registre initial des contrats pourrait établir. */
    public const CHAMPS_DECLARABLES = ['version', 'compatibilite', 'strategie_erreur', 'procedure_sortie'];

    public const NON_ETABLI = 'NON ÉTABLI';

    /** @var list<array<string,string>>|null */
    private ?array $dependances = null;

    public function __construct(
        private string $corpus,
        private Ctr14 $annuaire,
    ) {
    }

    /**
     * Le catalogue des familles de contrat définies par l'Atlas, chacune avec
     * son domaine gardien, ses titulaires déclarés, son producteur observé et
     * les champs que le corpus n'établit pas.
     *
     * @return array<string,array<string,mixed>>
     */
    public function catalogue(): array
    {
        $familles = $this->annuaire->familles();
        $attributions = $this->annuaire->attributions();
        $modules = $this->annuaire->modules();

        $catalogue = [];
        foreach ($familles as $reference => $famille) {
            $producteur = null;
            foreach ($modules as $m) {
                if ($m['famille'] === $reference) {
                    $producteur = [
                        'module'   => $m['module'],
                        'classe'   => $m['classe'],
                        'capacite' => $m['capacite'],
                    ];
                    break;
                }
            }

            $catalogue[$reference] = [
                'reference'   => $reference,
                'libelle'     => $famille['libelle'],
                'gardien'     => $famille['gardien'],
                'objet'       => $famille['objet'],
                'titulaires'  => $attributions[$reference] ?? [],
                'producteur'  => $producteur,
                'consommateurs' => $this->consommateursDe($reference),
                'champs'      => $this->champs(),
            ];
        }

        return $catalogue;
    }

    /**
     * La fiche d'une famille, ou null si l'Atlas ne la définit pas.
     *
     * @return array<string,mixed>|null
     */
    public function resoudreContrat(string $reference): ?array
    {
        return $this->catalogue()[$reference] ?? null;
    }

    /**
     * Pour chaque famille, le module qui la sert et la capacité qu'il DÉCLARE
     * servir (Article 9 de la conception ; INV-41).
     *
     * Une famille sans producteur figure au relevé avec la valeur `null` : la
     * taire donnerait à croire que toutes les familles sont servies.
     *
     * @return array<string,array<string,string>|null>
     */
    public function producteurs(): array
    {
        return array_map(
            static fn (array $c) => $c['producteur'],
            $this->catalogue(),
        );
    }

    /**
     * Pour chaque famille, les contrats qui l'utilisent EFFECTIVEMENT dans le
     * code (Article 9 de la conception ; INV-44).
     *
     * @return array<string,list<string>>
     */
    public function consommateurs(): array
    {
        $releve = [];
        foreach (array_keys($this->catalogue()) as $reference) {
            $releve[$reference] = $this->consommateursDe($reference);
        }

        return $releve;
    }

    /**
     * Familles définies par l'Atlas qu'aucun module ne sert (INV-43).
     *
     * Une famille sans producteur n'est pas un défaut : c'est un travail non
     * commencé, et le dire est plus utile que de le taire.
     *
     * @return list<string>
     */
    public function sansProducteur(): array
    {
        return array_values(array_keys(array_filter(
            $this->catalogue(),
            static fn (array $c) => $c['producteur'] === null,
        )));
    }

    /**
     * Familles définies par l'Atlas qu'aucune capacité ne revendique (INV-43).
     *
     * `CTR-09` — Données et droits est dans ce cas depuis ADOPTION-0045 :
     * aucune des vingt capacités ne garde `DOM-07`. Le fait est régulier et
     * l'écart global de données de l'Article 70 le prévoyait.
     *
     * @return list<string>
     */
    public function sansTitulaire(): array
    {
        return array_values(array_keys(array_filter(
            $this->catalogue(),
            static fn (array $c) => $c['titulaires'] === [],
        )));
    }

    /**
     * Familles sans titulaire ALORS QU'UNE CAPACITÉ GARDE LEUR DOMAINE.
     *
     * Toutes les familles sans titulaire ne se valent pas. `CTR-09`, `CTR-12`
     * et `CTR-13` sont gardées par des domaines qu'aucune des vingt capacités
     * ne tient : leur vacance est structurelle et prévue. Une famille dont le
     * domaine gardien EST tenu par une capacité, et que cette capacité ne
     * revendique pas, relève d'une autre espèce — une attribution que le
     * corpus n'a pas portée dans le champ qui la porte.
     *
     * Le service NOMME la différence ; il n'attribue rien (INV-38, INV-42).
     *
     * @return array<string,list<string>> famille => capacités gardant son domaine
     */
    public function sansTitulaireMalgreGardien(): array
    {
        $releve = [];
        foreach ($this->sansTitulaire() as $reference) {
            $gardiens = $this->codesDomaine($this->annuaire->familles()[$reference]['gardien'] ?? '');
            if ($gardiens === []) {
                continue;
            }
            $candidates = [];
            foreach ($this->annuaire->comparerReel() as $ligne) {
                $fiche = $this->annuaire->resoudreCapacite($ligne['capacite']);
                if ($fiche === null) {
                    continue;
                }
                if (array_intersect($gardiens, $this->codesDomaine((string) $fiche['domaine'])) !== []) {
                    $candidates[] = $ligne['capacite'];
                }
            }
            if ($candidates !== []) {
                $releve[$reference] = $candidates;
            }
        }

        return $releve;
    }

    /**
     * Dépendances entre contrats, RELEVÉES DANS LE CODE (INV-44).
     *
     * Un module qui importe la classe de contrat d'un autre module en dépend,
     * que le corpus le déclare ou non. C'est le « contrat implicite fondé sur
     * le comportement accidentel d'une implémentation » que l'Article 37 de
     * l'Atlas exclut des données de `DOM-06`, et que l'Article 44 range parmi
     * les risques de la présente capacité.
     *
     * Le relevé ne consulte aucune déclaration : il lit les imports.
     *
     * @return list<array<string,string>>
     */
    public function dependances(): array
    {
        if ($this->dependances !== null) {
            return $this->dependances;
        }

        // Classe de contrat -> famille, pour traduire un import en dépendance.
        $parClasse = [];
        foreach ($this->annuaire->modules() as $m) {
            $parClasse[(string) $m['classe']] = $m;
        }

        $releve = [];
        foreach ($this->annuaire->modules() as $m) {
            $fichier = $this->corpus . '/core/' . $m['module'] . '/src/' . $m['classe'] . '.php';
            if (!is_file($fichier)) {
                continue;
            }
            $source = (string) file_get_contents($fichier);
            if (!preg_match_all('/^use\s+Gamad\\\\\w+\\\\(Ctr\d{2})\s*;/m', $source, $mu)) {
                continue;
            }
            foreach (array_unique($mu[1]) as $classeImportee) {
                if ($classeImportee === $m['classe'] || !isset($parClasse[$classeImportee])) {
                    continue;
                }
                $releve[] = [
                    'consommateur'         => (string) $m['famille'],
                    'module_consommateur'  => (string) $m['module'],
                    'produit'              => (string) $parClasse[$classeImportee]['famille'],
                    'module_produit'       => (string) $parClasse[$classeImportee]['module'],
                    'declaree'             => $this->dependanceDeclaree(
                        (string) $m['famille'],
                        (string) $parClasse[$classeImportee]['famille'],
                    ) ? 'oui' : 'non',
                ];
            }
        }

        usort($releve, static fn (array $a, array $b) => [$a['consommateur'], $a['produit']] <=> [$b['consommateur'], $b['produit']]);

        return $this->dependances = $releve;
    }

    /**
     * Consommateurs observés d'une famille.
     *
     * @return list<string>
     */
    public function consommateursDe(string $reference): array
    {
        $consommateurs = [];
        foreach ($this->dependances() as $d) {
            if ($d['produit'] === $reference) {
                $consommateurs[] = $d['consommateur'];
            }
        }

        return array_values(array_unique($consommateurs));
    }

    /**
     * Registre des écarts — la synthèse que l'Article 44 attend parmi ses
     * contrôles requis, et le relevé de ce que le corpus n'établit pas.
     *
     * @return array<string,mixed>
     */
    public function ecarts(): array
    {
        $catalogue = $this->catalogue();
        $nonDeclarees = array_values(array_filter(
            $this->dependances(),
            static fn (array $d) => $d['declaree'] === 'non',
        ));

        return [
            'familles'              => count($catalogue),
            'familles_servies'      => count($catalogue) - count($this->sansProducteur()),
            'sans_producteur'       => $this->sansProducteur(),
            'sans_titulaire'        => $this->sansTitulaire(),
            'sans_titulaire_malgre_gardien' => $this->sansTitulaireMalgreGardien(),
            'dependances'           => count($this->dependances()),
            'dependances_non_declarees' => $nonDeclarees,
            'champs_non_etablis'    => array_keys(array_filter(
                $this->champs(),
                static fn (string $v) => $v === self::NON_ETABLI,
            )),
            'registre_initial_adopte' => is_file($this->corpus . '/' . self::REGISTRE_CONTRATS),
            'portee'                => "Catalogue dérivé, jamais autoritatif (INV-42). Il nomme les écarts ; il n'en arbitre aucun.",
        ];
    }

    // ------------------------------------------------------------------ interne

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
     * Les champs qu'un registre initial des contrats établirait.
     *
     * Tant que ce registre n'est pas adopté, les quatre demeurent NON ÉTABLI
     * (INV-45). Le service ne propose ni convention par défaut, ni
     * numérotation implicite, ni règle de compatibilité déduite de l'usage :
     * une version inventée serait une promesse de compatibilité que personne
     * n'a faite.
     *
     * @return array<string,string>
     */
    private function champs(): array
    {
        $etablis = is_file($this->corpus . '/' . self::REGISTRE_CONTRATS);

        $champs = [];
        foreach (self::CHAMPS_DECLARABLES as $champ) {
            $champs[$champ] = $etablis ? '' : self::NON_ETABLI;
        }

        return $champs;
    }

    /**
     * Une dépendance entre deux familles est-elle déclarée par un texte adopté ?
     *
     * En l'absence de registre initial des contrats, aucune ne l'est. La
     * question demeure posée dans le code plutôt que résolue par un « non »
     * codé en dur : le jour où ce registre existera, la réponse changera sans
     * que la structure du service change.
     */
    private function dependanceDeclaree(string $consommateur, string $produit): bool
    {
        $fichier = $this->corpus . '/' . self::REGISTRE_CONTRATS;
        if (!is_file($fichier)) {
            return false;
        }

        $texte = (string) file_get_contents($fichier);

        return (bool) preg_match(
            '/\*\*Dépendance\s*:\*\*\s*`' . preg_quote($consommateur, '/') . '`\s*→\s*`' . preg_quote($produit, '/') . '`/u',
            $texte,
        );
    }
}
