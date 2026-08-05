<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Bornes et vocabulaire fermé de CAP-CORE-021 — même principe que
 * `PolitiquePreuves` (CAP-CORE-015) et `PolitiqueSecretsCles` (CAP-CORE-016) :
 * listes fermées vérifiées en code, intégration dynamique avec CAP-CORE-010
 * laissée à un chantier ultérieur non bloquant (précédent déjà accepté pour
 * ces deux capacités).
 */
final class PolitiqueMatching
{
    public const POLITIQUE = 'POL-MATCHING-V1';
    public const SOURCE = 'CAP-CORE-021 — moteur de Matching';
    public const CAPACITE = 'CAP-CORE-021';
    public const AUTORITE = 'AUT-GAMAD-001';

    public const ACTION_CONTEXTES_CONSULTER = 'consulter-contextes-matching';
    public const ACTION_POLITIQUES_CONSULTER = 'consulter-politiques-matching';
    public const ACTION_POLITIQUE_COMPILER = 'compiler-politique-matching';
    public const ACTION_POLITIQUE_SIMULER = 'simuler-politique-matching';
    public const ACTION_POLITIQUES_COMPARER = 'comparer-politiques-matching';
    public const ACTION_DEMANDE_SOUMETTRE = 'soumettre-demande-matching';
    public const ACTION_DEMANDE_CONSULTER = 'consulter-demande-matching';
    public const ACTION_DEMANDE_ANNULER = 'annuler-demande-matching';
    public const ACTION_MATCHING_EXECUTER = 'executer-matching';
    public const ACTION_RESULTAT_CONSULTER = 'consulter-resultat-matching';
    public const ACTION_RESULTAT_EXPLIQUER = 'expliquer-resultat-matching';
    public const ACTION_SEGMENT_CONSTRUIRE = 'construire-segment-matching';
    public const ACTION_SEGMENT_CONSULTER = 'consulter-segment-matching';
    public const ACTION_SEGMENT_APPARTENANCE_VERIFIER = 'verifier-appartenance-segment';
    public const ACTION_SEGMENT_ACTIVER = 'activer-segment-matching';
    public const ACTION_SEGMENT_SUSPENDRE = 'suspendre-segment-matching';
    public const ACTION_SEGMENT_REVOQUER = 'revoquer-segment-matching';
    public const ACTION_ACTIVATION_TERMINER = 'terminer-activation-matching';
    public const ACTION_MESURE_ENREGISTRER = 'enregistrer-mesure-matching';
    public const ACTION_CONTESTATION_OUVRIR = 'ouvrir-contestation-matching';
    public const ACTION_CONTESTATION_INSTRUIRE = 'instruire-contestation-matching';
    public const ACTION_RESULTAT_REEXAMINER = 'reexaminer-resultat-matching';
    public const ACTION_PAQUET_EXPORTER = 'exporter-paquet-matching';
    public const ACTION_PAQUET_VERIFIER = 'verifier-paquet-matching';
    public const ACTION_DIAGNOSTICS_CONSULTER = 'consulter-diagnostics-matching';
    public const ACTION_RAPPORT_QUALITE_CONSULTER = 'consulter-rapport-qualite-matching';
    public const ACTION_RAPPORT_EQUITE_CONSULTER = 'consulter-rapport-equite-matching';
    public const ACTION_SIGNAUX_GERER = 'gerer-signaux-matching';
    public const ACTION_JEUX_EVALUATION_GERER = 'gerer-jeux-evaluation-matching';

    public const ACTIONS = [
        self::ACTION_CONTEXTES_CONSULTER, self::ACTION_POLITIQUES_CONSULTER,
        self::ACTION_POLITIQUE_COMPILER, self::ACTION_POLITIQUE_SIMULER, self::ACTION_POLITIQUES_COMPARER,
        self::ACTION_DEMANDE_SOUMETTRE, self::ACTION_DEMANDE_CONSULTER, self::ACTION_DEMANDE_ANNULER,
        self::ACTION_MATCHING_EXECUTER, self::ACTION_RESULTAT_CONSULTER, self::ACTION_RESULTAT_EXPLIQUER,
        self::ACTION_SEGMENT_CONSTRUIRE, self::ACTION_SEGMENT_CONSULTER, self::ACTION_SEGMENT_APPARTENANCE_VERIFIER,
        self::ACTION_SEGMENT_ACTIVER, self::ACTION_SEGMENT_SUSPENDRE, self::ACTION_SEGMENT_REVOQUER,
        self::ACTION_ACTIVATION_TERMINER, self::ACTION_MESURE_ENREGISTRER,
        self::ACTION_CONTESTATION_OUVRIR, self::ACTION_CONTESTATION_INSTRUIRE, self::ACTION_RESULTAT_REEXAMINER,
        self::ACTION_PAQUET_EXPORTER, self::ACTION_PAQUET_VERIFIER,
        self::ACTION_DIAGNOSTICS_CONSULTER, self::ACTION_RAPPORT_QUALITE_CONSULTER, self::ACTION_RAPPORT_EQUITE_CONSULTER,
        self::ACTION_SIGNAUX_GERER, self::ACTION_JEUX_EVALUATION_GERER,
    ];

