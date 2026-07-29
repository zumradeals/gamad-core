<?php

declare(strict_types=1);

namespace Gamad\RegistreAudit;

/**
 * Les opérations du contrat CTR-10 — volet AUDIT
 * (CAP-CORE-013, conception adoptée par ADOPTION-0057).
 *
 * `CTR-10` — Audit et intégrité est gardée par `DOM-09` et partagée entre
 * `CAP-CORE-013` et `CAP-CORE-015`, toutes deux `DOM-09` (Article 120 de
 * `CORE-ATLAS-0001`). Le partage est régulier : `INV-40` est satisfait.
 * `CAP-CORE-015` en tient le volet intégrité — `Gamad\RegistrePreuves\Ctr10`.
 * Le présent module en tient le volet audit, et ne recouvre pas l'autre :
 * l'intégrité atteste d'un OBJET, l'audit atteste d'un ACTE.
 *
 * Lecture et attestation seulement (INV-4).
 *
 * CE SERVICE NE PRONONCE AUCUNE LEVÉE ET AUCUN JUGEMENT DE SUFFISANCE.
 * Il restitue ce que le corpus écrit, restrictions comprises, et nomme la
 * non-indépendance de la fonction sous laquelle il est lui-même écrit.
 *
 * Invariant porté :
 *   INV-62 une réserve levée par décision n'est pas une réserve résolue.
 */
