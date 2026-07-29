<?php

declare(strict_types=1);

namespace Gamad\RegistreRealms;

/**
 * Les opérations du contrat CTR-08 — Statut produit ou realm, pour la part
 * REALMS (CAP-CORE-012, conception adoptée par ADOPTION-0053).
 *
 * La famille `CTR-08` sert deux capacités et l'Atlas l'énonce dans son
 * intitulé même : le partage est RÉGULIER (INV-40). Ce module sert les
 * realms, celui de `core/registre-produits/` sert les produits, et chacun
 * déclare la capacité qu'il sert (INV-41).
 *
 * Lecture et attestation seulement (INV-4). Le service ne reconnaît aucun
 * realm, n'établit aucune fédération et n'accorde aucune confiance.
 *
 * CE SERVICE CONSTATE PRINCIPALEMENT UNE ABSENCE, et c'est son objet.
 * L'Article 35 de l'Atlas nomme trois sources canoniques pour `DOM-04` — le
 * Registre des organisations, celui des produits, celui des realms. Un seul
 * existe. Un service qui suppléerait les deux autres par celui qui existe
 * ferait dire au corpus ce qu'il n'a pas écrit.
 *
 * Invariants portés :
 *   INV-54 un realm non inscrit n'est pas reconnu ; aucune confiance
 *          n'est implicite ·
 *   INV-55 l'absence d'une source canonique est constatée, jamais suppléée.
 */
