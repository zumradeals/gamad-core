<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

use Gamad\RegistreSecretsCles\UsageSecret;

/**
 * Registre des preuves d'intégrité (CAP-CORE-015).
 *
 * Sépare toujours artefact/représentation canonique/empreinte/signature
 * facultative (fiche partie 2 §1). Ne conserve jamais de clé privée : les
 * signatures sont demandées à `CAP-CORE-016` via `ServiceSignature`, qui ne
 * retourne que la signature produite, jamais le matériel.
 *
 * Comme les autres registres persistants du Core, ce module ne décide
 * d'aucune autorisation lui-même — `preuve_autorisation_reference` est un
 * intrant obligatoire, produite en amont par la couche applicative
 * (`CAP-CORE-004`).
 */
final class RegistrePreuves
{
    public const CAPACITE = 'CAP-CORE-015';

    public function __construct(
        private \PDO $magasin,
        private ?ServiceSignature $signature = null,
    ) {
        SchemaPreuves::migrer($this->magasin);
    }

    // ------------------------------------------------------------------
    // Préparation

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function preparerPreuve(array $dossier): array
    {
        $g = $this->refuserChampsInterdits($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['type_preuve', 'sujet_type', 'sujet_reference', 'realm_reference', 'finalite_reference', 'source_reference', 'classification'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $type = (string) $dossier['type_preuve'];
        if (!in_array($type, PolitiquePreuves::TYPES_PREUVE, true)) {
            return $this->refus('TYPE_PREUVE_INCONNU', 'type_preuve hors liste close');
        }
        $classification = (string) $dossier['classification'];
        if (!in_array($classification, PolitiquePreuves::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }
        if (empty($dossier['producteur_capacite_reference']) && empty($dossier['producteur_produit_reference']) && empty($dossier['producteur_identite_reference'])) {
            return $this->refus('PRODUCTEUR_ABSENT', 'au moins un producteur (capacité, produit ou identité) est requis');
        }
        $idempotencyKey = $this->nullable($dossier['idempotency_key'] ?? null);
        if ($idempotencyKey !== null) {
            $existante = $this->lignePreuveParIdempotence((string) $dossier['producteur'], $type, $idempotencyKey);
            if ($existante !== null) {
                return ['reference' => $existante['reference'], 'etat' => 'PREPAREE', 'idempotent' => true];
            }
        }

        $representation = $dossier['representation'] ?? null;
        if (!is_array($representation) || !in_array($representation['format_representation'] ?? null, PolitiquePreuves::FORMATS_REPRESENTATION, true)) {
            return $this->refus('REPRESENTATION_INVALIDE', 'representation.format_representation hors liste close');
        }
        if (isset($representation['chemin_logique']) && is_string($representation['chemin_logique'])) {
            $refusChemin = $this->refuserCheminLogique((string) $representation['chemin_logique']);
            if ($refusChemin !== null) {
                return $refusChemin;
            }
        }
        $contenuInline = $representation['contenu_inline'] ?? null;
        if (is_string($contenuInline) && strlen($contenuInline) > PolitiquePreuves::CONTENU_INLINE_MAX_OCTETS) {
            return $this->refus('CONTENU_TROP_VOLUMINEUX', 'contenu_inline dépasse la limite autorisée');
        }

        return $this->transaction(function () use ($dossier, $type, $classification, $idempotencyKey, $representation): array {
            $reference = 'PRF-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO preuve
                 (reference,type_preuve,sujet_type,sujet_reference,producteur_capacite_reference,
                  producteur_produit_reference,producteur_identite_reference,organisation_reference,
                  realm_reference,finalite_reference,source_reference,contrat_reference,contrat_version,
                  classification,description,cree_le,cree_par_reference,correlation_id,idempotency_key)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $type, (string) $dossier['sujet_type'], (string) $dossier['sujet_reference'],
                $this->nullable($dossier['producteur_capacite_reference'] ?? null),
                $this->nullable($dossier['producteur_produit_reference'] ?? null),
                $this->nullable($dossier['producteur_identite_reference'] ?? null),
                $this->nullable($dossier['organisation_reference'] ?? null),
                (string) $dossier['realm_reference'], (string) $dossier['finalite_reference'],
                (string) $dossier['source_reference'], $this->nullable($dossier['contrat_reference'] ?? null),
                $this->nullable($dossier['contrat_version'] ?? null), $classification,
                $this->nullable($dossier['description'] ?? null), $maintenant,
                (string) $dossier['producteur'], (string) ($dossier['correlation_id'] ?? ''), $idempotencyKey,
            ]);

            $this->magasin->prepare(
                'INSERT INTO preuve_representation
                 (preuve_reference,format_representation,version_canonicalisation,media_type,taille_octets,
                  artefact_reference,chemin_logique,contenu_inline,encodage,metadonnees_json,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, (string) $representation['format_representation'],
                (string) PolitiquePreuves::VERSION_CANONICALISATION,
                (string) ($representation['media_type'] ?? 'application/octet-stream'),
                isset($representation['taille_octets']) ? (int) $representation['taille_octets'] : null,
                $this->nullable($representation['artefact_reference'] ?? null),
                $this->nullable($representation['chemin_logique'] ?? null),
                $this->nullable($representation['contenu_inline'] ?? null),
                $this->nullable($representation['encodage'] ?? null),
                json_encode($representation['metadonnees'] ?? [], JSON_UNESCAPED_SLASHES), $maintenant,
            ]);

            $this->inscrireCycle($reference, 'PREPAREE', $maintenant, null, (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

            return ['reference' => $reference, 'etat' => 'PREPAREE'];
        });
    }

    // ------------------------------------------------------------------
    // Empreinte

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function emettreEmpreinte(string $reference, string $algorithme, string $contenu, array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $preuve = $this->lignePreuve($reference);
        if ($preuve === null) {
            return $this->refus('PREUVE_INCONNUE', "preuve `{$reference}` inconnue");
        }
        if ($this->etatCourant($reference) !== 'PREPAREE') {
            return $this->refus('ETAT_INVALIDE', 'seule une preuve PREPAREE peut recevoir une empreinte');
        }
        if (in_array($algorithme, PolitiquePreuves::ALGORITHMES_EMPREINTE_REFUSES, true)) {
            return $this->refus('ALGORITHME_REFUSE', "{$algorithme} est refusé pour toute nouvelle preuve");
        }
        if (!in_array($algorithme, PolitiquePreuves::ALGORITHMES_EMPREINTE_AUTORISES, true)) {
            return $this->refus('ALGORITHME_INCONNU', 'algorithme hors liste close');
        }

        $empreinteHex = CalculateurEmpreinte::empreinteChaine($contenu, $algorithme);
        $signatureRequise = !empty($dossier['signature_requise']);

        return $this->transaction(function () use ($reference, $algorithme, $empreinteHex, $contenu, $dossier, $signatureRequise): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO preuve_empreinte
                 (preuve_reference,algorithme,empreinte_hex,taille_bits,calculee_le,calculateur_version,
                  representation_empreinte,est_principale,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $algorithme, $empreinteHex, CalculateurEmpreinte::longueurAttendueBits($algorithme),
                $maintenant, (string) CalculateurEmpreinte::VERSION, 'JSON_CANONIQUE', 1, $maintenant,
            ]);
            $this->inscrireCycle($reference, 'EMISE', $maintenant, null, (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));
            $etat = 'EMISE';
            if (!$signatureRequise) {
                $this->inscrireCycle($reference, 'ACTIVE', $maintenant, 'empreinte seule, aucune signature requise', (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));
                $etat = 'ACTIVE';
            }

            return ['reference' => $reference, 'algorithme' => $algorithme, 'empreinte_hex' => $empreinteHex, 'etat' => $etat];
        });
    }

    // ------------------------------------------------------------------
    // Signature

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function emettreSignature(string $reference, string $cleReference, UsageSecret $usage, array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($this->signature === null) {
            return $this->refus('FOURNISSEUR_INDISPONIBLE', 'ServiceSignature non configuré');
        }
        $preuve = $this->lignePreuve($reference);
        if ($preuve === null) {
            return $this->refus('PREUVE_INCONNUE', "preuve `{$reference}` inconnue");
        }
        $etat = $this->etatCourant($reference);
        if (!in_array($etat, ['PREPAREE', 'EMISE'], true)) {
            return $this->refus('ETAT_INVALIDE', "signature refusée depuis l'état {$etat}");
        }
        $empreinte = $this->empreintePrincipale($reference);
        if ($empreinte === null) {
            return $this->refus('EMPREINTE_ABSENTE', 'une empreinte principale doit être émise avant la signature');
        }

        $contexte = $this->construireContexteSigne($preuve, $empreinte);
        $empreinteContexte = CalculateurEmpreinte::empreinteChaine($contexte, 'SHA-256');

        try {
            $resultat = $this->signature->signer($cleReference, $contexte, $usage);
        } catch (ExceptionPreuve $e) {
            return $this->refus('SIGNATURE_ECHOUEE', $e->getMessage());
        }

        return $this->transaction(function () use ($reference, $cleReference, $usage, $resultat, $contexte, $empreinteContexte, $dossier): array {
            $maintenant = gmdate('c');
            $referenceSignature = 'SIG-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
            $this->magasin->prepare(
                'INSERT INTO preuve_signature
                 (reference,preuve_reference,algorithme_signature,cle_reference,cle_version_reference,
                  signature_base64url,contexte_signature_version,empreinte_contexte,signee_le,expire_le,
                  fournisseur_reference,resultat_operation_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $referenceSignature, $reference, $resultat['algorithme'], $cleReference, $cleReference,
                $resultat['signature_base64url'], (string) PolitiquePreuves::VERSION_CONTEXTE, $empreinteContexte,
                $maintenant, $this->nullable($dossier['expire_le'] ?? null), $usage->consommateurReference,
                'OP-' . strtoupper(bin2hex(random_bytes(6))), $maintenant,
            ]);
            $this->inscrireCycle($reference, 'EMISE', $maintenant, null, (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

            // Vérification immédiate — n'active jamais avant confirmation cryptographique réelle.
            $clePublique = (string) ($dossier['cle_publique_base64'] ?? '');
            $valide = $clePublique !== '' && $this->signature->verifier($resultat['signature_base64url'], $contexte, $clePublique);
            if (!$valide) {
                return $this->refus('SIGNATURE_NON_VERIFIABLE', 'la signature produite n\'a pas pu être vérifiée immédiatement — préparée, non activée');
            }
            $this->inscrireCycle($reference, 'ACTIVE', $maintenant, 'signature vérifiée immédiatement', (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

            return ['reference' => $reference, 'signature' => $referenceSignature, 'etat' => 'ACTIVE'];
        });
    }

    private function construireContexteSigne(array $preuve, array $empreinte): string
    {
        return Canonicaliseur::canonicaliser([
            'format' => PolitiquePreuves::FORMAT_CONTEXTE,
            'version' => PolitiquePreuves::VERSION_CONTEXTE,
            'preuve_reference' => $preuve['reference'],
            'type_preuve' => $preuve['type_preuve'],
            'sujet_type' => $preuve['sujet_type'],
            'sujet_reference' => $preuve['sujet_reference'],
            'producteur_reference' => (string) ($preuve['producteur_capacite_reference'] ?? $preuve['producteur_produit_reference'] ?? $preuve['producteur_identite_reference'] ?? ''),
            'realm_reference' => $preuve['realm_reference'],
            'finalite_reference' => $preuve['finalite_reference'],
            'source_reference' => $preuve['source_reference'],
            'contrat_reference' => $preuve['contrat_reference'],
            'algorithme_empreinte' => $empreinte['algorithme'],
            'empreinte' => $empreinte['empreinte_hex'],
            'cree_le' => $preuve['cree_le'],
            'expire_le' => null,
        ]);
    }

    // ------------------------------------------------------------------
    // Manifeste

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function emettreManifeste(string $preuveReference, array $membres, array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($this->lignePreuve($preuveReference) === null) {
            return $this->refus('PREUVE_INCONNUE', "preuve `{$preuveReference}` inconnue");
        }
        if ($membres === []) {
            return $this->refus('MANIFESTE_VIDE', 'un manifeste doit comporter au moins un membre');
        }
        if (count($membres) > PolitiquePreuves::MANIFESTE_MEMBRES_MAX) {
            return $this->refus('MANIFESTE_TROP_GRAND', 'nombre de membres au-delà de la limite autorisée');
        }
        $type = (string) ($dossier['type_manifeste'] ?? '');
        if (!in_array($type, PolitiquePreuves::TYPES_MANIFESTE, true)) {
            return $this->refus('TYPE_MANIFESTE_INCONNU', 'type_manifeste hors liste close');
        }
        $chemins = [];
        foreach ($membres as $membre) {
            $chemin = (string) ($membre['chemin_logique'] ?? '');
            $refusChemin = $this->refuserCheminLogique($chemin);
            if ($refusChemin !== null) {
                return $refusChemin;
            }
            if (isset($chemins[$chemin])) {
                return $this->refus('MEMBRE_DUPLIQUE', "chemin dupliqué : {$chemin}");
            }
            $chemins[$chemin] = true;
            if (!CalculateurEmpreinte::empreinteValide((string) ($membre['empreinte'] ?? ''), (string) ($membre['algorithme_empreinte'] ?? 'SHA-256'))) {
                return $this->refus('EMPREINTE_MEMBRE_INVALIDE', "empreinte invalide pour {$chemin}");
            }
        }

        $ordreSignificatif = !empty($dossier['ordre_significatif']);
        $membresTries = $ordreSignificatif ? $membres : $this->trierParChemin($membres);
        $racine = $this->calculerRacineManifeste($membresTries);

        return $this->transaction(function () use ($preuveReference, $membresTries, $type, $ordreSignificatif, $racine, $dossier): array {
            $reference = 'MNF-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
            $maintenant = gmdate('c');
            $tailleTotale = array_sum(array_map(static fn (array $m): int => (int) ($m['taille_octets'] ?? 0), $membresTries));
            $this->magasin->prepare(
                'INSERT INTO manifeste
                 (reference,preuve_reference,nom,type_manifeste,version_format,ordre_significatif,
                  membres_attendus,taille_totale,racine_empreinte,algorithme_racine,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $preuveReference, (string) ($dossier['nom'] ?? $type), $type, '1',
                $ordreSignificatif ? 1 : 0, count($membresTries), $tailleTotale, $racine, 'SHA-256', $maintenant,
            ]);
            $ordre = 0;
            foreach ($membresTries as $membre) {
                $ordre++;
                $this->magasin->prepare(
                    'INSERT INTO manifeste_membre
                     (manifeste_reference,ordre,chemin_logique,sujet_type,sujet_reference,media_type,
                      taille_octets,algorithme_empreinte,empreinte,obligatoire,metadonnees_json,cree_le)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $reference, $ordre, (string) $membre['chemin_logique'],
                    $this->nullable($membre['sujet_type'] ?? null), $this->nullable($membre['sujet_reference'] ?? null),
                    (string) ($membre['media_type'] ?? 'application/octet-stream'), (int) ($membre['taille_octets'] ?? 0),
                    (string) ($membre['algorithme_empreinte'] ?? 'SHA-256'), (string) $membre['empreinte'],
                    array_key_exists('obligatoire', $membre) ? (int) (bool) $membre['obligatoire'] : 1,
                    json_encode($membre['metadonnees'] ?? [], JSON_UNESCAPED_SLASHES), $maintenant,
                ]);
            }
            $this->inscrireCycle($preuveReference, 'EMISE', $maintenant, null, (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));
            if (empty($dossier['signature_requise'])) {
                $this->inscrireCycle($preuveReference, 'ACTIVE', $maintenant, 'manifeste sans signature requise', (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));
            }

            return ['reference' => $reference, 'racine_empreinte' => $racine, 'membres' => count($membresTries)];
        });
    }

    /** @param list<array<string,mixed>> $membres */
    private function calculerRacineManifeste(array $membres): string
    {
        $canonique = Canonicaliseur::canonicaliser(array_map(static fn (array $m): array => [
            'chemin_logique' => $m['chemin_logique'],
            'algorithme_empreinte' => $m['algorithme_empreinte'] ?? 'SHA-256',
            'empreinte' => $m['empreinte'],
            'taille_octets' => (int) ($m['taille_octets'] ?? 0),
        ], $membres));

        return CalculateurEmpreinte::empreinteChaine($canonique, 'SHA-256');
    }

    /** @param list<array<string,mixed>> $membres @return list<array<string,mixed>> */
    private function trierParChemin(array $membres): array
    {
        usort($membres, static fn (array $a, array $b): int => strcmp((string) $a['chemin_logique'], (string) $b['chemin_logique']));

        return $membres;
    }

    private function refuserCheminLogique(string $chemin): ?array
    {
        if ($chemin === '' || $chemin[0] === '/' || str_contains($chemin, '..') || str_contains($chemin, "\0")) {
            return $this->refus('CHEMIN_INVALIDE', "chemin logique refusé : {$chemin}");
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Attestation

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function emettreAttestation(string $preuveReference, array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($this->lignePreuve($preuveReference) === null) {
            return $this->refus('PREUVE_INCONNUE', "preuve `{$preuveReference}` inconnue");
        }
        foreach (['type_attestation', 'declaration', 'version_schema', 'resultat'] as $champ) {
            if (empty($dossier[$champ])) {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $declaration = $dossier['declaration'];
        if (!is_array($declaration)) {
            return $this->refus('DECLARATION_INVALIDE', 'declaration doit être un tableau JSON borné');
        }
        $champsAutorises = (array) ($dossier['champs_autorises'] ?? array_keys($declaration));
        foreach (array_keys($declaration) as $champ) {
            if (!in_array($champ, $champsAutorises, true)) {
                return $this->refus('CHAMP_SUPPLEMENTAIRE', "champ `{$champ}` hors schéma déclaré");
            }
        }

        return $this->transaction(function () use ($preuveReference, $dossier, $declaration): array {
            $reference = 'ATT-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO attestation
                 (reference,preuve_reference,type_attestation,declaration_json,version_schema,resultat,
                  periode_debut,periode_fin,emettrice_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $preuveReference, (string) $dossier['type_attestation'],
                Canonicaliseur::canonicaliser($declaration), (string) $dossier['version_schema'],
                (string) $dossier['resultat'], $this->nullable($dossier['periode_debut'] ?? null),
                $this->nullable($dossier['periode_fin'] ?? null), (string) $dossier['producteur'], $maintenant,
            ]);
            $this->inscrireCycle($preuveReference, 'EMISE', $maintenant, null, (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));
            if (empty($dossier['signature_requise'])) {
                $this->inscrireCycle($preuveReference, 'ACTIVE', $maintenant, 'attestation non critique', (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));
            }

            return ['reference' => $reference];
        });
    }

    // ------------------------------------------------------------------
    // Checkpoint

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function emettreCheckpoint(string $preuveReference, array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($this->lignePreuve($preuveReference) === null) {
            return $this->refus('PREUVE_INCONNUE', "preuve `{$preuveReference}` inconnue");
        }
        $type = (string) ($dossier['type_checkpoint'] ?? '');
        if (!in_array($type, PolitiquePreuves::TYPES_CHECKPOINT, true)) {
            return $this->refus('TYPE_CHECKPOINT_INCONNU', 'type_checkpoint hors liste close');
        }
        if (empty($dossier['tete_empreinte']) || empty($dossier['structure_reference'])) {
            return $this->refus('DOSSIER_INCOMPLET', 'tete_empreinte et structure_reference sont requis');
        }

        return $this->transaction(function () use ($preuveReference, $type, $dossier): array {
            $reference = 'CHK-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO checkpoint_preuve
                 (reference,preuve_reference,type_checkpoint,structure_reference,sequence,tete_empreinte,
                  nombre_elements,instant_observe,metadonnees_json,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $preuveReference, $type, (string) $dossier['structure_reference'],
                isset($dossier['sequence']) ? (int) $dossier['sequence'] : null, (string) $dossier['tete_empreinte'],
                isset($dossier['nombre_elements']) ? (int) $dossier['nombre_elements'] : null,
                $this->nullable($dossier['instant_observe'] ?? null) ?? $maintenant,
                json_encode($dossier['metadonnees'] ?? [], JSON_UNESCAPED_SLASHES), $maintenant,
            ]);
            $this->inscrireCycle($preuveReference, 'EMISE', $maintenant, null, (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));
            if (empty($dossier['signature_requise'])) {
                $this->inscrireCycle($preuveReference, 'ACTIVE', $maintenant, 'checkpoint sans signature requise', (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));
            }

            return ['reference' => $reference];
        });
    }

    // ------------------------------------------------------------------
    // Vérification

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function verifierPreuve(string $reference, array $dossier): array
    {
        $preuve = $this->lignePreuve($reference);
        if ($preuve === null) {
            return $this->refus('PREUVE_INCONNUE', "preuve `{$reference}` inconnue");
        }
        $etat = $this->etatCourant($reference);
        $divergences = [];
        $resultat = 'INDETERMINE';

        if ($etat === 'REVOQUEE') {
            $resultat = 'PREUVE_REVOQUEE';
        } elseif ($etat === 'COMPROMISE') {
            $resultat = 'CLE_COMPROMISE';
        } elseif ($etat === 'EXPIREE') {
            $resultat = 'PREUVE_EXPIREE';
        } elseif (array_key_exists('empreinte_presentee', $dossier)) {
            $empreinte = $this->empreintePrincipale($reference);
            if ($empreinte === null) {
                $resultat = 'ARTEFACT_ABSENT';
            } elseif (!CalculateurEmpreinte::comparerConstant($empreinte['empreinte_hex'], (string) $dossier['empreinte_presentee'])) {
                $resultat = 'EMPREINTE_DIVERGENTE';
                $divergences[] = ['champ' => 'empreinte', 'attendu' => $empreinte['empreinte_hex'], 'observe' => (string) $dossier['empreinte_presentee']];
            } else {
                $resultat = 'VALIDE';
            }
        } elseif (isset($dossier['signature_a_verifier'])) {
            $sig = $dossier['signature_a_verifier'];
            $clePublique = (string) ($dossier['cle_publique_base64'] ?? '');
            $empreinte = $this->empreintePrincipale($reference);
            if ($empreinte === null || $clePublique === '' || $this->signature === null) {
                $resultat = 'CLE_INCONNUE';
            } else {
                $contexte = $this->construireContexteSigne($preuve, $empreinte);
                $resultat = $this->signature->verifier((string) $sig, $contexte, $clePublique) ? 'VALIDE' : 'SIGNATURE_INVALIDE';
            }
        } elseif ($etat === 'ACTIVE') {
            $resultat = 'VALIDE';
        }

        $reference2 = 'VRF-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
        $maintenant = gmdate('c');
        $this->magasin->prepare(
            'INSERT INTO verification_preuve
             (reference,preuve_reference,verificateur_reference,instant_verification,resultat,
              empreinte_presentee,signature_verifiee,cle_version_reference,etat_cle_a_signature,
              etat_cle_aujourdhui,divergences_json,moteur_version,artefact_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference2, $reference, (string) ($dossier['verificateur'] ?? 'anonyme'), $maintenant, $resultat,
            $this->nullable($dossier['empreinte_presentee'] ?? null),
            isset($dossier['signature_a_verifier']) ? ($resultat === 'VALIDE' ? 1 : 0) : null,
            null, null, null, json_encode($divergences, JSON_UNESCAPED_SLASHES), '1',
            $this->nullable($dossier['artefact_reference'] ?? null), $this->nullable($dossier['correlation_id'] ?? null), $maintenant,
        ]);

        return [
            'reference' => $reference2, 'resultat' => $resultat,
            'preuve_utilisable' => $resultat === 'VALIDE' && $etat === 'ACTIVE',
            'divergences' => $divergences,
        ];
    }

    // ------------------------------------------------------------------
    // Cycle

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function suspendrePreuve(string $reference, array $dossier): array
    {
        return $this->transitionner($reference, 'SUSPENDUE', $dossier, ['ACTIVE', 'EMISE']);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function revoquerPreuve(string $reference, array $dossier): array
    {
        return $this->transitionner($reference, 'REVOQUEE', $dossier, ['ACTIVE', 'SUSPENDUE', 'EMISE']);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerCompromission(string $reference, array $dossier): array
    {
        return $this->transitionner($reference, 'COMPROMISE', $dossier, ['ACTIVE', 'SUSPENDUE', 'EMISE', 'REVOQUEE']);
    }

    private function transitionner(string $reference, string $cible, array $dossier, array $etatsAutorises): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($this->lignePreuve($reference) === null) {
            return $this->refus('PREUVE_INCONNUE', "preuve `{$reference}` inconnue");
        }
        $etat = $this->etatCourant($reference);
        if (in_array($etat, PolitiquePreuves::ETATS_TERMINAUX, true)) {
            return $this->refus('PREUVE_COMPROMISE', 'une preuve compromise ne redevient jamais active');
        }
        if (!in_array($etat, $etatsAutorises, true)) {
            return $this->refus('ETAT_INVALIDE', "transition refusée depuis l'état {$etat}");
        }
        if (empty($dossier['motif_code'])) {
            return $this->refus('DOSSIER_INCOMPLET', 'motif_code obligatoire');
        }

        return $this->transaction(function () use ($reference, $cible, $dossier): array {
            $maintenant = gmdate('c');
            $this->inscrireCycle(
                $reference, $cible, $maintenant, (string) $dossier['motif_code'],
                (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'],
                $this->nullable($dossier['correlation_id'] ?? null), $this->nullable($dossier['motif_detail'] ?? null),
            );

            return ['reference' => $reference, 'etat' => $cible];
        });
    }

    // ------------------------------------------------------------------
    // Liens

    /** @return array<string,mixed> */
    public function declarerLien(string $source, string $cible, string $type): array
    {
        if ($source === $cible) {
            return $this->refus('AUTO_LIEN_REFUSE', 'une preuve ne peut pas être liée à elle-même');
        }
        if (!in_array($type, PolitiquePreuves::TYPES_LIEN, true)) {
            return $this->refus('TYPE_LIEN_INCONNU', 'type_lien hors liste close');
        }
        if ($type === 'DERIVE_DE' && $this->creeraitCycle($source, $cible)) {
            return $this->refus('CYCLE_REFUSE', 'ce lien DERIVE_DE créerait un cycle');
        }
        $this->magasin->prepare(
            'INSERT INTO preuve_lien(preuve_source_reference,preuve_cible_reference,type_lien,cree_le) VALUES(?,?,?,?)'
        )->execute([$source, $cible, $type, gmdate('c')]);

        return ['source' => $source, 'cible' => $cible, 'type_lien' => $type];
    }

    private function creeraitCycle(string $source, string $cible, int $profondeur = 0): bool
    {
        if ($profondeur > 50) {
            return true;
        }
        if ($cible === $source) {
            return true;
        }
        $st = $this->magasin->prepare("SELECT preuve_cible_reference FROM preuve_lien WHERE preuve_source_reference = ? AND type_lien = 'DERIVE_DE'");
        $st->execute([$cible]);
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $suivant) {
            if ($this->creeraitCycle($source, (string) $suivant, $profondeur + 1)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Paquet exportable

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function exporterPaquetPreuve(string $reference, string $profil, array $dossier): array
    {
        if ($this->lignePreuve($reference) === null) {
            return $this->refus('PREUVE_INCONNUE', "preuve `{$reference}` inconnue");
        }
        if (!in_array($profil, PolitiquePreuves::PROFILS_EXPORT, true)) {
            return $this->refus('PROFIL_INCONNU', 'profil hors liste close');
        }
        $contenu = $this->construirePaquet($reference, $profil);
        $paquetJson = Canonicaliseur::canonicaliser($contenu);
        if (strlen($paquetJson) > PolitiquePreuves::PAQUET_MAX_OCTETS) {
            return $this->refus('PAQUET_TROP_VOLUMINEUX', 'le paquet dépasse la taille maximale autorisée');
        }
        $empreintePaquet = CalculateurEmpreinte::empreinteChaine($paquetJson, 'SHA-256');
        $referencePaquet = 'PKG-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
        $this->magasin->prepare(
            'INSERT INTO paquet_preuve
             (reference,preuve_reference,format_paquet,version_format,empreinte_paquet,taille_octets,
              classification,expire_le,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?)'
        )->execute([
            $referencePaquet, $reference, 'gamad-proof-package', '1', $empreintePaquet, strlen($paquetJson),
            (string) ($dossier['classification'] ?? 'INTERNE'), $this->nullable($dossier['expire_le'] ?? null), gmdate('c'),
        ]);

        return ['reference' => $referencePaquet, 'empreinte_paquet' => $empreintePaquet, 'contenu' => $contenu];
    }

    /** @return array<string,mixed> */
    private function construirePaquet(string $reference, string $profil): array
    {
        $preuve = $this->lignePreuve($reference);
        $empreintes = $this->resoudreEmpreintes($reference);
        $signatures = $this->resoudreSignatures($reference);

        return [
            'format' => 'gamad-proof-package', 'version' => 1, 'profil' => $profil,
            'preuve_reference' => $reference, 'type_preuve' => $preuve['type_preuve'] ?? null,
            'empreintes' => array_map(static fn (array $e): array => ['algorithme' => $e['algorithme'], 'empreinte_hex' => $e['empreinte_hex']], $empreintes),
            'signatures' => array_map(static fn (array $s): array => [
                'algorithme' => $s['algorithme_signature'], 'cle_reference' => $s['cle_reference'],
                'signature_base64url' => $s['signature_base64url'],
            ], $signatures),
            'etat' => $this->etatCourant($reference),
        ];
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudrePreuve(string $reference): ?array
    {
        $preuve = $this->lignePreuve($reference);
        if ($preuve === null) {
            return null;
        }
        $preuve['etat'] = $this->etatCourant($reference);

        return $preuve;
    }

    /** @param array<string,mixed> $filtres @return list<array<string,mixed>> */
    public function listerPreuves(array $filtres = []): array
    {
        $conditions = [];
        $valeurs = [];
        if (isset($filtres['type_preuve'])) {
            $conditions[] = 'type_preuve = ?';
            $valeurs[] = $filtres['type_preuve'];
        }
        if (isset($filtres['realm_reference'])) {
            $conditions[] = 'realm_reference = ?';
            $valeurs[] = $filtres['realm_reference'];
        }
        $sql = 'SELECT * FROM preuve';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY cree_le DESC';
        $st = $this->magasin->prepare($sql);
        $st->execute($valeurs);

        return $st->fetchAll();
    }

    public function resoudreEtat(string $reference): ?string
    {
        return $this->etatCourant($reference);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreEmpreintes(string $reference): array
    {
        $st = $this->magasin->prepare('SELECT * FROM preuve_empreinte WHERE preuve_reference = ? ORDER BY id');
        $st->execute([$reference]);

        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function resoudreSignatures(string $reference): array
    {
        $st = $this->magasin->prepare('SELECT * FROM preuve_signature WHERE preuve_reference = ? ORDER BY cree_le');
        $st->execute([$reference]);

        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function resoudreManifeste(string $preuveReference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM manifeste WHERE preuve_reference = ?');
        $st->execute([$preuveReference]);
        $manifeste = $st->fetch();
        if ($manifeste === false) {
            return null;
        }
        $stMembres = $this->magasin->prepare('SELECT * FROM manifeste_membre WHERE manifeste_reference = ? ORDER BY ordre');
        $stMembres->execute([$manifeste['reference']]);
        $manifeste['membres'] = $stMembres->fetchAll();

        return $manifeste;
    }

    /** @return array<string,mixed>|null */
    public function resoudreAttestation(string $preuveReference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM attestation WHERE preuve_reference = ?');
        $st->execute([$preuveReference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return array<string,mixed>|null */
    public function resoudreCheckpoint(string $preuveReference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM checkpoint_preuve WHERE preuve_reference = ?');
        $st->execute([$preuveReference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return list<array<string,mixed>> */
    public function resoudreVerifications(string $reference): array
    {
        $st = $this->magasin->prepare('SELECT * FROM verification_preuve WHERE preuve_reference = ? ORDER BY cree_le');
        $st->execute([$reference]);

        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function resoudreLiens(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM preuve_lien WHERE preuve_source_reference = ? OR preuve_cible_reference = ? ORDER BY id'
        );
        $st->execute([$reference, $reference]);

        return $st->fetchAll();
    }

    /** @return array<string,mixed> */
    public function diagnostiquerRegistre(): array
    {
        $totalPreuves = (int) $this->magasin->query('SELECT count(*) FROM preuve')->fetchColumn();
        $actives = (int) $this->magasin->query(
            "SELECT count(*) FROM preuve_cycle c1 WHERE c1.etat = 'ACTIVE' AND c1.id = (
                SELECT MAX(c2.id) FROM preuve_cycle c2 WHERE c2.preuve_reference = c1.preuve_reference
            )"
        )->fetchColumn();
        $compromises = (int) $this->magasin->query(
            "SELECT count(*) FROM preuve_cycle c1 WHERE c1.etat = 'COMPROMISE' AND c1.id = (
                SELECT MAX(c2.id) FROM preuve_cycle c2 WHERE c2.preuve_reference = c1.preuve_reference
            )"
        )->fetchColumn();
        $preparees = (int) $this->magasin->query(
            "SELECT count(*) FROM preuve_cycle c1 WHERE c1.etat = 'PREPAREE' AND c1.id = (
                SELECT MAX(c2.id) FROM preuve_cycle c2 WHERE c2.preuve_reference = c1.preuve_reference
            )"
        )->fetchColumn();

        return [
            'preuves' => $totalPreuves, 'actives' => $actives, 'compromises' => $compromises,
            'preparees_bloquees' => $preparees, 'sodium_disponible' => ServiceSignature::sodiumDisponible(),
        ];
    }

    // ------------------------------------------------------------------
    // Internes

    /** @return array<string,mixed>|null */
    private function lignePreuve(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM preuve WHERE reference = ?');
        $st->execute([$reference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return array<string,mixed>|null */
    private function lignePreuveParIdempotence(string $producteur, string $type, string $idempotencyKey): ?array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM preuve WHERE cree_par_reference = ? AND type_preuve = ? AND idempotency_key = ?'
        );
        $st->execute([$producteur, $type, $idempotencyKey]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return array<string,mixed>|null */
    private function empreintePrincipale(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM preuve_empreinte WHERE preuve_reference = ? AND est_principale = 1 LIMIT 1');
        $st->execute([$reference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    private function etatCourant(string $reference): ?string
    {
        $st = $this->magasin->prepare('SELECT etat FROM preuve_cycle WHERE preuve_reference = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$reference]);
        $etat = $st->fetchColumn();

        return $etat === false ? null : (string) $etat;
    }

    private function inscrireCycle(
        string $preuveReference,
        string $etat,
        string $dateEffet,
        ?string $motifCode,
        string $acteur,
        string $politique,
        string $preuveAutorisation,
        ?string $correlation,
        ?string $motifDetail = null,
    ): void {
        $this->magasin->prepare(
            'INSERT INTO preuve_cycle
             (preuve_reference,etat,date_effet,motif_code,motif_detail,acteur_reference,politique_reference,
              preuve_autorisation_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?)'
        )->execute([$preuveReference, $etat, $dateEffet, $motifCode, $motifDetail, $acteur, $politique, $preuveAutorisation, $correlation, gmdate('c')]);
    }

    private function controlerGouvernance(array $dossier): array
    {
        foreach (['politique', 'producteur', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('COMMANDE_NON_GOUVERNEE', "champ `{$champ}` absent");
            }
        }

        return ['valide' => true];
    }

    private function refuserChampsInterdits(array $dossier): array
    {
        foreach (array_keys($dossier) as $cle) {
            if (in_array(strtolower((string) $cle), PolitiquePreuves::CHAMPS_INTERDITS, true)) {
                return $this->refus('CHAMP_INTERDIT', "le champ `{$cle}` ne peut jamais être transmis à ce registre");
            }
        }

        return ['valide' => true];
    }

    private function nullable(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $chaine = trim((string) $valeur);

        return $chaine === '' ? null : $chaine;
    }

    /** @return array<string,mixed> */
    private function refus(string $motif, string $detail): array
    {
        return ['refus' => $motif, 'detail' => $detail];
    }

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    private function transaction(callable $operation): mixed
    {
        $propre = !$this->magasin->inTransaction();
        if ($propre) {
            $this->magasin->beginTransaction();
        }
        try {
            $resultat = $operation();
            if ($propre) {
                $this->magasin->commit();
            }

            return $resultat;
        } catch (\Throwable $e) {
            if ($propre && $this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }
    }
}
