<?php

declare(strict_types=1);

namespace Gamad\RegistreProduits;

/**
 * Les opérations du contrat CTR-08 — Statut produit ou realm, pour la part
 * PRODUITS (CAP-CORE-011, conception adoptée par ADOPTION-0053).
 *
 * La famille `CTR-08` sert deux capacités — les produits et les realms — et
 * l'Atlas l'énonce dans son intitulé même. Le partage est RÉGULIER (INV-40) ;
 * chaque capacité a son module, et chaque module déclare la capacité qu'il
 * sert (INV-41). Le numéro de famille ne suffit pas à les distinguer.
 *
 * Lecture et attestation seulement : aucune écriture applicative du corpus
 * (INV-4). Le registre dérive du Registre initial des produits ; il n'admet,
 * ne qualifie et ne certifie aucun produit.
 *
 * Ce que le service N'INVENTE PAS :
 *   · l'admission d'un produit dont le dossier est à constituer (INV-52) ;
 *   · une conformité pour un produit non évalué (INV-52) ;
 *   · la traduction d'un état hors vocabulaire vers le terme voisin (INV-53) ;
 *   · un propriétaire institutionnel que nul texte ne désigne.
 *
 * Invariants portés :
 *   INV-52 admission et conformité ne se présument jamais ·
 *   INV-53 l'état courant procède du dernier Titre, et un état hors du
 *          vocabulaire adopté est nommé tel, jamais traduit.
 */
