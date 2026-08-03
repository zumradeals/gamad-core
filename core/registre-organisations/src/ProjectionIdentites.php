<?php

declare(strict_types=1);

namespace Gamad\RegistreOrganisations;

use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;

/**
 * Résolution de la représentation opposable, combinant CAP-CORE-001,
 * CAP-CORE-002 et CAP-CORE-003 (fiche §15).
 *
 * Quatre notions distinctes, jamais fusionnées en un seul booléen ambigu
 * (fiche §18) :
 *
 *   AFFILIATION_ACTIVE          l'identité est activement affiliée à
 *                                l'organisation, dans ce registre ;
 *   MANDAT_VERIFIE               `CAP-CORE-003` confirme un mandat actif à
 *                                la date demandée, pour la fonction interne
 *                                que l'affiliation référence ;
 *   REPRESENTATION_OPPOSABLE     l'affiliation ET le mandat sont réunis ;
 *   AUTORISATION_OPERATIONNELLE  décision de `CAP-CORE-004` pour une
 *                                opération donnée — hors périmètre de cette
 *                                classe, laissée à la couche applicative.
 *
 * Une absence de réponse de `CAP-CORE-003` — fonction non liée, mandat
 * absent, module indisponible — vaut toujours non opposable (fiche §15,
 * §25, §32) : cette classe ne relève JAMAIS `DIRIGEANT` ou `REPRESENTANT` au
 * rang de droit automatique.
 */
final class ProjectionIdentites
{
    public function __construct(
        private \PDO $magasin,
        private Ctr01 $identites,
        private ?Ctr02 $autorites = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function verifierAppartenance(
        string $identite,
        string $organisation,
        ?string $type = null,
        ?string $date = null,
    ): array {
        $jour = $date ?? date('Y-m-d');
        $affiliations = $this->affiliationsActives($identite, $organisation, $jour);
        if ($type !== null) {
            $affiliations = array_values(array_filter(
                $affiliations,
                static fn (array $a): bool => $a['type_affiliation_reference'] === $type,
            ));
        }

        return [
            'membre' => $affiliations !== [],
            'identite_reference' => $identite,
            'organisation_reference' => $organisation,
            'affiliations' => array_map(static fn (array $a): string => (string) $a['reference'], $affiliations),
            'date' => $jour,
        ];
    }

    /** @return array<string,mixed> */
    public function verifierRepresentation(
        string $identite,
        string $organisation,
        ?string $action = null,
        ?string $date = null,
    ): array {
        $jour = $date ?? date('Y-m-d');
        $motifs = [];

        $identiteEtat = $this->identites->resoudreIdentite($identite);
        if ($identiteEtat === null || !in_array($identiteEtat['etat'], ['ACTIVE', 'VERIFIEE'], true)) {
            $motifs[] = 'IDENTITE_NON_ACTIVE';
        }

        $affiliations = array_values(array_filter(
            $this->affiliationsActives($identite, $organisation, $jour),
            static fn (array $a): bool => in_array(
                $a['type_affiliation_reference'],
                PolitiqueOrganisations::AFFILIATIONS_A_MANDAT,
                true,
            ),
        ));
        if ($affiliations === []) {
            $motifs[] = 'AFFILIATION_ABSENTE';
        }

        $mandatVerifie = false;
        $mandatReference = null;
        foreach ($affiliations as $affiliation) {
            $fonction = $this->fonctionLieeAffiliation($organisation, $affiliation, $jour);
            $fonctionMandat = $fonction['mandat_fonction_reference'] ?? null;
            if ($fonctionMandat === null || $this->autorites === null) {
                continue;
            }
            try {
                $mandat = $this->autorites->resoudreMandat((string) $fonctionMandat, $identite, $jour);
            } catch (\Throwable) {
                $mandat = null;
            }
            if ($mandat !== null && is_string($mandat['etat'] ?? null) && str_starts_with($mandat['etat'], 'ACTIF')) {
                $mandatVerifie = true;
                $mandatReference = $mandat['mandat'] ?? null;
                break;
            }
        }
        if (!$mandatVerifie) {
            $motifs[] = $this->autorites === null ? 'MANDAT_INDISPONIBLE' : 'MANDAT_ABSENT';
        }

        $opposable = $motifs === [];

        return [
            'opposable' => $opposable,
            'affiliation' => $affiliations[0]['reference'] ?? null,
            'organisation' => $organisation,
            'identite' => $identite,
            'mandat' => $mandatReference,
            'action' => $action,
            'date' => $jour,
            'motifs' => $motifs,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function affiliationsActives(string $identite, string $organisation, string $date): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM organisation_affiliation
             WHERE identite_reference = ? AND organisation_reference = ? AND date_debut <= ?'
        );
        $st->execute([$identite, $organisation, $date]);
        $lignes = [];
        foreach ($st->fetchAll() as $a) {
            if ($this->etatAffiliation((string) $a['reference'], $date) === 'ACTIVE') {
                $lignes[] = $a;
            }
        }

        return $lignes;
    }

    private function etatAffiliation(string $reference, string $date): ?string
    {
        $st = $this->magasin->prepare(
            'SELECT etat_reference FROM organisation_affiliation_cycle
             WHERE affiliation_reference = ? AND date_effet <= ?
             ORDER BY date_effet DESC, id DESC LIMIT 1'
        );
        $st->execute([$reference, $date]);
        $etat = $st->fetchColumn();

        return $etat === false ? null : (string) $etat;
    }

    /** @param array<string,mixed> $affiliation @return array<string,mixed>|null */
    private function fonctionLieeAffiliation(string $organisation, array $affiliation, string $date): ?array
    {
        // Une fonction couvre l'affiliation lorsqu'elle appartient à la même
        // organisation (et, si l'affiliation en porte une, à la même unité) et
        // qu'elle est active à la date demandée. Aucun rapprochement flou.
        $sql = 'SELECT * FROM organisation_fonction_interne
                WHERE organisation_reference = ? AND date_debut <= ?
                AND (date_fin IS NULL OR date_fin >= ?)';
        $args = [$organisation, $date, $date];
        if (!empty($affiliation['unite_reference'])) {
            $sql .= ' AND (unite_reference = ? OR unite_reference IS NULL)';
            $args[] = $affiliation['unite_reference'];
        }
        $sql .= ' AND mandat_fonction_reference IS NOT NULL ORDER BY reference LIMIT 1';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $f = $st->fetch();

        return $f === false ? null : $f;
    }
}