    /** Liste fermée initiale des contextes (doc 01 §10). Toute activation reste bornée par POL-MATCHING-V1. */
    public const CONTEXTES_INITIAUX = [
        'WASPLEX_AUDIENCE', 'B2B_OPPORTUNITY', 'SERVICE_ORIENTATION',
        'INSTITUTIONAL_ELIGIBILITY', 'RESOURCE_RECOMMENDATION',
    ];

    /** Opérateurs déterministes fermés (doc 03 §5) : aucune expression SQL ou regex libre. */
    public const OPERATEURS = [
        'EQ', 'NEQ', 'IN', 'NOT_IN', 'GT', 'GTE', 'LT', 'LTE', 'BETWEEN',
        'CONTAINS_CANONICAL', 'INTERSECTS_CANONICAL', 'WITHIN_REALM',
        'VALID_AT', 'EXISTS_VERIFIED', 'NOT_EXISTS_VERIFIED',
    ];

    public const TRAITEMENTS_INCONNU = [
        'INDETERMINE', 'IGNORER_AVEC_TRACE', 'ECHEC_CRITERE', 'REFUS_EXECUTION',
    ];

    public const ETATS_DEMANDE = [
        'PREPARATION', 'SOUMISE', 'VALIDEE', 'REFUSEE', 'EN_ATTENTE_SOURCES',
        'EN_EXECUTION', 'TERMINEE', 'PARTIELLE', 'EN_ECHEC', 'ANNULEE', 'EXPIREE',
    ];

    public const ETATS_DEMANDE_TERMINAUX = ['TERMINEE', 'PARTIELLE', 'EN_ECHEC', 'ANNULEE', 'EXPIREE', 'REFUSEE'];

    public const MODES_RESULTAT = [
        'QUALIFICATION_UNITAIRE', 'CORRESPONDANCE', 'CLASSEMENT',
        'ESTIMATION_AGREGEE', 'SEGMENT_PROTEGE', 'APPARTENANCE_SEGMENT', 'COMPARAISON_POLITIQUE',
    ];

    public const ROLES_OBJET = ['OFFRE', 'BESOIN', 'ENTITE', 'PROGRAMME', 'RESSOURCE', 'CANDIDAT'];

    public const ORIGINES_CRITERE = [
        'POLITIQUE', 'CONSOMMATEUR_AUTORISE', 'OBJET_SOURCE', 'OBLIGATION_REALM', 'RESTRICTION_RISQUE',
    ];

    public const STATUTS_SIGNAL = [
        'VALIDE', 'PERIME', 'REVOQUE', 'CONTRADICTOIRE', 'INUTILISABLE', 'SUPPRIME_LOGIQUEMENT',
    ];

    public const ETATS_EXECUTION = ['PLANIFIEE', 'EN_COURS', 'TERMINEE', 'PARTIELLE', 'EN_ECHEC', 'ANNULEE'];

    public const ETATS_EVALUATION_CRITERE = [
        'SATISFAIT', 'DEFAVORABLE', 'NON_ETABLI', 'CONTRADICTOIRE', 'INTERDIT', 'NON_APPLICABLE',
    ];

    public const CLASSES_RESULTAT = [
        'CORRESPONDANCE_FORTE', 'CORRESPONDANCE_PROBABLE', 'CORRESPONDANCE_PARTIELLE',
        'NON_CORRESPONDANT', 'INDETERMINE', 'INTERDIT', 'POPULATION_TROP_REDUITE',
    ];

    public const NATURES_FACTEUR = ['FAVORABLE', 'DEFAVORABLE', 'NON_ETABLI', 'CONTRADICTOIRE', 'RESTRICTION'];

    public const ETATS_SEGMENT = ['PREPARATION', 'ACTIF', 'SUSPENDU', 'EXPIRE', 'REVOQUE'];

    public const STATUTS_MEMBRE_SEGMENT = ['ACTIF', 'RETIRE', 'EXPIRE', 'REVOQUE'];

    public const ETATS_ACTIVATION = [
        'DEMANDEE', 'REFUSEE', 'AUTORISEE', 'ACTIVE', 'SUSPENDUE',
        'TERMINEE', 'EXPIREE', 'REVOQUEE', 'EN_ECHEC',
    ];

    /** Codes canoniques d'obligation d'activation (doc 02 §21). */
    public const OBLIGATIONS = [
        'NO_RAW_EXPORT', 'NO_REUSE', 'PURPOSE_BOUND', 'CONSUMER_BOUND', 'REALM_BOUND',
        'EXPIRES_AT', 'NO_AUTOMATED_FINAL_DECISION', 'HUMAN_REVIEW_REQUIRED',
        'AGGREGATED_REPORTING_ONLY', 'DELETE_LOCAL_CACHE_AT', 'CONTESTATION_CHANNEL_REQUIRED',
    ];

