<?php

declare(strict_types=1);

namespace Gamad\RegistreIncidents;

/**
 * Les opérations du contrat CTR-11 — Risque et incident, pour la part
 * INCIDENTS (CAP-CORE-018, conception adoptée par ADOPTION-0055).
 *
 * La famille `CTR-11` sert deux capacités ; ce module sert les incidents,
 * celui de `core/registre-risques/` sert les risques et exceptions, et chacun
 * déclare la capacité qu'il sert (INV-41). Le partage est régulier (INV-40).
 *
 * Lecture et attestation seulement (INV-4). Le service ne déclare, ne classe
 * et ne clôt aucun incident.
 *
 * CE SERVICE CONSTATE UNE ABSENCE — mais pas la même que celle des realms.
 * Le Registre des incidents EXISTE, il est ouvert, il est vide, et il porte
 * une déclaration motivée d'absence. L'Article 53 du Registre des capacités
 * admettait expressément cette alternative : « registre initial des incidents
 * connus OU déclaration motivée d'absence ». La condition est satisfaite ;
 * celle des realms ne l'est pas. Confondre les deux situations effacerait
 * cette différence (INV-59).
 *
 * Invariant porté :
 *   INV-59 une déclaration motivée d'absence est distinguée d'une absence
 *          d'inventaire.
 */
final class Ctr11
{
    /** La capacité souveraine que ce module sert (INV-41). */
    public const CAPACITE = 'CAP-CORE-018';

    private const REGISTRE = 'genesis-ii/registres/securite/REGISTRE-INITIAL-INCIDENTS-SECURITE-0001.md';

    /** Niveaux d'incident — Article 170 de SECURITY-GOVERNANCE-0001. */
    public const NIVEAUX = ['I0', 'I1', 'I2', 'I3', 'I4'];

    /** Champs que l'Article 53 exige et que le corpus n'établit pas. */
    public const CHAMPS_DECLARABLES = ['classification', 'delais', 'autorites_de_crise', 'politique_communication'];

    public const NON_ETABLI = 'NON ÉTABLI';

    public function __construct(private string $corpus)
    {
    }

    /**
     * Les incidents inscrits, dérivés d'une forme déclarative.
     *
     * Aucun n'est inscrit. Le service ne cherche aucun incident dans la prose
     * du corpus : un incident trouvé dans une phrase serait un incident
     * déclaré par l'agent, et l'Article 176 de `SECURITY-GOVERNANCE-0001`
     * réserve la déclaration aux acteurs.
     *
     * @return array<string,array<string,string>>
     */
    public function incidents(): array
    {
        $incidents = [];
        foreach (explode("\n", $this->lire()) as $ligne) {
            if (!preg_match(
                '/\*\*Incident\s*:\*\*\s*`(INC-[A-Z]+-\d{4})`\s*—\s*(.+?)\.\s*\*\*Niveau\s*:\*\*\s*`(I[0-4])`/u',
                trim($ligne),
                $m,
            )) {
                continue;
            }
            $incidents[$m[1]] = [
                'reference' => $m[1],
                'libelle'   => trim($m[2]),
                'niveau'    => $m[3],
            ];
        }
        ksort($incidents);

        return $incidents;
    }

    /**
     * Le registre porte-t-il une déclaration motivée d'absence ? (INV-59)
     *
     * L'existence du registre ne suffit pas : un registre vide et muet
     * laisserait ignorer si nul incident n'est survenu ou si nul n'a regardé.
     * La déclaration motivée lève cette ambiguïté, et le service la relève
     * plutôt que de la présumer.
     *
     * @return array<string,mixed>
     */
    public function declarationAbsence(): array
    {
        $texte = $this->lire();

        $motif = null;
        if (preg_match('/^## Article \d+ — Absence d\'incident constaté\s*$\n\n(.+?)$/mu', $texte, $m)) {
            $motif = trim($m[1]);
        }

        return [
            'registre_present' => is_file($this->corpus . '/' . self::REGISTRE),
            'declaree'         => $motif !== null,
            'motif'            => $motif,
        ];
    }

    /**
     * Les faits que le registre nomme et EXCLUT expressément de la
     * qualification d'incident, avec leur motif.
     *
     * Un fait écarté sans motif serait un fait caché — « incident caché » est
     * le premier risque que l'Article 53 énumère. Le service restitue donc
     * l'exclusion avec sa raison, et n'en juge pas le bien-fondé.
     *
     * @return list<array<string,string>>
     */
    public function nonClassifications(): array
    {
        $texte = $this->lire();

        $releve = [];
        foreach (preg_split('/^## /m', $texte) ?: [] as $bloc) {
            if (!preg_match('/^Article \d+ — Non-classification (.+?)\s*$/mu', $bloc, $m)) {
                continue;
            }
            $corps = trim((string) preg_replace('/^Article \d+ — .+?$/mu', '', $bloc));
            $releve[] = [
                'objet'  => trim($m[1]),
                'motif'  => $corps,
            ];
        }

        return $releve;
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
        $absence = $this->declarationAbsence();

        return [
            'incidents'          => count($this->incidents()),
            'registre_present'   => $absence['registre_present'],
            'absence_declaree'   => $absence['declaree'],
            'non_classifications' => $this->nonClassifications(),
            'champs_non_etablis' => array_keys($this->champs()),
            'exercice_scenario'  => self::NON_ETABLI,
            'canal_signalement'  => self::NON_ETABLI,
            'portee' => "Registre présent, ouvert et vide, avec déclaration motivée d'absence. Le service ne déclare, ne classe et ne clôt aucun incident.",
        ];
    }

    // ------------------------------------------------------------------ interne

    private function lire(): string
    {
        $fichier = $this->corpus . '/' . self::REGISTRE;

        return is_file($fichier) ? (string) file_get_contents($fichier) : '';
    }
}
