<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

use Gamad\JournalEvenements\LivreurEvenements;

/**
 * Acquisition de signaux depuis CAP-CORE-014 (doc de chantier 01 §7, doc 02
 * §10) — la seule classe de ce module qui contacte réellement le journal
 * d'événements. `RegistreMatching` documente explicitly qu'elle ne le fait
 * jamais elle-même ; cette classe existe précisément pour combler cet écart
 * sans violer cette frontière.
 *
 * Réserve honnête, vérifiée le 2026-08-06 : **aucune capacité ni satellite
 * du Core ne publie aujourd'hui d'événement `SIGNAL_NORMALISE_DISPONIBLE`**.
 * Cette classe est une tuyauterie réelle et testée (elle réutilise
 * `LivreurEvenements`, déjà éprouvé par CAP-CORE-014), prête dès qu'un
 * producteur existera — pas un raccordement déjà exercé en conditions
 * réelles. Le contrat `EVENEMENT` porteur du type ci-dessous et la forme
 * exacte de sa charge restent à confirmer avec le premier producteur réel ;
 * ce qui suit est une proposition cohérente avec le modèle de `matching_signal`
 * (doc 02 §10), pas une donnée déjà adoptée.
 *
 * Forme de charge attendue (`charge_json` de l'événement) :
 *   signal_code, valeur_type, valeur_normalisee, valide_jusqua,
 *   confiance_source (optionnel). Le reste (sujet, source, realm, finalité,
 *   contrat, classification, instant d'observation) vient de l'enveloppe de
 *   l'événement lui-même, jamais dupliqué dans la charge.
 *
 * Chaque livraison correctement matérialisée en `matching_signal` est
 * accusée ; une livraison rejetée (type inattendu, charge incomplète,
 * signal refusé par `RegistreMatching::enregistrerSignal`) ne l'est jamais —
 * elle reste disponible pour une nouvelle tentative ou une lettre morte
 * selon la politique de l'abonnement, gérée par CAP-CORE-014 lui-même.
 */
final class AcquisitionSignaux
{
    public const TYPE_EVENEMENT = 'CAP-CORE-021.SIGNAL_NORMALISE_DISPONIBLE';

    public function __construct(
        private LivreurEvenements $livreur,
        private RegistreMatching $matching,
    ) {
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function acquerir(string $abonnement, string $consommateur, int $limite, string $correlation): array
    {
        $bail = $this->livreur->obtenirLivraisons($abonnement, $consommateur, $limite, null, $correlation);
        if (isset($bail['refus'])) {
            return $bail;
        }

        $materialises = [];
        $accusables = [];
        $refuses = [];
        foreach ($bail['livraisons'] as $livraison) {
            $evenement = $livraison['evenement'];
            $charge = $livraison['charge'];
            if ($evenement === null || $evenement['type'] !== self::TYPE_EVENEMENT || $charge === null) {
                $refuses[] = ['livraison' => $livraison['livraison'], 'motif' => 'TYPE_OU_CHARGE_INDISPONIBLE'];
                continue;
            }
            $donnees = $this->mapper($evenement, $charge);
            if ($donnees === null) {
                $refuses[] = ['livraison' => $livraison['livraison'], 'motif' => 'CHARGE_INCOMPLETE'];
                continue;
            }
            $resultat = $this->matching->enregistrerSignal($donnees);
            if (isset($resultat['refus'])) {
                $refuses[] = ['livraison' => $livraison['livraison'], 'motif' => $resultat['refus']];
                continue;
            }
            $materialises[] = ['livraison' => $livraison['livraison'], 'signal' => $resultat['reference']];
            $accusables[] = $livraison['livraison'];
        }

        $accuses = [];
        if ($accusables !== [] && $bail['bail'] !== null) {
            $accuses = $this->livreur->accuserLivraisons($abonnement, (string) $bail['bail'], $accusables, $correlation)['resultats'] ?? [];
        }
        // Une livraison refusée n'est jamais laissée sous bail sans suite : elle
        // est explicitement rendue disponible pour une nouvelle tentative (ou
        // passée en lettre morte par CAP-CORE-014 lui-même si les tentatives
        // maximales de l'abonnement sont atteintes), jamais silencieusement
        // abandonnée jusqu'à l'expiration naturelle du bail.
        if ($refuses !== [] && $bail['bail'] !== null) {
            foreach ($refuses as $refus) {
                $this->livreur->refuserTemporairement($abonnement, (string) $bail['bail'], (string) $refus['livraison'], (string) $refus['motif'], null, $correlation);
            }
        }

        return [
            'abonnement' => $abonnement,
            'bail' => $bail['bail'],
            'livraisons_recues' => count($bail['livraisons']),
            'signaux_materialises' => $materialises,
            'livraisons_refusees' => $refuses,
            'accuses' => $accuses,
        ];
    }

    /**
     * @param array<string,mixed> $evenement enveloppe projetée par RegistreEvenements::resoudreEvenement
     * @param array<string,mixed> $charge charge_json décodée
     * @return array<string,mixed>|null null si l'enveloppe ou la charge n'a pas les champs minimaux
     */
    private function mapper(array $evenement, array $charge): ?array
    {
        foreach (['signal_code', 'valeur_type', 'valide_jusqua'] as $champ) {
            if (!isset($charge[$champ]) || trim((string) $charge[$champ]) === '') {
                return null;
            }
        }
        foreach (['sujet_type', 'sujet_reference', 'source_reference', 'realm_reference', 'finalite_reference',
            'contrat_reference', 'contrat_version', 'classification', 'survenu_le'] as $champ) {
            if (!isset($evenement[$champ]) || trim((string) $evenement[$champ]) === '') {
                return null;
            }
        }

        return [
            'sujet_type' => (string) $evenement['sujet_type'],
            'sujet_reference' => (string) $evenement['sujet_reference'],
            'signal_code' => (string) $charge['signal_code'],
            'valeur_type' => (string) $charge['valeur_type'],
            'valeur_normalisee' => $charge['valeur_normalisee'] ?? null,
            'source_reference' => (string) $evenement['source_reference'],
            'finalite_reference' => (string) $evenement['finalite_reference'],
            'realm_reference' => (string) $evenement['realm_reference'],
            'contrat_reference' => (string) $evenement['contrat_reference'],
            'contrat_version' => (string) $evenement['contrat_version'],
            'observation_le' => (string) $evenement['survenu_le'],
            'valide_jusqua' => (string) $charge['valide_jusqua'],
            'confiance_source' => isset($charge['confiance_source']) && is_numeric($charge['confiance_source']) ? (float) $charge['confiance_source'] : null,
            'classification' => (string) $evenement['classification'],
        ];
    }
}
