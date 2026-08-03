<?php

declare(strict_types=1);

namespace Gamad\RegistreRealms;

/**
 * Contrat CTR-12 — Realms Registry (CAP-CORE-012), fiche §46.
 *
 * Façade de lecture minimale destinée aux autres capacités et produits qui
 * doivent seulement répondre à :
 *
 *   Ce realm existe-t-il, dans quel état, avec quelle hiérarchie ?
 *   Cette organisation ou ce produit y est-il rattaché ?
 *   Cette opération est-elle dans la portée déclarée du realm ?
 *
 * Un consommateur de CTR-12 n'écrit jamais directement dans les tables de
 * `CAP-CORE-012` : toute commande passe par `RegistreRealms`, gouvernée par
 * `CAP-CORE-004` et tracée par `CAP-CORE-013` dans la couche applicative
 * (`AccesRealms`). Une panne du registre des realms se traduit ici par une
 * réponse fermée (`null` ou `dans_portee => false`), jamais par une portée
 * globale supposée (fiche §61, §68).
 */
final class Ctr12
{
    public const CAPACITE = 'CAP-CORE-012';

    public function __construct(private RegistreRealms $registre)
    {
    }

    /** @return array<string,mixed>|null */
    public function resoudreRealm(string $reference, ?string $date = null): ?array
    {
        try {
            return $this->registre->resoudreRealm($reference, $date);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function resoudreRealmParCode(string $code, ?string $date = null): ?array
    {
        try {
            return $this->registre->resoudreRealmParCode($code, $date);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function resoudreEtat(string $reference, ?string $date = null): ?array
    {
        try {
            return $this->registre->resoudreEtat($reference, $date);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    public function resoudreAscendance(string $reference, ?string $date = null): array
    {
        try {
            return $this->registre->resoudreAscendance($reference, $date);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function resoudreOrganisations(string $reference, ?string $date = null): array
    {
        try {
            return $this->registre->resoudreOrganisations($reference, $date);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function resoudreProduits(string $reference, ?string $date = null): array
    {
        try {
            return $this->registre->resoudreProduits($reference, $date);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function verifierPortee(array $dossier): array
    {
        try {
            return $this->registre->verifierPortee($dossier);
        } catch (\Throwable) {
            return [
                'dans_portee' => false,
                'realm' => $dossier['realm'] ?? null,
                'motifs' => ['DEPENDANCE_INDISPONIBLE'],
                'faits' => [],
                'avertissement' => 'cette réponse ne constitue pas une autorisation ; seul CAP-CORE-004 décide',
            ];
        }
    }
}
