<?php

declare(strict_types=1);

namespace Gamad\RegistreContinuite;

/**
 * Les opérations du contrat CTR-18 — Preuve de sauvegarde et restauration
 * (CAP-CORE-019, conception adoptée par ADOPTION-0055).
 *
 * La famille `CTR-18` est créée par le Titre XVI de `CORE-ATLAS-0001` et
 * rattachée à `CAP-CORE-019`. L'Article 54 énonçait ses contrats attendus en
 * prose — « preuve de sauvegarde, demande de restauration, résultat,
 * réconciliation et attestation de succession » — sans qu'aucune famille de
 * l'Article 69 ne les porte. C'était, après `CAP-CORE-002`, la seconde et
 * dernière capacité dépourvue de famille de contrat.
 *
 * Lecture et attestation seulement (INV-4).
 *
 * CE SERVICE S'ARRÊTE DEVANT UNE FRONTIÈRE, ET LE DÉCLARE.
 * L'Article 4 du Registre initial des sauvegardes réserve expressément
 * l'inventaire et le test des sauvegardes techniques réelles à l'autorité de
 * proposition. Le service ne franchit pas cette exclusion : il restitue ce que
 * le corpus déclare, et nomme ce qu'il ne peut pas voir (INV-61).
 *
 * Invariants portés :
 *   INV-60 une redondance de fait n'est pas une sauvegarde éprouvée ·
 *   INV-61 le service ne franchit pas une exclusion de mission : ce que
 *          l'autorité s'est réservé n'est pas inventorié.
 */
final class Ctr18
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-019';

    private const REGISTRE  = 'genesis-ii/registres/securite/REGISTRE-INITIAL-SAUVEGARDES-RESTAURATIONS-0001.md';
    private const EXERCICES = 'genesis-ii/registres/securite/REGISTRE-CONTINUITE-EXERCICES-0001.md';

    /**
     * Ce que l'Article 54 attend comme preuves `G0`, et que le corpus
     * n'établit pas. L'Article 74 le constate comme écart global de
     * continuité.
     */
    public const CHAMPS_DECLARABLES = [
        'objectifs_de_reprise',
        'mode_degrade',
        'plan_de_succession',
        'strategie_sortie_fournisseur',
        'retention',
        'emplacements',
    ];

    public const NON_ETABLI = 'NON ÉTABLI';

    public function __construct(private string $corpus)
    {
    }

    /**
     * La redondance de fait que le corpus constate, et ce qu'elle N'EST PAS.
     *
     * Le dépôt existe sur `origin` et sur au moins un clone local. Le Registre
     * l'énonce lui-même comme « redondance de fait, non un plan de sauvegarde
     * testé au sens de l'Article 211 ».
     *
     * La Loi 44 de `CORE-LAWS-0001` est explicite : une sauvegarde n'est
     * réputée fiable qu'après vérification d'intégrité et tests périodiques de
     * restauration. Deux copies non éprouvées ne sont pas une sauvegarde ;
     * elles sont deux copies (INV-60).
     *
     * @return array<string,mixed>
     */
    public function redondanceDeFait(): array
    {
        $texte = $this->lire(self::REGISTRE);

        $constat = null;
        if (preg_match('/^## Article \d+ — Sauvegarde de fait constatable\s*$\n\n(.+?)$/mu', $texte, $m)) {
            $constat = trim($m[1]);
        }

        return [
            'constatee'          => $constat !== null,
            'constat'            => $constat,
            'plan_teste'         => false,
            'qualification'      => $constat === null
                ? self::NON_ETABLI
                : 'redondance de fait — non un plan de sauvegarde testé (Loi 44)',
        ];
    }

    /**
     * L'exclusion de mission déclarée par le Registre (INV-61).
     *
     * Le service pourrait techniquement énumérer des artefacts, des dépôts,
     * des emplacements. Il ne le fait pas : l'Article 4 du Registre réserve
     * cet inventaire à l'autorité, et `ADOPTION-0025`, Art. 3.a range les
     * accès et les secrets dans son domaine exclusif.
     *
     * Un service qui franchirait cette frontière « pour être utile » rendrait
     * le corpus faux sur le point même où il se veut le plus strict.
     *
     * @return array<string,mixed>
     */
    public function exclusionDeMission(): array
    {
        $texte = $this->lire(self::REGISTRE);

        $motif = null;
        if (preg_match('/^## Article \d+ — Exclusion explicite de mission\s*$\n\n(.+?)$/mu', $texte, $m)) {
            $motif = trim($m[1]);
        }

        return [
            'declaree'  => $motif !== null,
            'motif'     => $motif,
            'inventaire_technique' => 'NON INVENTORIÉ — réservé à l\'autorité',
            'source'    => 'REGISTRE-INITIAL-SAUVEGARDES-RESTAURATIONS-0001, Article 4 ; ADOPTION-0025, Art. 3.a',
        ];
    }

    /**
     * Les tests de restauration inscrits, dérivés d'une forme déclarative.
     *
     * Aucun n'est inscrit. L'Article 54 attend « au moins un exercice de
     * restauration pour les preuves et sources racines » parmi ses preuves
     * `G0` : cette attente n'est pas satisfaite, et le service le constate
     * sans la déclarer satisfaite par la redondance de fait.
     *
     * @return list<array<string,string>>
     */
    public function testsDeRestauration(): array
    {
        $tests = [];
        foreach ([self::REGISTRE, self::EXERCICES] as $chemin) {
            foreach (explode("\n", $this->lire($chemin)) as $ligne) {
                if (!preg_match(
                    '/\*\*Test de restauration\s*:\*\*\s*`(EXE-[A-Z]+-\d{4})`\s*—\s*(.+?)\.\s*\*\*Résultat\s*:\*\*\s*(.+?)\.\s*$/u',
                    trim($ligne),
                    $m,
                )) {
                    continue;
                }
                $tests[] = ['reference' => $m[1], 'objet' => trim($m[2]), 'resultat' => trim($m[3])];
            }
        }

        return $tests;
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
        $redondance = $this->redondanceDeFait();
        $exclusion  = $this->exclusionDeMission();

        return [
            'redondance_de_fait'   => $redondance['constatee'],
            'plan_de_sauvegarde_teste' => $redondance['plan_teste'],
            'tests_de_restauration' => count($this->testsDeRestauration()),
            'exclusion_de_mission' => $exclusion['declaree'],
            'inventaire_technique' => $exclusion['inventaire_technique'],
            'champs_non_etablis'   => array_keys($this->champs()),
            'ecart_global_continuite' => 'Article 74 — objectifs de reprise, modes dégradés, tests de restauration, comptes institutionnels et plans de succession non établis',
            'portee' => "Le service restitue ce que le corpus déclare et s'arrête à l'exclusion de mission de l'Article 4 (INV-61).",
        ];
    }

    // ------------------------------------------------------------------ interne

    private function lire(string $chemin): string
    {
        $fichier = $this->corpus . '/' . $chemin;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
