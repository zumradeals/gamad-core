<?php

declare(strict_types=1);

namespace Gamad\RegistreRealms;

/**
 * Moteur déterministe de contrôle de portée (CAP-CORE-012, fiche §40).
 *
 * Pure fonction sur des faits déjà rassemblés par `RegistreRealms` : cette
 * classe n'ouvre aucune connexion et ne lit aucune table — elle applique
 * seulement les règles de refus par défaut, dans un ordre stable, et explique
 * chaque refus par un motif canonique de `PolitiqueRealms::MOTIFS_REFUS`.
 *
 * Sa réponse ne constitue jamais une autorisation : `CAP-CORE-004` rend la
 * décision finale (fiche §40, §68).
 */
final class EvaluateurPortee
{
    /**
     * @param array<string,mixed> $dossier
     * @param array<string,mixed> $faits
     * @return array<string,mixed>
     */
    public static function evaluer(array $dossier, array $faits): array
    {
        $motifs = [];

        if (($faits['realm_connu'] ?? false) !== true) {
            $motifs[] = 'REALM_INCONNU';

            return self::resultat($dossier, $faits, $motifs);
        }

        $motifEtat = match ($faits['realm_etat'] ?? null) {
            'PREPARATION' => 'REALM_EN_PREPARATION',
            'SUSPENDU' => 'REALM_SUSPENDU',
            'FERME' => 'REALM_FERME',
            'RETIRE' => 'REALM_RETIRE',
            'ACTIF' => null,
            default => 'REALM_INCONNU',
        };
        if ($motifEtat !== null) {
            $motifs[] = $motifEtat;
        }

        if (($faits['organisation_fournie'] ?? false) === true) {
            if (($faits['organisation_rattachee'] ?? false) !== true) {
                $motifs[] = 'ORGANISATION_NON_RATTACHEE';
            } elseif (($faits['organisation_active'] ?? false) !== true) {
                $motifs[] = 'ORGANISATION_INACTIVE';
            }
            if (($faits['mandat_requis'] ?? false) === true && ($faits['mandat_verifie'] ?? false) !== true) {
                $motifs[] = 'MANDAT_INSUFFISANT';
            }
        }

        if (($faits['produit_fourni'] ?? false) === true) {
            if (($faits['produit_rattache'] ?? false) !== true) {
                $motifs[] = 'PRODUIT_NON_RATTACHE';
            } elseif (($faits['produit_actif'] ?? false) !== true) {
                $motifs[] = 'PRODUIT_INACTIF';
            }
        }

        if (($faits['contrat_fourni'] ?? false) === true) {
            if (($faits['contrat_rattache'] ?? false) !== true) {
                $motifs[] = 'CONTRAT_NON_RATTACHE';
            } elseif (($faits['contrat_actif'] ?? false) !== true) {
                $motifs[] = 'CONTRAT_INACTIF';
            }
        }

        if (($faits['finalite_requise'] ?? false) === true && ($faits['finalite_fournie'] ?? false) !== true) {
            $motifs[] = 'FINALITE_INCONNUE';
        }

        // Un refus applicable est toujours prioritaire sur l'absence de
        // permission (fiche §23, §40 point 9).
        if (($faits['franchissement_croise'] ?? false) === true) {
            if (($faits['franchissement_refuse'] ?? false) === true) {
                $motifs[] = 'FRANCHISSEMENT_REFUSE';
            } elseif (($faits['franchissement_permis'] ?? false) !== true) {
                $motifs[] = 'FRANCHISSEMENT_NON_DECLARE';
            }
        }

        if (($faits['verification_expiree'] ?? false) === true) {
            $motifs[] = 'VERIFICATION_EXPIREE';
        }

        if (($faits['dependance_indisponible'] ?? false) === true) {
            $motifs[] = 'DEPENDANCE_INDISPONIBLE';
        }

        return self::resultat($dossier, $faits, $motifs);
    }

    /**
     * @param array<string,mixed> $dossier
     * @param array<string,mixed> $faits
     * @param list<string> $motifs
     * @return array<string,mixed>
     */
    private static function resultat(array $dossier, array $faits, array $motifs): array
    {
        return [
            'dans_portee' => $motifs === [],
            'realm' => $dossier['realm'] ?? null,
            'motifs' => array_values(array_unique($motifs)),
            'faits' => $faits,
            'avertissement' => 'cette réponse ne constitue pas une autorisation ; seul CAP-CORE-004 décide',
        ];
    }
}
