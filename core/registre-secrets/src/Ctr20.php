<?php

declare(strict_types=1);

namespace Gamad\RegistreSecrets;

/**
 * Les opérations du contrat CTR-20 — Gouvernance de secret
 * (CAP-CORE-016, conception adoptée par ADOPTION-0057).
 *
 * La famille `CTR-20` est créée par le Titre XIX de `CORE-ATLAS-0001` et
 * rattachée à `CAP-CORE-016`. Elle résout les MÉTADONNÉES d'un secret —
 * gardien, finalité, rotation, révocation, récupération — et JAMAIS sa valeur.
 * L'exclusion de la valeur n'est pas une limitation de la famille : elle en est
 * la définition. Une famille qui porterait la valeur violerait la Loi 40.
 *
 * Lecture et attestation seulement (INV-4).
 *
 * CE SERVICE EST BORNÉ DEUX FOIS, ET LES DEUX BORNES NE SE VALENT PAS.
 *
 *   · Article 4 du Registre cryptographique — EXCLUSION DE MISSION. L'inventaire
 *     réel des secrets relève exclusivement de l'autorité. Le service ne le
 *     franchit pas (INV-61). Cette borne tomberait si l'autorité renseignait
 *     l'inventaire elle-même.
 *
 *   · Article 3 du même registre — INTERDICTION ABSOLUE. Aucune valeur secrète
 *     ne doit JAMAIS être inscrite dans le registre ni dans aucun fichier du
 *     dépôt (GOVERNANCE-0001 Art. 72 ; CORE-LAWS-0001 Loi 40). Cette borne
 *     survit à la levée de la première : le jour où l'autorité renseignera
 *     l'inventaire, l'Article 3 s'appliquera encore (INV-66).
 *
 * Une exclusion de mission borne ce que le service a le droit de CONNAÎTRE.
 * Une interdiction absolue borne ce que le service a le droit de PRODUIRE.
 *
 * Invariants portés :
 *   INV-61 le service ne franchit pas une exclusion de mission ·
 *   INV-66 une interdiction absolue borne le service, non seulement sa portée.
 */
