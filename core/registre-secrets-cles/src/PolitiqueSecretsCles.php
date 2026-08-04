<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Bornes et vocabulaire fermé de CAP-CORE-016.
 *
 * Une intégration dynamique avec CAP-CORE-010 (vocabulaire canonique) reste
 * un chantier ultérieur non bloquant : les listes ci-dessous sont
 * volontairement fermées et vérifiées en code, jamais dérivées d'un texte
 * libre — même principe que `PolitiqueEvenements` (CAP-CORE-014).
 */
final class PolitiqueSecretsCles
{
    public const POLITIQUE = 'POL-SECRETS-CLES-V1';
    public const SOURCE = 'CAP-CORE-016 — registre des secrets et clés';
    public const CAPACITE = 'CAP-CORE-016';
    public const AUTORITE = 'AUT-GAMAD-001';

    public const ACTION_LIRE_METADONNEES = 'secret.lire-metadonnees';
    public const ACTION_INSCRIRE = 'secret.inscrire';
    public const ACTION_FOURNISSEUR_INSCRIRE = 'secret.fournisseur.inscrire';
    public const ACTION_FOURNISSEUR_VERIFIER = 'secret.fournisseur.verifier';
    public const ACTION_VERSION_DECLARER = 'secret.version.declarer';
    public const ACTION_VERSION_VERIFIER = 'secret.version.verifier';
    public const ACTION_VERSION_ACTIVER = 'secret.version.activer';
    public const ACTION_USAGE_DECLARER = 'secret.usage.declarer';
    public const ACTION_ROTATION_PLANIFIER = 'secret.rotation.planifier';
    public const ACTION_ROTATION_VALIDER = 'secret.rotation.valider';
    public const ACTION_ROTATION_EXECUTER = 'secret.rotation.executer';
    public const ACTION_VERSION_SUSPENDRE = 'secret.version.suspendre';
    public const ACTION_VERSION_REVOQUER = 'secret.version.revoquer';
    public const ACTION_VERSION_COMPROMETTRE = 'secret.version.compromettre';
    public const ACTION_VERSION_DETRUIRE = 'secret.version.detruire';
    public const ACTION_MATERIEL_PUBLIC_EXPORTER = 'secret.materiel-public.exporter';
    public const ACTION_DIAGNOSTIC_LIRE = 'secret.diagnostic.lire';

    public const TYPES_SECRET = [
        'CLE_CHIFFREMENT_SYMETRIQUE', 'PAIRE_CLES_SIGNATURE', 'PAIRE_CLES_CHIFFREMENT',
        'CLE_HMAC', 'SECRET_API', 'IDENTIFIANT_CONNEXION', 'MOT_DE_PASSE_SERVICE',
        'CLE_SSH', 'CLE_GPG', 'PHRASE_SECRETE', 'CLE_APPLICATION', 'CERTIFICAT_TLS',
    ];

    public const TYPES_FOURNISSEUR = [
        'FICHIER_0600', 'CREDENTIAL_SYSTEMD', 'VARIABLE_ENVIRONNEMENT_TRANSITION',
        'TROUSSEAU_GPG', 'AGENT_SSH', 'FOURNISSEUR_EXTERNE',
    ];

    public const CAPACITES_FOURNISSEUR = [
        'LIRE', 'GENERER', 'ROTATION', 'DETRUIRE', 'SIGNER_SANS_EXPORT', 'DECHIFFRER_SANS_EXPORT',
    ];

    public const ETATS_FOURNISSEUR = ['PREPARATION', 'ACTIF', 'DEGRADE', 'SUSPENDU', 'RETIRE'];

    public const ETATS_VERSION = [
        'PREPARATION', 'ACTIVE_ECRITURE', 'ACTIVE_LECTURE', 'DEPRECIEE',
        'SUSPENDUE', 'REVOQUEE', 'COMPROMISE', 'DETRUITE',
    ];

    /** États qui ne peuvent plus jamais redevenir actifs (partie 2 §5). */
    public const ETATS_TERMINAUX = ['COMPROMISE', 'DETRUITE'];

    public const MODES_USAGE = [
        'LIRE_SECRET', 'CONNECTER', 'CHIFFRER', 'DECHIFFRER', 'SIGNER',
        'VERIFIER', 'CALCULER_HMAC', 'AUTHENTIFIER', 'TRANSPORTER',
    ];

    /** Modes d'usage qui exigent une version ACTIVE_ECRITURE (partie 2 §15). */
    public const MODES_ECRITURE = ['CHIFFRER', 'SIGNER', 'CONNECTER', 'CALCULER_HMAC', 'AUTHENTIFIER', 'TRANSPORTER'];

    public const TYPES_DEPENDANCE = [
        'DONNEE_CHIFFREE', 'SAUVEGARDE', 'SESSION', 'SIGNATURE', 'JETON', 'CONNEXION', 'CERTIFICAT', 'ARTEFACT_EXTERNE',
    ];

    public const STRATEGIES_ROTATION = [
        'DOUBLE_LECTURE_ECRITURE_NOUVELLE', 'DOUBLE_IDENTIFIANT', 'BASCULE_ATOMIQUE',
        'ROTATION_FOURNISSEUR', 'RECHIFFREMENT_PROGRESSIF', 'RENOUVELLEMENT_CERTIFICAT',
    ];

    public const ETATS_ROTATION_PLAN = ['BROUILLON', 'EN_VALIDATION', 'VALIDE', 'EN_COURS', 'REUSSI', 'ECHEC', 'ANNULE'];

    public const ETATS_ROTATION_EXECUTION = ['EN_COURS', 'REUSSIE', 'ECHOUEE'];

    public const NIVEAUX_COMPROMISSION = ['SUSPECTEE', 'PROBABLE', 'CONFIRMEE'];

    public const ETATS_COMPROMISSION = ['OUVERTE', 'CONTENUE', 'ROTATION_EN_COURS', 'CLOTUREE'];

    public const TYPES_MATERIEL_PUBLIC = ['CLE_PUBLIQUE', 'CERTIFICAT', 'CHAINE_CERTIFICATS', 'EMPREINTE', 'IDENTIFIANT_CLE'];

    public const CLASSIFICATIONS = [
        'PUBLIC_ECOSYSTEME', 'INTERNE', 'CONFIDENTIEL', 'RESTREINT', 'SECRET_CORE',
    ];

    public const ENVIRONNEMENTS = ['PRODUCTION', 'STAGING', 'DEVELOPPEMENT', 'CI'];

    /**
     * Garde absolue (fiche partie 4 §5) : un dossier, une charge de résumé ou
     * un événement contenant l'une de ces clés est refusé avant toute
     * écriture, quelle que soit la commande. Empêche l'ajout futur d'un champ
     * de valeur par erreur, pas seulement l'usage initial.
     */
    public const CHAMPS_INTERDITS = [
        'value', 'secret', 'private_key', 'password', 'passphrase', 'token', 'credential_content',
        'cle_privee', 'mot_de_passe', 'phrase_secrete', 'jeton', 'valeur',
    ];

    public const CODES_ERREUR = [
        'SECRET_INCONNU', 'VERSION_INCONNUE', 'VERSION_INACTIVE', 'USAGE_REFUSE',
        'REALM_REFUSE', 'ENVIRONNEMENT_REFUSE', 'FOURNISSEUR_INDISPONIBLE',
        'FOURNISSEUR_NON_CONFORME', 'ROTATION_REQUISE', 'DEPENDANCE_BLOQUANTE',
        'VERSION_COMPROMISE', 'DESTRUCTION_REFUSEE',
    ];
}