final class Ctr10
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-013';

    private const DOSSIER   = 'genesis-ii/audit/DOSSIER-AUDIT-G0-0001.md';
    private const AUTORITES = 'genesis-ii/registres/autorites/REGISTRE-INITIAL-AUTORITES-MANDATS-0001.md';
    private const ACTES     = 'genesis-ii/registre';

    /**
     * Ce que l'Article 49 attend comme preuves `G0`, et que le corpus
     * n'établit pas. Ce sont ses décisions ouvertes, réservées à l'autorité.
     */
    public const CHAMPS_DECLARABLES = [
        'evenements_auditables',
        'politique_de_conservation',
        'delais_de_consultation',
        'acces_aux_journaux',
        'independance_de_la_fonction',
    ];

    public const NON_ETABLI = 'NON ÉTABLI';

    /** Les fonctions d'audit et de contrôle attribuées à titre transitoire. */
    private const FONCTIONS_TRANSITOIRES = ['AUDIT', 'AUT-SEC', 'AUT-EXP', 'AUT-ING'];

    public function __construct(private string $corpus)
    {
    }

    /**
     * Les réserves de `G0`, avec leur levée ET la restriction que la levée
     * porte elle-même (INV-62).
     *
     * Le Titre V du dossier d'audit lève les cinq écarts de ses Articles 6
     * à 10. Deux de ces levées écrivent ce qu'elles ne valent pas :
     *
     *   · « levé par décision documentée, NON par résolution technique
     *     complète » — l'écart sur les accès et secrets ;
     *   · « levé au sens d'une décision de statut, NON d'une certification de
     *     conformité » — les quatre produits non qualifiés.
     *
     * Le corpus a pris soin d'écrire ces restrictions. Aucun service ne les
     * restituait, et un lecteur du seul constat final — « les cinq écarts sont
     * tous levés » — en tirerait l'inverse.
     *
     * Le service ne requalifie aucune levée, n'en annule aucune, n'en prononce
     * aucune, et ne juge pas si la restriction est suffisante. Il la rend
     * visible.
     *
     * @return list<array<string,mixed>>
     */
    public function reserves(): array
    {
        $texte = $this->lire(self::DOSSIER);

        if (!preg_match_all(
            '/^## Article (\d+) — Levée de l\'écart de l\'Article (\d+)(.*?)$\n\n(.+?)(?=\n\n## |\n\n---|\z)/msu',
            $texte,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        $reserves = [];
        foreach ($matches as $m) {
            $corps = trim($m[4]);
            $enonce = $this->enonceDeLevee($corps);

            $reserves[] = [
                'ecart'        => 'Article ' . $m[2],
                'objet'        => trim($m[3], " ()\t"),
                'article_levee' => 'Article ' . $m[1],
                'levee'        => $enonce !== null,
                'enonce'       => $enonce,
                'restreinte'   => $enonce !== null && $this->porteUneRestriction($enonce),
                'restriction'  => $enonce !== null ? $this->restriction($enonce) : null,
                'corps'        => $corps,
            ];
        }

        return $reserves;
    }

    /**
     * Les réserves dont la levée porte sa propre restriction — le cœur de
     * `INV-62`. Ce sont celles qu'un constat global rendrait invisibles.
     *
     * @return list<array<string,mixed>>
     */
    public function reservesLeveesSousRestriction(): array
    {
        return array_values(array_filter(
            $this->reserves(),
            static fn (array $r): bool => $r['restreinte'] === true,
        ));
    }

    /**
     * Les fonctions d'audit et de contrôle tenues par l'autorité qu'elles
     * devraient auditer.
     *
     * `ADOPTION-0022` attribue `AUDIT`, `AUT-SEC`, `AUT-EXP` et `AUT-ING` à
     * l'autorité de proposition seule, à titre transitoire. C'est
     * `RISK-SEC-0001`, que le service de `CAP-CORE-017` restitue déjà comme
     * accepté sans échéance ferme.
     *
     * `CAP-CORE-013` a mission d'établir « qui a fait quoi, sous quelle
     * autorité ». Elle ne peut pas taire que l'autorité de la fonction d'audit
     * et l'autorité auditée sont la même personne.
     *
     * @return array<string,mixed>
     */
    public function independanceDeLAudit(): array
    {
        $texte = $this->lire(self::DOSSIER);

        $constat = null;
        if (preg_match(
            '/^## Article \d+ — Levée de l\'écart de l\'Article \d+ \(fonctions vacantes\)\s*$\n\n(.+?)$/mu',
            $texte,
            $m,
        )) {
            $constat = trim($m[1]);
        }

        $nommees = [];
        foreach (self::FONCTIONS_TRANSITOIRES as $fonction) {
            if ($constat !== null && str_contains($constat, $fonction)) {
                $nommees[] = $fonction;
            }
        }

        return [
            'independante'       => false,
            'fonctions_transitoires' => $nommees,
            'detenteur'          => 'autorité de proposition seule, à titre transitoire',
            'constat'            => $constat,
            'risque_associe'     => 'RISK-SEC-0001 — absence de séparation entre l\'audit et les fonctions auditées',
            'source'             => 'DOSSIER-AUDIT-G0-0001, Titre V ; ADOPTION-0022, Article 1',
            'portee'             => 'Le service nomme la non-indépendance ; il ne l\'atténue pas et ne la corrige pas.',
        ];
    }

    /**
     * La trace d'adoption d'un acte : qui a fait quoi, sous quelle autorité,
     * à quelle date. C'est l'opération centrale du volet audit de `CTR-10`.
     *
     * Le corpus enregistre cette trace sous TROIS FORMES distinctes, apparues
     * à des dates différentes et jamais unifiées — le service les nomme plutôt
     * que de les confondre, et ne réécrit aucun acte pour les uniformiser.
     *
     * @return array<string,mixed>
     */
    public function traceDAdoption(string $reference): array
    {
        $fichier = $this->fichierDeLActe($reference);
        if ($fichier === null) {
            return [
                'reference'      => $reference,
                'reconstituable' => false,
                'forme'          => null,
                'autorite'       => null,
                'date'           => null,
                'motif'          => 'aucun acte de cette référence',
            ];
        }

        $texte = $this->lire($fichier);

        // Forme 1 — bloc « Autorité d'adoption » à liste de champs.
        if (preg_match('/^\s*-\s*\*\*Nom\s*:\*\*\s*(.+?)\s*(?:—|$)/mu', $texte, $m)) {
            return $this->trace($reference, 'bloc « Autorité d\'adoption »', trim($m[1]), $texte, $fichier);
        }

        // Forme 2 — mention « Autorité constatante », propre au constat de G0.
        if (preg_match('/\*\*Autorité constatante\s*:\*\*\s*(.+?)(?:,\s*dirigeant|\.)/u', $texte, $m)) {
            return $this->trace($reference, 'mention « Autorité constatante »', trim($m[1]), $texte, $fichier);
        }

        // Forme 3 — déclaration en prose ouvrant l'acte.
        if (preg_match('/^Le \d+[a-zé]* \w+ \d{4}, (.+?), .*?déclare avoir/mu', $texte, $m)) {
            return $this->trace($reference, 'déclaration en prose', trim($m[1]), $texte, $fichier);
        }

        return [
            'reference'      => $reference,
            'reconstituable' => false,
            'forme'          => null,
            'autorite'       => null,
            'date'           => null,
            'motif'          => 'aucune des formes connues n\'est présente',
        ];
    }

    /**
     * Le recensement des formes sous lesquelles le corpus enregistre sa propre
     * trace d'adoption, et les actes dont la trace ne se reconstitue pas.
     *
     * L'Article 49 range « impossibilité de reconstruire une action » parmi
     * les risques de `CAP-CORE-013`. Une trace qui prend trois formes n'est
     * reconstituable que par un lecteur qui les connaît toutes les trois : le
     * service constate le fait, il ne le corrige pas (INV-43).
     *
     * @return array<string,mixed>
     */
    public function formesDeTrace(): array
    {
        $formes = [];
        $incompletes = [];

        foreach ($this->actes() as $reference) {
            $trace = $this->traceDAdoption($reference);
            if ($trace['reconstituable'] !== true) {
                $incompletes[] = $reference;
                continue;
            }
            $forme = (string) $trace['forme'];
            $formes[$forme] = ($formes[$forme] ?? 0) + 1;
        }

        ksort($formes);

        return [
            'actes'        => count($this->actes()),
            'formes'       => $formes,
            'nombre_de_formes' => count($formes),
            'incompletes'  => $incompletes,
            'portee'       => 'Le service constate l\'hétérogénéité ; il ne réécrit aucun acte pour l\'uniformiser (INV-43).',
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
        $reserves     = $this->reserves();
        $restreintes  = $this->reservesLeveesSousRestriction();
        $independance = $this->independanceDeLAudit();
        $formes       = $this->formesDeTrace();

        return [
            'reserves'              => count($reserves),
            'reserves_levees'       => count(array_filter($reserves, static fn (array $r): bool => $r['levee'] === true)),
            'reserves_restreintes'  => count($restreintes),
            'audit_independant'     => $independance['independante'],
            'fonctions_transitoires' => $independance['fonctions_transitoires'],
            'actes'                 => $formes['actes'],
            'formes_de_trace'       => $formes['nombre_de_formes'],
            'traces_incompletes'    => count($formes['incompletes']),
            'champs_non_etablis'    => array_keys($this->champs()),
            'ecart_global_preuve'   => 'Article 49 — format commun, conservation et mécanisme d\'audit non établis',
            'portee' => 'Le service restitue les levées AVEC leurs restrictions. Il ne prononce, ne requalifie et ne juge aucune levée (INV-62).',
        ];
    }

    // ------------------------------------------------------------------ interne

    /**
     * L'énoncé de levée d'une réserve : la phrase qui porte le verbe « lever ».
     * C'est elle, et non le titre de l'article, qui peut porter une restriction.
     */
    private function enonceDeLevee(string $corps): ?string
    {
        $sansGras = str_replace('**', '', $corps);
        foreach (preg_split('/(?<=\.)\s+/u', $sansGras) ?: [] as $phrase) {
            if (preg_match('/\b[Ll]ev[ée]/u', $phrase)) {
                return trim($phrase);
            }
        }

        return null;
    }

    /**
     * Une levée porte une restriction lorsqu'elle énonce elle-même ce qu'elle
     * ne vaut pas — « levé par X, NON par Y », « au sens de X, NON de Y ».
     */
    private function porteUneRestriction(string $enonce): bool
    {
        return (bool) preg_match('/\bnon\s+(?:par|d\'une|de|un|une)\b/iu', $enonce);
    }

    private function restriction(string $enonce): ?string
    {
        if (!preg_match('/(\bnon\s+(?:par|d\'une|de|un|une)\b.+?)(?:\.|$)/iu', $enonce, $m)) {
            return null;
        }

        return trim($m[1]);
    }

    /** @return list<string> */
    private function actes(): array
    {
        $repertoire = $this->corpus . '/' . self::ACTES;
        if (!is_dir($repertoire)) {
            return [];
        }

        $references = [];
        foreach ((array) scandir($repertoire) as $entree) {
            if (!is_string($entree) || !preg_match('/^(ADOPTION-\d{4})[-.]/', $entree, $m)) {
                continue;
            }
            $references[$m[1] . ':' . $entree] = $entree;
        }

        ksort($references);

        return array_values($references);
    }

    private function fichierDeLActe(string $reference): ?string
    {
        foreach ($this->actes() as $fichier) {
            if ($fichier === $reference || str_starts_with($fichier, $reference)) {
                return self::ACTES . '/' . $fichier;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function trace(string $reference, string $forme, string $autorite, string $texte, string $fichier): array
    {
        $date = null;
        if (preg_match('/\*\*Date(?:[^*]*)\s*:\*\*\s*(\d{1,2}[a-zé]*\s+\w+\s+\d{4})/u', $texte, $m)) {
            $date = trim($m[1]);
        } elseif (preg_match('/^Le (\d{1,2}[a-zé]*\s+\w+\s+\d{4}),/mu', $texte, $m)) {
            $date = trim($m[1]);
        }

        return [
            'reference'      => $reference,
            'reconstituable' => $autorite !== '' && $date !== null,
            'forme'          => $forme,
            'autorite'       => $autorite,
            'date'           => $date,
            'source'         => $fichier,
        ];
    }

    private function lire(string $chemin): string
    {
        $fichier = $this->corpus . '/' . $chemin;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