final class Ctr08
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-011';

    private const REGISTRE = 'genesis-ii/registres/produits/REGISTRE-INITIAL-PRODUITS-0001.md';

    /** Vocabulaire des états du portefeuille — Article 22. */
    public const ETATS_PORTEFEUILLE = [
        'IDENTIFIÉ', 'HISTORIQUE À QUALIFIER', 'PROPOSÉ', 'ADMIS', 'PILOTE',
        'ACTIF', 'LIMITÉ', 'SUSPENDU', 'DÉPRÉCIÉ', 'RETIRÉ', 'ARCHIVÉ',
    ];

    /** Vocabulaire des états d'admission — Article 24. */
    public const ETATS_ADMISSION = [
        'NON DEMANDÉE', 'DOSSIER À CONSTITUER', 'EN REVUE',
        'ADMISE SOUS CONDITIONS', 'ADMISE', 'REFUSÉE', 'RETIRÉE',
    ];

    /** Vocabulaire des états de conformité — Article 25. */
    public const ETATS_CONFORMITE = [
        'NON ÉVALUÉ', 'EN ÉVALUATION', 'CONFORME', 'CONFORME SOUS RÉSERVES',
        'LIMITÉ', 'SUSPENDU', 'NON CONFORME', 'RETIRÉ',
    ];

    /** Les seuls états d'admission et de conformité qui autorisent une prétention. */
    public const ADMISSION_ACQUISE = ['ADMISE', 'ADMISE SOUS CONDITIONS'];
    public const CONFORMITE_ACQUISE = ['CONFORME', 'CONFORME SOUS RÉSERVES'];

    public const NON_DESIGNE = 'NON DÉSIGNÉ';

    /** @var array<string,array<string,mixed>>|null */
    private ?array $produits = null;

    public function __construct(private string $corpus)
    {
    }

    /**
     * Le portefeuille, dérivé du tableau initial de l'Article 43 puis mis à
     * jour par le dernier Titre qui a constaté un état.
     *
     * L'état initial n'est pas effacé : il demeure lisible à côté de l'état
     * courant. Un registre qui perdrait l'état antérieur perdrait la trace de
     * la décision qui l'a changé.
     *
     * @return array<string,array<string,mixed>>
     */
    public function portefeuille(): array
    {
        if ($this->produits !== null) {
            return $this->produits;
        }

        $texte = $this->lire();
        $produits = [];

        // 1. Tableau initial — Article 43 : huit colonnes.
        foreach ($this->lignes($texte) as $c) {
            if (count($c) < 8 || !preg_match('/^`(PRD-[A-Z]+-\d{3})`$/', $c[0], $m)) {
                continue;
            }
            $produits[$m[1]] = [
                'reference'    => $m[1],
                'libelle'      => $c[1],
                'etat_initial' => $this->net($c[2]),
                'etat'         => $this->net($c[2]),
                'classe'       => $this->net($c[3]),
                'admission'    => $this->net($c[4]),
                'conformite'   => $this->net($c[5]),
                'proprietaire' => $this->net($c[6]),
                'preuve'       => $this->net($c[7]),
                'constate_par' => 'Article 43',
            ];
        }

        // 2. Titres postérieurs — tableau de lecture combinée à quatre colonnes,
        //    dont la dernière porte l'état constaté. Le dernier prévaut.
        foreach ($this->lignes($texte) as $c) {
            if (count($c) !== 4 || !preg_match('/^`(PRD-[A-Z]+-\d{3})`$/', $c[0], $m)) {
                continue;
            }
            if (!isset($produits[$m[1]])) {
                continue;
            }
            $produits[$m[1]]['etat'] = $this->net($c[3]);
            $produits[$m[1]]['constate_par'] = 'Titre postérieur';
        }

        ksort($produits);

        return $this->produits = $produits;
    }

    /** @return array<string,mixed>|null */
    public function resoudreProduit(string $reference): ?array
    {
        return $this->portefeuille()[$reference] ?? null;
    }

    /**
     * Produits dont l'admission n'est PAS acquise (INV-52).
     *
     * Aucun des quatre produits historiques n'est admis : leur dossier est à
     * constituer. Le fait est constaté, et il est la raison pour laquelle
     * aucun produit ne peut être présenté comme conforme au Core.
     *
     * @return list<string>
     */
    public function nonAdmis(): array
    {
        return array_values(array_keys(array_filter(
            $this->portefeuille(),
            fn (array $p) => !in_array($p['admission'], self::ADMISSION_ACQUISE, true),
        )));
    }

    /**
     * Produits prétendant une conformité sans admission acquise (INV-52).
     *
     * C'est le risque que l'Article 46 nomme en premier : « produit non admis
     * présenté comme GAMAD ». Un produit non admis dont la conformité serait
     * acquise serait une prétention que nul dossier ne fonde.
     *
     * @return list<array<string,string>>
     */
    public function pretentionsSansDossier(): array
    {
        $pretentions = [];
        foreach ($this->portefeuille() as $reference => $p) {
            if (in_array($p['admission'], self::ADMISSION_ACQUISE, true)) {
                continue;
            }
            if (in_array($p['conformite'], self::CONFORMITE_ACQUISE, true)) {
                $pretentions[] = [
                    'produit'    => $reference,
                    'admission'  => (string) $p['admission'],
                    'conformite' => (string) $p['conformite'],
                ];
            }
        }

        return $pretentions;
    }

    /**
     * États employés hors des vocabulaires adoptés (INV-53).
     *
     * Les quatre produits portent aujourd'hui un état courant qu'aucun des
     * onze états de l'Article 22 ne nomme — « DISSOUS — IDENTITÉ RENDUE AU
     * CORE », « PRODUIT OFFICIEL RECONNU », « PARTENAIRE EXTERNE ». Ces états
     * procèdent d'un Titre adopté ; ils sont donc réguliers et hors
     * vocabulaire à la fois.
     *
     * Le service les NOMME. Les rapprocher d'`ADMIS` ou de `RETIRÉ` ferait
     * dire au corpus ce qu'il n'a pas écrit.
     *
     * @return array<string,list<string>> état employé => produits
     */
    public function etatsHorsVocabulaire(): array
    {
        $hors = [];
        foreach ($this->portefeuille() as $reference => $p) {
            $etat = (string) $p['etat'];
            if ($etat === '' || in_array($etat, self::ETATS_PORTEFEUILLE, true)) {
                continue;
            }
            $hors[$etat][] = $reference;
        }
        ksort($hors);

        return $hors;
    }

    /**
     * Produits dont le propriétaire institutionnel n'est pas désigné.
     *
     * @return list<string>
     */
    public function sansProprietaire(): array
    {
        return array_values(array_keys(array_filter(
            $this->portefeuille(),
            fn (array $p) => $p['proprietaire'] === self::NON_DESIGNE || $p['proprietaire'] === '',
        )));
    }

    /**
     * Produits dont un Titre postérieur a changé l'état, l'état initial
     * demeurant lisible.
     *
     * @return list<array<string,string>>
     */
    public function etatsChanges(): array
    {
        $changes = [];
        foreach ($this->portefeuille() as $reference => $p) {
            if ($p['etat'] !== $p['etat_initial']) {
                $changes[] = [
                    'produit' => $reference,
                    'initial' => (string) $p['etat_initial'],
                    'courant' => (string) $p['etat'],
                ];
            }
        }

        return $changes;
    }

    /** @return array<string,mixed> */
    public function ecarts(): array
    {
        return [
            'produits'          => count($this->portefeuille()),
            'non_admis'         => $this->nonAdmis(),
            'pretentions_sans_dossier' => $this->pretentionsSansDossier(),
            'sans_proprietaire' => $this->sansProprietaire(),
            'etats_changes'     => $this->etatsChanges(),
            'etats_hors_vocabulaire' => $this->etatsHorsVocabulaire(),
            'produits_certifies' => 0,
            'portee' => "Portefeuille dérivé du Registre initial des produits. Le service n'admet, ne qualifie et ne certifie aucun produit.",
        ];
    }

    // ------------------------------------------------------------------ interne

    /** @return list<list<string>> */
    private function lignes(string $texte): array
    {
        $lignes = [];
        foreach (explode("\n", $texte) as $ligne) {
            $ligne = trim($ligne);
            if (!str_starts_with($ligne, '|')) {
                continue;
            }
            $lignes[] = array_map('trim', explode('|', trim($ligne, '|')));
        }

        return $lignes;
    }

    private function net(string $valeur): string
    {
        return trim(str_replace(['`', '**'], '', $valeur));
    }

    private function lire(): string
    {
        $fichier = $this->corpus . '/' . self::REGISTRE;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