    /** Obligations toujours présentes sur toute activation, quel que soit le contexte. */
    public const OBLIGATIONS_MINIMALES = [
        'NO_RAW_EXPORT', 'PURPOSE_BOUND', 'CONSUMER_BOUND', 'REALM_BOUND',
        'EXPIRES_AT', 'NO_AUTOMATED_FINAL_DECISION',
    ];

    public const ETATS_CONTESTATION = [
        'OUVERTE', 'RECEVABLE', 'NON_RECEVABLE', 'EN_INSTRUCTION',
        'CORRECTION_SOURCE_ATTENDUE', 'REEXECUTION_ATTENDUE', 'RESOLUE', 'REJETEE', 'CLOTUREE',
    ];

    public const VERDICTS_REEXAMEN = [
        'CONFIRME', 'MODIFIE', 'ANNULE', 'DEVENU_INDETERMINE', 'DEVENU_INTERDIT',
    ];

    public const POPULATIONS_JEU_EVALUATION = ['SYNTHETIQUE', 'ANONYMISE', 'AUTORISE_JURIDIQUEMENT'];

    /** Critères interdits par défaut (doc 01 §11) — jamais dérogeables par simple exception. */
    public const CRITERES_INTERDITS = [
        'JUGEMENT_MORAL', 'VALEUR_HUMAINE_GLOBALE', 'RANG_SPIRITUEL', 'PRATIQUE_RELIGIEUSE_SUPPOSEE',
        'OPINION_PRIVEE_SANS_LIEN', 'DONNEE_INTIME_NON_NECESSAIRE', 'REPUTATION_GENERALE_NON_SOURCEE',
        'CARACTERISTIQUE_OBTENUE_ILLICITEMENT', 'EXCLUSION_SANS_RAPPORT_LEGITIME',
        'PROXY_CRITERE_INTERDIT', 'SCORE_SOCIAL_TRANSVERSAL', 'INFERENCE_VULNERABILITE_COMMERCIALE',
    ];

    public const CODES_ERREUR = [
        'MATCHING_CONTEXT_UNKNOWN', 'MATCHING_CONTEXT_INACTIVE', 'MATCHING_PURPOSE_NOT_ALLOWED',
        'MATCHING_CONSUMER_NOT_ALLOWED', 'MATCHING_PRODUCT_INACTIVE', 'MATCHING_REALM_NOT_ALLOWED',
        'MATCHING_CROSS_REALM_DENIED', 'MATCHING_POLICY_UNKNOWN', 'MATCHING_POLICY_INACTIVE',
        'MATCHING_POLICY_DIVERGENT', 'MATCHING_CONTRACT_UNKNOWN', 'MATCHING_CONTRACT_INACTIVE',
        'MATCHING_SOURCE_UNKNOWN', 'MATCHING_SOURCE_UNUSABLE', 'MATCHING_SIGNAL_EXPIRED',
        'MATCHING_SIGNAL_CONTRADICTORY', 'MATCHING_CRITERION_UNKNOWN', 'MATCHING_CRITERION_FORBIDDEN',
        'MATCHING_CRITERION_NOT_ALLOWED', 'MATCHING_REQUIRED_DATA_UNKNOWN', 'MATCHING_POPULATION_TOO_SMALL',
        'MATCHING_LIMIT_EXCEEDED', 'MATCHING_RESULT_EXPIRED', 'MATCHING_SEGMENT_EXPIRED',
        'MATCHING_SEGMENT_SUSPENDED', 'MATCHING_SEGMENT_REVOKED', 'MATCHING_ACTIVATION_DENIED',
        'MATCHING_RAW_EXPORT_FORBIDDEN', 'MATCHING_REUSE_FORBIDDEN', 'MATCHING_HUMAN_REVIEW_REQUIRED',
        'MATCHING_RISK_BLOCKED', 'MATCHING_INCIDENT_BLOCKED', 'MATCHING_NON_REPRODUCIBLE',
        'MATCHING_IDEMPOTENCY_CONFLICT', 'MATCHING_DEPENDENCY_UNAVAILABLE',
    ];

    /** Limites d'exploitation par défaut (doc 03 §27), ajustables par configuration et versionnées dans le rapport final. */
    public const MATCHING_MAX_CANDIDATES = 5000;
    public const MATCHING_MAX_CRITERIA = 50;
    public const MATCHING_MAX_RESULTS = 500;
    public const MATCHING_MAX_SEGMENT_MEMBERS = 200_000;
    public const MATCHING_EXECUTION_TIMEOUT_SECONDES = 300;
    public const MATCHING_MAX_CONCURRENT_RUNS = 4;

    /** Seuil minimal d'agrégation en dessous duquel une estimation est refusée (doc 02 §31), versionné par contexte. */
    public const SEUIL_PETITE_POPULATION_DEFAUT = 25;
}
