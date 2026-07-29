<?php

declare(strict_types=1);

namespace Gamad\RegistreOrganisations;

/**
 * Les opérations du contrat CTR-17 — Référence d'organisation
 * (CAP-CORE-002, conception adoptée par ADOPTION-0053).
 *
 * La famille `CTR-17` est créée par le Titre XV de `CORE-ATLAS-0001` et
 * rattachée à `CAP-CORE-002`. L'Article 37 énonçait des contrats attendus en
 * prose — « résolution d'organisation, statut, représentation et événements
 * de cycle de vie » — sans qu'aucune famille de l'Article 69 ne les porte.
 * `CAP-CORE-002` était ainsi la seule capacité sans famille de contrat.
 *
 * Lecture et attestation seulement (INV-4). Le service ne reconnaît aucune
 * organisation : il restitue celles que le Registre inscrit.
 *
 * Invariants portés :
 *   INV-55 l'absence d'une source canonique est constatée, jamais suppléée ·
 *   INV-56 être nommée par un texte ne vaut pas reconnaissance : seule
 *          l'inscription reconnaît.
 */
final class Ctr17
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-002';

    private const REGISTRE = 'genesis-ii/registres/organisations/REGISTRE-INITIAL-ORGANISATIONS-0001.md';

    /** Vocabulaire des types — Article 5 du Registre. */
    public const TYPES = ['SOUVERAINE', 'PARTENAIRE', 'OPÉRATRICE'];

    /** Vocabulaire des statuts — Article 6 du Registre. */
    public const STATUTS = ['RECONNUE', 'NON ENTÉRINÉE', 'SUSPENDUE', 'CLÔTURÉE'];

    /** Champs que l'Article 37 exige et que le corpus n'établit pas. */
    public const CHAMPS_DECLARABLES = ['representants', 'liens_produits_realms', 'dates_validite', 'autorite_admission'];

    public const NON_ETABLI = 'NON ÉTABLI';

    /** @var array<string,array<string,string>>|null */
    private ?array $organisations = null;

    public function __construct(private string $corpus)
    {
    }

    /**
     * Les organisations inscrites, dérivées de la forme de l'Article 7.
     *
     * Le service ne cherche aucune organisation dans la prose. GAMAD, Wasplex
     * et IKOMA sont nommés en toutes lettres par des textes adoptés ; un seul
     * de ces trois noms est une organisation inscrite, et c'est l'inscription
     * qui en décide, non la mention (INV-56).
     *
     * @return array<string,array<string,string>>
     */
    public function organisations(): array
    {
        if ($this->organisations !== null) {
            return $this->organisations;
        }

        $inscrites = [];
        foreach (explode("\n", $this->lire()) as $ligne) {
            if (!preg_match(
                '/\*\*Organisation\s*:\*\*\s*`(ORG-[A-Z0-9]+-\d{3})`\s*—\s*(.+?)\.\s*'
                . '\*\*Type\s*:\*\*\s*`([^`]+)`\.\s*\*\*Statut\s*:\*\*\s*`([^`]+)`\.\s*'
                . '\*\*Source\s*:\*\*\s*`([^`]+)`\./u',
                trim($ligne),
                $m,
            )) {
                continue;
            }
            $inscrites[$m[1]] = [
                'reference' => $m[1],
                'libelle'   => trim($m[2]),
                'type'      => $m[3],
                'statut'    => $m[4],
                'source'    => $m[5],
            ];
        }
        ksort($inscrites);

        return $this->organisations = $inscrites;
    }

    /** @return array<string,string>|null */
    public function resoudreOrganisation(string $reference): ?array
    {
        return $this->organisations()[$reference] ?? null;
    }

    /**
     * Le Registre initial des organisations est-il constitué ?
     *
     * L'Article 37 le déclarait non constitué. Le constat est dérivé du
     * disque, non recopié du texte qui l'énonçait (INV-55).
     */
    public function registreConstitue(): bool
    {
        return is_file($this->corpus . '/' . self::REGISTRE);
    }

    /**
     * Types ou statuts employés hors des vocabulaires arrêtés.
     *
     * @return list<array<string,string>>
     */
    public function horsVocabulaire(): array
    {
        $hors = [];
        foreach ($this->organisations() as $reference => $o) {
            if (!in_array($o['type'], self::TYPES, true)) {
                $hors[] = ['organisation' => $reference, 'champ' => 'type', 'valeur' => $o['type']];
            }
            if (!in_array($o['statut'], self::STATUTS, true)) {
                $hors[] = ['organisation' => $reference, 'champ' => 'statut', 'valeur' => $o['statut']];
            }
        }

        return $hors;
    }

    /**
     * Organisations reconnues, par opposition à celles qu'un acte a suspendues
     * ou closes.
     *
     * @return list<string>
     */
    public function reconnues(): array
    {
        return array_values(array_keys(array_filter(
            $this->organisations(),
            static fn (array $o) => $o['statut'] === 'RECONNUE',
        )));
    }

    /**
     * Les champs que l'Article 37 exige et que le corpus n'établit pas.
     *
     * @return array<string,string>
     */
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
        return [
            'organisations'      => count($this->organisations()),
            'reconnues'          => $this->reconnues(),
            'registre_constitue' => $this->registreConstitue(),
            'hors_vocabulaire'   => $this->horsVocabulaire(),
            'champs_non_etablis' => array_keys($this->champs()),
            'proprietaires_partenaires' => self::NON_ETABLI,
            'portee' => "Registre dérivé, jamais autoritatif. Il ne crée aucune organisation et ne confère aucune personnalité juridique (INV-56).",
        ];
    }

    // ------------------------------------------------------------------ interne

    private function lire(): string
    {
        $fichier = $this->corpus . '/' . self::REGISTRE;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