final class Ctr20
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-016';

    private const CRYPTO = 'genesis-ii/registres/securite/REGISTRE-CRYPTOGRAPHIQUE-INITIAL-0001.md';
    private const ACCES  = 'genesis-ii/registres/securite/REGISTRE-INITIAL-ACCES-PRIVILEGIES-0001.md';

    /**
     * Ce que l'Article 51 range parmi ses décisions ouvertes, et que le corpus
     * n'établit pas. Réservé à l'autorité.
     */
    public const CHAMPS_DECLARABLES = [
        'solutions_de_coffre',
        'detenteurs',
        'seuils',
        'frequence_de_rotation',
        'cles_racines',
    ];

    public const NON_ETABLI     = 'NON ÉTABLI';
    public const NON_INVENTORIE = 'NON INVENTORIÉ — réservé à l\'autorité';

    /**
     * Les formes sous lesquelles une valeur secrète se reconnaît SANS ÊTRE LUE.
     *
     * Chaque motif décrit une FORME, jamais un contenu. Le service compte les
     * occurrences et nomme le motif ; il ne restitue, ne journalise et
     * n'enregistre AUCUNE correspondance (INV-66). Un détecteur qui citerait
     * ce qu'il trouve violerait l'interdiction qu'il atteste.
     */
    private const FORMES_DE_VALEUR = [
        'bloc de clé privée'      => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        'valeur affectée à un secret' => '/\b(?:mot de passe|password|passwd|secret|token|api[_-]?key|clé privée)\b\s*[:=]\s*[^\s`|]{8,}/iu',
        'chaîne de connexion'     => '/\b[a-z][a-z0-9+.-]*:\/\/[^\s:@\/]+:[^\s@\/]+@/i',
        'jeton porteur'           => '/\b(?:Bearer|Basic)\s+[A-Za-z0-9._~+\/-]{20,}={0,2}/',
    ];

    public function __construct(private string $corpus)
    {
    }

    /**
     * Le schéma d'une entrée, tel que l'Article 2 du Registre cryptographique
     * l'énonce. C'est ce que `CTR-20` résout — et la valeur n'y figure pas.
     *
     * @return list<string>
     */
    public function schema(): array
    {
        if (!preg_match(
            '/^## Article \d+ — Schéma d\'une entrée\s*$\n\n(.+?)$/mu',
            $this->lire(self::CRYPTO),
            $m,
        )) {
            return [];
        }

        // Les parenthèses énumèrent les natures d'un même champ — « nature
        // (clé, certificat, secret…) ». Les découper produirait des champs qui
        // n'existent pas : elles sont protégées avant la coupure.
        $ligne = preg_replace_callback(
            '/\(([^)]*)\)/u',
            static fn (array $p): string => '(' . str_replace(',', "\u{2063}", $p[1]) . ')',
            rtrim(trim($m[1]), '.'),
        ) ?? '';

        $champs = preg_split('/,\s*|\s+et\s+/u', $ligne) ?: [];
        $champs = array_map(
            static fn (string $v): string => trim(str_replace("\u{2063}", ',', $v)),
            $champs,
        );

        return array_values(array_filter($champs, static fn (string $v): bool => $v !== ''));
    }

    /**
     * L'interdiction absolue de l'Article 3, relevée du corpus avec ses
     * fondements (INV-66).
     *
     * @return array<string,mixed>
     */
    public function interdictionAbsolue(): array
    {
        $texte = $this->lire(self::CRYPTO);

        $enonce = null;
        if (preg_match('/^## Article \d+ — Interdiction absolue\s*$\n\n(.+?)$/mu', $texte, $m)) {
            $enonce = trim($m[1]);
        }

        $fondements = [];
        foreach (['GOVERNANCE-0001', 'CORE-LAWS-0001', 'Loi 40'] as $fondement) {
            if ($enonce !== null && str_contains($enonce, $fondement)) {
                $fondements[] = $fondement;
            }
        }

        return [
            'declaree'    => $enonce !== null,
            'enonce'      => $enonce,
            'fondements'  => $fondements,
            'portee'      => 'Elle borne ce que le service a le droit de PRODUIRE, et survit à la levée de l\'exclusion de mission (INV-66).',
            'source'      => 'REGISTRE-CRYPTOGRAPHIQUE-INITIAL-0001, Article 3',
        ];
    }

    /**
     * Les exclusions de mission déclarées, pour les secrets et pour les accès
     * privilégiés (INV-61).
     *
     * @return list<array<string,mixed>>
     */
    public function exclusionsDeMission(): array
    {
        $exclusions = [];

        foreach ([self::CRYPTO => 'secrets, clés et certificats', self::ACCES => 'comptes et accès privilégiés'] as $chemin => $objet) {
            $motif = null;
            if (preg_match(
                '/^## Article \d+ — Exclusion explicite de mission\s*$\n\n(.+?)$/mu',
                $this->lire($chemin),
                $m,
            )) {
                $motif = trim($m[1]);
            }

            $exclusions[] = [
                'objet'      => $objet,
                'declaree'   => $motif !== null,
                'motif'      => $motif,
                'inventaire' => self::NON_INVENTORIE,
                'source'     => $chemin,
            ];
        }

        return $exclusions;
    }

    /**
     * L'attestation que l'interdiction absolue est TENUE dans les sources lues.
     *
     * C'est la seule chose que ce service produise de positif, et il la produit
     * sans jamais reproduire ce qu'il cherche : le relevé porte le NOM du motif
     * et le NOMBRE d'occurrences, jamais la correspondance elle-même.
     *
     * Un détecteur qui citerait ce qu'il trouve violerait l'interdiction qu'il
     * atteste. C'est la différence exacte entre `INV-61` et `INV-66` : la
     * première borne le périmètre, la seconde borne la sortie.
     *
     * @return array<string,mixed>
     */
    public function attesterInterdiction(): array
    {
        $releve = [];
        $total  = 0;

        foreach ([self::CRYPTO, self::ACCES] as $chemin) {
            $texte = $this->lire($chemin);
            foreach (self::FORMES_DE_VALEUR as $nom => $motif) {
                $occurrences = preg_match_all($motif, $texte);
                $occurrences = is_int($occurrences) ? $occurrences : 0;
                if ($occurrences > 0) {
                    $releve[] = ['source' => $chemin, 'forme' => $nom, 'occurrences' => $occurrences];
                    $total   += $occurrences;
                }
            }
        }

        return [
            'tenue'          => $total === 0,
            'sources_lues'   => [self::CRYPTO, self::ACCES],
            'formes_cherchees' => array_keys(self::FORMES_DE_VALEUR),
            'occurrences'    => $total,
            'releve'         => $releve,
            'portee'         => 'Le relevé porte le nom du motif et le nombre, jamais la correspondance (INV-66).',
        ];
    }

    /**
     * Les FORMES qu'un échantillon présente, par leur nom seulement.
     *
     * Existe pour que la garde puisse établir que le détecteur n'est pas
     * vacuant : un détecteur qui ne peut rien reconnaître n'atteste rien, et
     * son attestation « interdiction tenue » ne vaudrait rien.
     *
     * La méthode restitue des NOMS de forme, jamais l'échantillon ni la
     * correspondance — la borne de `INV-66` vaut aussi pour ce chemin.
     *
     * @return list<string>
     */
    public function formesDetectees(string $echantillon): array
    {
        $formes = [];
        foreach (self::FORMES_DE_VALEUR as $nom => $motif) {
            if (preg_match($motif, $echantillon) === 1) {
                $formes[] = $nom;
            }
        }

        return $formes;
    }

    /**
     * L'inventaire réel — que ce service ne produit pas, et ne produira pas.
     *
     * Le service pourrait techniquement énumérer des dépôts, des variables
     * d'environnement, des fichiers de configuration. IL NE LE FAIT PAS.
     * `ADOPTION-0025`, Art. 3.a range les accès et les secrets dans le domaine
     * exclusif de l'autorité.
     *
     * @return array<string,mixed>
     */
    public function inventaire(): array
    {
        return [
            'secrets'      => self::NON_INVENTORIE,
            'cles'         => self::NON_INVENTORIE,
            'certificats'  => self::NON_INVENTORIE,
            'coffres'      => self::NON_INVENTORIE,
            'detenteurs'   => self::NON_INVENTORIE,
            'source'       => 'REGISTRE-CRYPTOGRAPHIQUE-INITIAL-0001, Article 4 ; ADOPTION-0025, Art. 3.a',
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
        $interdiction = $this->interdictionAbsolue();
        $exclusions   = $this->exclusionsDeMission();
        $attestation  = $this->attesterInterdiction();

        return [
            'schema_champs'         => count($this->schema()),
            'interdiction_declaree' => $interdiction['declaree'],
            'interdiction_tenue'    => $attestation['tenue'],
            'occurrences_de_valeur' => $attestation['occurrences'],
            'exclusions_declarees'  => count(array_filter($exclusions, static fn (array $e): bool => $e['declaree'] === true)),
            'inventaire'            => self::NON_INVENTORIE,
            'champs_non_etablis'    => array_keys($this->champs()),
            'ecart_global_securite' => 'Article 72 — inventaire, coffres, détenteurs, rotations et récupérations non établis',
            'portee' => 'Deux bornes : l\'exclusion de mission borne ce que le service peut connaître (INV-61) ; l\'interdiction absolue borne ce qu\'il peut produire (INV-66).',
        ];
    }

    // ------------------------------------------------------------------ interne

    private function lire(string $chemin): string
    {
        $fichier = $this->corpus . '/' . $chemin;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
