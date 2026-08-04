<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

/**
 * Bornes et vocabulaire fermé de CAP-CORE-014.
 *
 * Reprend la classification canonique déjà établie par CAP-CORE-001
 * (`PolitiqueInscription::CLASSIFICATIONS`) plutôt que d'en inventer une
 * nouvelle. Une intégration dynamique avec CAP-CORE-010 (vocabulaire
 * canonique) reste un chantier ultérieur non bloquant : les listes ci-dessous
 * sont volontairement fermées et vérifiées en code, jamais dérivées d'un
 * texte libre.
 */
final class PolitiqueEvenements
{
    public const POLITIQUE = 'POL-EVENEMENTS-V1';
    public const SOURCE = 'CAP-CORE-014 — journal des événements';
    public const CAPACITE = 'CAP-CORE-014';
    public const AUTORITE = 'AUT-GAMAD-001';

    /** Actions minimales de la politique d'administration (fiche partie 4 §13). */
    public const ACTION_PUBLIER = 'evenement.publier';
    public const ACTION_LIRE = 'evenement.lire';
    public const ACTION_ABONNEMENT_CREER = 'evenement.abonnement.creer';
    public const ACTION_ABONNEMENT_MODIFIER = 'evenement.abonnement.modifier';
    public const ACTION_ABONNEMENT_ACTIVER = 'evenement.abonnement.activer';
    public const ACTION_ABONNEMENT_SUSPENDRE = 'evenement.abonnement.suspendre';
    public const ACTION_ABONNEMENT_RETIRER = 'evenement.abonnement.retirer';
    public const ACTION_LIVRAISON_ACCUSER = 'evenement.livraison.accuser';
    public const ACTION_LIVRAISON_REFUSER = 'evenement.livraison.refuser';
    public const ACTION_REJEU_DEMANDER = 'evenement.rejeu.demander';
    public const ACTION_LETTRE_MORTE_RELANCER = 'evenement.lettre-morte.relancer';
    public const ACTION_DIAGNOSTIC_LIRE = 'evenement.diagnostic.lire';

    public const CLASSIFICATIONS = [
        'PUBLIC_ECOSYSTEME', 'INTERNE', 'CONFIDENTIEL', 'RESTREINT', 'SECRET_CORE',
    ];

    public const TYPES_PARTIE = ['CAPACITE', 'PRODUIT'];

    public const ETATS_OUTBOX = ['EN_ATTENTE', 'EN_COURS', 'PUBLIE', 'ECHEC_TEMPORAIRE', 'ECHEC_DEFINITIF'];

    public const ETATS_ABONNEMENT = ['PREPARATION', 'ACTIF', 'SUSPENDU', 'RETIRE'];

    public const MODES_LIVRAISON = ['PULL_API', 'PUSH_HTTPS'];

    public const PORTEES_REALM = ['EXACT', 'DESCENDANTS_EXPLICITES'];

    public const ETATS_LIVRAISON = [
        'DISPONIBLE', 'SOUS_BAIL', 'ACCUSE', 'A_REESSAYER', 'LETTRE_MORTE', 'ANNULE',
    ];

    public const RESULTATS_TENTATIVE = [
        'MISE_A_DISPOSITION', 'BAIL_ACCORDE', 'ACCUSE', 'REFUS_TEMPORAIRE',
        'REFUS_DEFINITIF', 'BAIL_EXPIRE', 'ERREUR_TRANSPORT', 'RELANCE',
    ];

    public const ETATS_REJEU = ['DEMANDEE', 'VALIDEE', 'EN_COURS', 'TERMINEE', 'REFUSEE', 'ANNULEE'];

    /** Codes d'erreur canoniques distinguant les causes de refus (partie 3 §10). */
    public const CODES_ERREUR_RETENTABLES = [
        'DEPENDANCE_INDISPONIBLE', 'ERREUR_INTERNE_CONSOMMATEUR',
    ];
    public const CODES_ERREUR_DEFINITIFS = [
        'CONTRAT_INCONNU', 'VERSION_INCOMPATIBLE', 'CHARGE_INVALIDE', 'ERREUR_METIER_DEFINITIVE',
    ];

    // Bornes — configurables via variables d'environnement dans des limites sûres,
    // jamais désactivables par une valeur arbitraire (partie 4 §11).
    public const TAILLE_CHARGE_MAX_OCTETS = 32_768;
    public const TAILLE_LOT_MAX = 500;
    public const TYPES_MAX_PAR_ABONNEMENT = 50;
    public const REALMS_MAX_PAR_ABONNEMENT = 50;
    public const PRODUCTEURS_MAX_PAR_ABONNEMENT = 50;
    public const BAIL_SECONDES_MAX = 3600;
    public const BAIL_SECONDES_DEFAUT = 300;
    public const TENTATIVES_MAX_DEFAUT = 8;
    public const TENTATIVES_MAX_PLAFOND = 25;
    public const REJEU_VOLUME_MAX = 10_000;

    /** Fragments de nom interdits dans une charge (partie 4 §11). */
    public const FRAGMENTS_CHAMPS_INTERDITS = [
        'password', 'mot_de_passe', 'secret', 'authorization', 'cookie', 'session',
        'token', 'jeton', 'private_key', 'cle_privee', 'passkey', 'passkey_challenge',
        'code_secours', 'recovery_code', 'card_number', 'numero_carte', 'cvv',
        'dossier_medical', 'webauthn',
    ];
}