final class Ctr08
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-012';

    private const LEXIQUE  = 'genesis-ii/lexique/LEXICON-0001-lexique-canonique-gamad-core.md';
    private const PRODUITS = 'genesis-ii/registres/produits/REGISTRE-INITIAL-PRODUITS-0001.md';

    /**
     * Les trois sources canoniques que l'Article 35 de l'Atlas nomme pour
     * `DOM-04`, avec le chemin auquel le corpus les porterait.
     */
    public const SOURCES_CANONIQUES = [
        'Registre des organisations' => 'genesis-ii/registres/organisations/REGISTRE-INITIAL-ORGANISATIONS-0001.md',
        'Registre des produits'      => 'genesis-ii/registres/produits/REGISTRE-INITIAL-PRODUITS-0001.md',
        'Registre des realms'        => 'genesis-ii/registres/realms/REGISTRE-INITIAL-REALMS-0001.md',
    ];

    public const NON_ETABLI = 'NON ÉTABLI';

    public function __construct(private string $corpus)
    {
    }

    /**
     * Les realms reconnus, dérivés d'une forme déclarative.
     *
     * Aucune n'existe à ce jour : le Registre des realms n'est pas constitué,
     * et l'Article 47 le constate lui-même. Le service restitue donc un
     * ensemble vide, et le déclare.
     *
     * Il ne cherche aucun realm dans la prose des textes adoptés. Un realm
     * trouvé dans une phrase serait un realm reconnu par l'agent.
     *
     * @return array<string,array<string,string>>
     */
    public function realms(): array
    {
        $fichier = $this->corpus . '/' . self::SOURCES_CANONIQUES['Registre des realms'];
        if (!is_file($fichier)) {
            return [];
        }

        $realms = [];
        foreach (explode("\n", (string) file_get_contents($fichier)) as $ligne) {
            if (!preg_match(
                '/\*\*Realm reconnu\s*:\*\*\s*`(RLM-[A-Z0-9-]+)`\s*—\s*(.+?)\.\s*\*\*Autorité\s*:\*\*\s*(.+?)\.\s*$/u',
                trim($ligne),
                $m,
            )) {
                continue;
            }
            $realms[$m[1]] = [
                'reference' => $m[1],
                'libelle'   => trim($m[2]),
                'autorite'  => trim($m[3]),
            ];
        }
        ksort($realms);

        return $realms;
    }

    /**
     * L'inventaire est-il constitué ?
     *
     * L'Article 47 attend « inventaire initial **ou décision motivée
     * d'absence** ». Ni l'un ni l'autre n'existe : c'est une troisième
     * situation, et la nommer vaut mieux que la ranger dans l'une des deux.
     */
    public function inventaireConstitue(): bool
    {
        return is_file($this->corpus . '/' . self::SOURCES_CANONIQUES['Registre des realms']);
    }

    /**
     * Les sources canoniques de `DOM-04` et leur présence effective (INV-55).
     *
     * @return array<string,array<string,mixed>>
     */
    public function sourcesCanoniques(): array
    {
        $etat = [];
        foreach (self::SOURCES_CANONIQUES as $libelle => $chemin) {
            $etat[$libelle] = [
                'libelle' => $libelle,
                'chemin'  => $chemin,
                'presente' => is_file($this->corpus . '/' . $chemin),
            ];
        }

        return $etat;
    }

    /**
     * Les définitions adoptées que le service restitue sans les reformuler.
     *
     * @return array<string,string>
     */
    public function definitions(): array
    {
        $texte = $this->lire(self::LEXIQUE);

        return [
            'realm'      => $this->entree($texte, 'Realm'),
            'federation' => $this->entree($texte, 'Fédération'),
            'isolation'  => $this->entree($texte, 'Isolation'),
        ];
    }

    /**
     * Entités que le corpus nomme comme extérieures, et qui NE SONT PAS des
     * realms reconnus (INV-54).
     *
     * Wasplex et IKOMA sont inscrits au Registre des produits comme partenaires
     * externes dont l'appartenance n'est pas entérinée. Les tenir pour des
     * realms fédérés serait exactement la « confiance implicite » que
     * l'Article 47 range en tête de ses risques.
     *
     * @return list<array<string,string>>
     */
    public function externesNonRealms(): array
    {
        $externes = [];
        foreach (explode("\n", $this->lire(self::PRODUITS)) as $ligne) {
            $ligne = trim($ligne);
            if (!str_starts_with($ligne, '|')) {
                continue;
            }
            $c = array_map('trim', explode('|', trim($ligne, '|')));
            if (count($c) !== 4 || !preg_match('/^`(PRD-[A-Z]+-\d{3})`$/', $c[0], $m)) {
                continue;
            }
            $etat = trim(str_replace(['`', '**'], '', $c[3]));
            if (!str_contains($etat, 'PARTENAIRE EXTERNE')) {
                continue;
            }
            $externes[] = [
                'reference' => $m[1],
                'libelle'   => $c[1],
                'etat'      => $etat,
                'realm'     => 'non — aucun realm reconnu',
            ];
        }

        return $externes;
    }

    /** @return array<string,mixed> */
    public function ecarts(): array
    {
        $sources = $this->sourcesCanoniques();
        $absentes = array_values(array_keys(array_filter(
            $sources,
            static fn (array $s) => $s['presente'] === false,
        )));

        return [
            'realms_reconnus'      => count($this->realms()),
            'inventaire_constitue' => $this->inventaireConstitue(),
            'sources_canoniques'   => count($sources),
            'sources_absentes'     => $absentes,
            'externes_non_realms'  => $this->externesNonRealms(),
            'contrat_federation'   => self::NON_ETABLI,
            'procedure_retrait'    => self::NON_ETABLI,
            'niveaux_confiance'    => self::NON_ETABLI,
            'portee' => "Aucun realm n'est reconnu. Le service ne fédère rien et n'accorde aucune confiance (INV-54).",
        ];
    }

    // ------------------------------------------------------------------ interne

    private function entree(string $texte, string $terme): string
    {
        if (!preg_match(
            '/^## Entrée \d+ — ' . preg_quote($terme, '/') . '\s*$\n\n(.+?)$/mu',
            $texte,
            $m,
        )) {
            return self::NON_ETABLI;
        }

        return trim($m[1]);
    }

    private function lire(string $chemin): string
    {
        $fichier = $this->corpus . '/' . $chemin;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
