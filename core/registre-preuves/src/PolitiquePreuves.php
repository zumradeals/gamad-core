<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

/**
 * Bornes et vocabulaire fermé de CAP-CORE-015 — même principe que
 * `PolitiqueSecretsCles` (CAP-CORE-016) : listes fermées vérifiées en code,
 * intégration dynamique avec CAP-CORE-010 laissée à un chantier ultérieur
 * non bloquant.
 */
final class PolitiquePreuves
{
    public const POLITIQUE = 'POL-PREUVES-V1';
    public const SOURCE = 'CAP-CORE-015 — registre des preuves d\'intégrité';
    public const CAPACITE = 'CAP-CORE-015';
    public const AUTORITE = 'AUT-GAMAD-001';

    public const ACTION_LIRE = 'preuve.lire';
    public const ACTION_PREPARER = 'preuve.preparer';
    public const ACTION_EMPREINTE_EMETTRE = 'preuve.empreinte.emettre';
    public const ACTION_SIGNATURE_EMETTRE = 'preuve.signature.emettre';
    public const ACTION_ATTESTATION_EMETTRE = 'preuve.attestation.emettre';
    public const ACTION_MANIFESTE_EMETTRE = 'preuve.manifeste.emettre';
    public const ACTION_CHECKPOINT_EMETTRE = 'preuve.checkpoint.emettre';
    public const ACTION_VERIFIER = 'preuve.verifier';
    public const ACTION_LOT_VERIFIER = 'preuve.lot.verifier';
    public const ACTION_REVOQUER = 'preuve.revoquer';
    public const ACTION_SUSPENDRE = 'preuve.suspendre';
    public const ACTION_COMPROMISSION_DECLARER = 'preuve.compromission.declarer';
    public const ACTION_PAQUET_EXPORTER = 'preuve.paquet.exporter';
    public const ACTION_DIAGNOSTIC_LIRE = 'preuve.diagnostic.lire';

    public const TYPES_PREUVE = [
        'EMPREINTE_ARTEFACT', 'SIGNATURE_ARTEFACT', 'ATTESTATION', 'MANIFESTE',
        'CHECKPOINT', 'PREUVE_CONFORMITE', 'PREUVE_RESTAURATION', 'PREUVE_EVENEMENT', 'PAQUET_PREUVE',
    ];

    public const FORMATS_REPRESENTATION = [
        'OCTETS_BRUTS', 'JSON_CANONIQUE', 'TEXTE_UTF8_NORMALISE',
        'MANIFESTE_CANONIQUE', 'CHECKPOINT_CANONIQUE', 'DECLARATION_CANONIQUE',
    ];

    /** SHA-256 reste obligatoire pour compatibilité ; SHA-512 disponible. MD5/SHA-1 explicitement refusés. */
    public const ALGORITHMES_EMPREINTE_AUTORISES = ['SHA-256', 'SHA-512'];
    public const ALGORITHMES_EMPREINTE_REFUSES = ['MD5', 'SHA-1'];

    public const ALGORITHMES_SIGNATURE_AUTORISES = ['ED25519'];

    public const ETATS_CYCLE = [
        'PREPAREE', 'EMISE', 'ACTIVE', 'EXPIREE', 'SUSPENDUE', 'REVOQUEE', 'COMPROMISE', 'ARCHIVEE',
    ];

    public const ETATS_TERMINAUX = ['COMPROMISE'];

    public const TYPES_MANIFESTE = [
        'SAUVEGARDE', 'RESTAURATION', 'VERSION_REGISTRE', 'PROJECTION_CONTRAT',
        'LOT_EVENEMENTS', 'PAQUET_EXPORT', 'ARTEFACTS_CI',
    ];

    public const TYPES_CHECKPOINT = [
        'JOURNAL_AUDIT', 'JOURNAL_EVENEMENTS', 'REGISTRE', 'SAUVEGARDE', 'RESTAURATION',
    ];

    public const RESULTATS_VERIFICATION = [
        'VALIDE', 'INVALIDE', 'INDETERMINE', 'ARTEFACT_ABSENT', 'EMPREINTE_DIVERGENTE',
        'SIGNATURE_INVALIDE', 'CLE_INCONNUE', 'CLE_NON_AUTORISEE', 'CLE_COMPROMISE',
        'PREUVE_REVOQUEE', 'PREUVE_EXPIREE', 'CONTRAT_INACTIF', 'ALGORITHME_NON_SUPPORTE',
    ];

    public const TYPES_LIEN = [
        'DERIVE_DE', 'REMPLACE', 'CONFIRME', 'CONTREDIT', 'COMPOSE', 'CHECKPOINT_DE', 'RESTAURE_DEPUIS',
    ];

    public const CLASSIFICATIONS = [
        'PUBLIC_ECOSYSTEME', 'INTERNE', 'CONFIDENTIEL', 'RESTREINT', 'SECRET_CORE',
    ];

    public const PROFILS_EXPORT = [
        'VERIFICATION_INTERNE', 'VERIFICATION_SATELLITE', 'PREUVE_SAUVEGARDE',
        'PREUVE_RESTAURATION', 'CONFORMITE_CONTRAT',
    ];

    /** Contexte signé canonique — version de format explicite (fiche partie 2 §7). */
    public const FORMAT_CONTEXTE = 'gamad-integrity-proof';
    public const VERSION_CONTEXTE = 1;
    public const VERSION_CANONICALISATION = 1;

    public const CONTENU_INLINE_MAX_OCTETS = 262_144;
    public const MANIFESTE_MEMBRES_MAX = 1000;
    public const PAQUET_MAX_OCTETS = 10_485_760;

    /** Même garde que CAP-CORE-016 : aucun champ de valeur secrète ne peut jamais être écrit ici. */
    public const CHAMPS_INTERDITS = [
        'value', 'secret', 'private_key', 'password', 'passphrase', 'token', 'credential_content',
        'cle_privee', 'mot_de_passe', 'phrase_secrete', 'jeton', 'valeur',
    ];
}
