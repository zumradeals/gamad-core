<?php

declare(strict_types=1);

namespace Gamad\RegistreRisques;

/**
 * Les opérations du contrat CTR-11 — Risque et incident, pour la part
 * RISQUES ET EXCEPTIONS (CAP-CORE-017, conception adoptée par ADOPTION-0055).
 *
 * La famille `CTR-11` sert deux capacités — les risques et les incidents — et
 * l'Atlas l'énonce dans son intitulé même. Le partage est RÉGULIER (INV-40) ;
 * chaque capacité a son module, et chaque module déclare la capacité qu'il
 * sert (INV-41).
 *
 * Lecture et attestation seulement (INV-4). Le service n'évalue aucun risque,
 * n'en accepte aucun et n'en clôt aucun : la Loi 65 de CORE-LAWS-0001 réserve
 * l'acceptation à l'autorité compétente, et l'Article 4 du Registre des
 * risques rappelle qu'elle n'appartient pas à l'IA.
 *
 * Invariants portés :
 *   INV-57 une acceptation sans échéance est nommée telle ; le temps ne
 *          clôt rien ·
 *   INV-58 un niveau proposé par un agent artificiel n'est pas un niveau
 *          arbitré.
 */
final class Ctr11
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-017';

    private const RISQUES    = 'genesis-ii/registres/securite/REGISTRE-INITIAL-RISQUES-CONTROLES-0001.md';
    private const EXCEPTIONS = 'genesis-ii/registres/securite/REGISTRE-INITIAL-EXCEPTIONS-SECURITE-0001.md';

    /** Niveaux de risque — Article 58 de SECURITY-GOVERNANCE-0001. */
    public const NIVEAUX = ['S0', 'S1', 'S2', 'S3', 'S4'];

    public const NON_ETABLI = 'NON ÉTABLI';

    /** Ce qu'une échéance ferme n'est pas. */
    private const SANS_TERME = ['aucun terme fixe', 'sans terme fixe', 'sans terme'];

    /** @var array<string,array<string,mixed>>|null */
    private ?array $risques = null;

    /** @var array<string,array<string,string>>|null */
    private ?array $exceptions = null;

    public function __construct(private string $corpus)
    {
    }

    /**
     * Les risques inscrits, dérivés du tableau de l'Article 5 puis complétés
     * par les arbitrages que des Titres postérieurs ont portés.
     *
     * Le niveau relevé au tableau est **proposé**. Il ne devient arbitré que
     * si un arbitrage le confirme, et cet arbitrage nomme l'acte qui le porte
     * (INV-58).
     *
     * @return array<string,array<string,mixed>>
     */
    public function risques(): array
    {
        if ($this->risques !== null) {
            return $this->risques;
        }

        $texte = $this->lire(self::RISQUES);
        $risques = [];

        foreach (explode("\n", $texte) as $ligne) {
            $ligne = trim($ligne);
            if (!str_starts_with($ligne, '|')) {
                continue;
            }
            $c = array_map('trim', explode('|', trim($ligne, '|')));
            if (count($c) < 4 || !preg_match('/^`(RISK-[A-Z]+-\d{4})`$/', $c[0], $m)) {
                continue;
            }
            $risques[$m[1]] = [
                'reference'     => $m[1],
                'libelle'       => $c[1],
                'niveau_propose' => $this->niveau($c[2]),
                'source'        => $c[3],
                'arbitre_par'   => null,
                'niveau_arbitre' => null,
                'traitement'    => null,
                'exception'     => null,
                'reexamen'      => null,
            ];
        }

        // Arbitrages portés par un Titre postérieur. Le service lit la forme
        // que le Registre emploie : un article « Arbitrage de `RISK-...` »
        // suivi de champs nommés.
        foreach ($this->blocsArbitrage($texte) as $reference => $bloc) {
            if (!isset($risques[$reference])) {
                continue;
            }
            $risques[$reference]['arbitre_par']    = $this->champ($bloc, "l'Article 1 d'`([A-Z-]+-\\d{4})", true) ?? $this->acte($bloc);
            $risques[$reference]['niveau_arbitre'] = $this->niveau((string) $this->champ($bloc, 'Niveau confirmé'));
            $risques[$reference]['traitement']     = $this->champ($bloc, 'Traitement');
            $risques[$reference]['exception']      = $this->reference($this->champ($bloc, 'Exception associée'));
            $risques[$reference]['reexamen']       = $this->champ($bloc, 'Date de réexamen');
        }

        ksort($risques);

        return $this->risques = $risques;
    }

    /** @return array<string,mixed>|null */
    public function resoudreRisque(string $reference): ?array
    {
        return $this->risques()[$reference] ?? null;
    }

    /**
     * Les exceptions de sécurité inscrites.
     *
     * @return array<string,array<string,string>>
     */
    public function exceptions(): array
    {
        if ($this->exceptions !== null) {
            return $this->exceptions;
        }

        $texte = $this->lire(self::EXCEPTIONS);
        $exceptions = [];

        foreach (preg_split('/^## /m', $texte) ?: [] as $bloc) {
            if (!preg_match('/\*\*Référence\s*:\*\*\s*`(EXC-[A-Z]+-\d{4})`/u', $bloc, $m)) {
                continue;
            }
            $exceptions[$m[1]] = [
                'reference'     => $m[1],
                'contourne'     => (string) $this->champ($bloc, 'Loi ou contrôle contourné'),
                'duree'         => (string) $this->champ($bloc, 'Durée'),
                'compensations' => (string) $this->champ($bloc, 'Compensations'),
                'autorite'      => (string) $this->champ($bloc, 'Autorité'),
                'echeance'      => (string) $this->champ($bloc, 'Échéance de rétablissement'),
                'sortie'        => (string) $this->champ($bloc, 'Statut de sortie'),
            ];
        }
        ksort($exceptions);

        return $this->exceptions = $exceptions;
    }

    /**
     * Risques dont le niveau demeure PROPOSÉ, faute d'arbitrage (INV-58).
     *
     * La Loi 65 de `CORE-LAWS-0001` réserve l'acceptation du risque à
     * l'autorité compétente, et l'Article 6 du Registre rappelle que les
     * niveaux proposés l'ont été par un agent artificiel. Un niveau proposé
     * n'est pas un niveau arrêté, et le service ne le promeut pas.
     *
     * @return list<string>
     */
    public function nonArbitres(): array
    {
        return array_values(array_keys(array_filter(
            $this->risques(),
            static fn (array $r) => $r['arbitre_par'] === null,
        )));
    }

    /**
     * Acceptations et exceptions SANS ÉCHÉANCE FERME (INV-57).
     *
     * L'Article 52 du Registre des capacités range « échéance obligatoire »
     * parmi les contrôles requis de cette capacité, et « exception permanente »
     * parmi ses risques. Une acceptation dont le réexamen est suspendu à un
     * événement incertain n'a pas d'échéance : elle a une condition.
     *
     * Le service NOMME la différence. Il ne fixe aucun terme — le fixer serait
     * accepter le risque à la place de l'autorité.
     *
     * @return list<array<string,string>>
     */
    public function sansEcheanceFerme(): array
    {
        $releve = [];

        foreach ($this->risques() as $reference => $r) {
            if ($r['arbitre_par'] === null || $r['reexamen'] === null) {
                continue;
            }
            if ($this->sansTerme((string) $r['reexamen'])) {
                $releve[] = [
                    'reference' => (string) $reference,
                    'espece'    => 'risque accepté',
                    'terme'     => (string) $r['reexamen'],
                ];
            }
        }

        foreach ($this->exceptions() as $reference => $e) {
            if ($this->sansTerme($e['duree']) || $this->sansTerme($e['echeance'])) {
                $releve[] = [
                    'reference' => (string) $reference,
                    'espece'    => 'exception de sécurité',
                    'terme'     => $e['duree'],
                ];
            }
        }

        return $releve;
    }

    /**
     * Exceptions demeurées ouvertes — aucun rétablissement constaté.
     *
     * @return list<string>
     */
    public function exceptionsOuvertes(): array
    {
        return array_values(array_keys(array_filter(
            $this->exceptions(),
            static fn (array $e) => stripos($e['sortie'], 'ouvert') !== false,
        )));
    }

    /**
     * Exceptions dont aucune compensation technique n'est constituée.
     *
     * L'Article 52 range « compensation non testée » parmi les risques de
     * cette capacité. Une compensation qui n'existe pas ne peut être testée,
     * et le Registre le dit lui-même pour `EXC-SEC-0001`.
     *
     * @return list<string>
     */
    public function sansCompensationTechnique(): array
    {
        return array_values(array_keys(array_filter(
            $this->exceptions(),
            static fn (array $e) => stripos($e['compensations'], 'aucun contrôle technique') !== false,
        )));
    }

    /** @return array<string,mixed> */
    public function ecarts(): array
    {
        return [
            'risques'            => count($this->risques()),
            'exceptions'         => count($this->exceptions()),
            'non_arbitres'       => $this->nonArbitres(),
            'sans_echeance_ferme' => $this->sansEcheanceFerme(),
            'exceptions_ouvertes' => $this->exceptionsOuvertes(),
            'sans_compensation_technique' => $this->sansCompensationTechnique(),
            'methode_evaluation' => self::NON_ETABLI,
            'seuils'             => self::NON_ETABLI,
            'frequence_revue'    => self::NON_ETABLI,
            'portee' => "Registre dérivé. Le service n'évalue, n'accepte et ne clôt aucun risque : la Loi 65 réserve l'acceptation à l'autorité.",
        ];
    }

    // ------------------------------------------------------------------ interne

    /**
     * Les blocs d'arbitrage, indexés par la référence du risque arbitré.
     *
     * @return array<string,string>
     */
    private function blocsArbitrage(string $texte): array
    {
        $blocs = [];
        foreach (preg_split('/^## /m', $texte) ?: [] as $bloc) {
            if (!preg_match('/^Article [^\n]*Arbitrage de\s+`(RISK-[A-Z]+-\d{4})`/u', $bloc, $m)) {
                continue;
            }
            $blocs[$m[1]] = $bloc;
        }

        return $blocs;
    }

    /** Le premier acte cité par un bloc d'arbitrage. */
    private function acte(string $bloc): ?string
    {
        return preg_match('/`(ADOPTION-\d{4})[^`]*`/u', $bloc, $m) ? $m[1] : null;
    }

    private function champ(string $bloc, string $etiquette, bool $brut = false): ?string
    {
        $motif = $brut
            ? '/' . $etiquette . '/u'
            : '/\*\*' . preg_quote($etiquette, '/') . '\s*:\*\*\s*(.+?)\s*$/mu';

        return preg_match($motif, $bloc, $m) ? trim($m[1], " .\t") : null;
    }

    private function reference(?string $valeur): ?string
    {
        return $valeur !== null && preg_match('/`(EXC-[A-Z]+-\d{4})`/u', $valeur, $m) ? $m[1] : null;
    }

    private function niveau(?string $cellule): ?string
    {
        return $cellule !== null && preg_match('/\b(S[0-4])\b/', $cellule, $m) ? $m[1] : null;
    }

    private function sansTerme(string $valeur): bool
    {
        foreach (self::SANS_TERME as $forme) {
            if (stripos($valeur, $forme) !== false) {
                return true;
            }
        }

        return false;
    }

    private function lire(string $chemin): string
    {
        $fichier = $this->corpus . '/' . $chemin;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
