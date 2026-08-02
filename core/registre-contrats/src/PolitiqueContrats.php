<?php

declare(strict_types=1);

namespace Gamad\RegistreContrats;

/**
 * Vocabulaire technique fermé du registre des contrats (CAP-CORE-009).
 *
 * Cette classe ne décide rien : la permission d'inscrire, de versionner, de
 * déclarer, de soumettre, d'analyser, d'activer, de déprécier, de suspendre
 * ou de retirer un contrat est portée par `POL-CONTRATS-V1` et évaluée par
 * `Ctr03` (CAP-CORE-004). Elle borne seulement les valeurs que le code sait
 * traiter.
 */
final class PolitiqueContrats
{
    public const CAPACITE = 'CAP-CORE-009';

    /** Politique technique d'administration de ce registre, bootstrapée avec lui. */
    public const POLITIQUE = 'POL-CONTRATS-V1';

    public const ACTION_LIRE = 'lire un contrat en brouillon';
    public const ACTION_INSCRIRE = 'inscrire un contrat dans le registre';
    public const ACTION_VERSION_CREER = 'créer une version de contrat';
    public const ACTION_VERSION_MODIFIER = 'modifier une version de contrat en brouillon';
    public const ACTION_CONSOMMATEUR_RATTACHER = 'rattacher un consommateur à une version de contrat';
    public const ACTION_VERSION_SOUMETTRE = 'soumettre une version de contrat à validation';
    public const ACTION_VERSION_ANALYSER = 'analyser la compatibilité d’une version de contrat';
    public const ACTION_VERSION_ACTIVER = 'activer une version de contrat';
    public const ACTION_VERSION_DEPRECIER = 'déprécier une version de contrat active';
    public const ACTION_VERSION_SUSPENDRE = 'suspendre une version de contrat';
    public const ACTION_VERSION_RETIRER = 'retirer une version de contrat';
    public const ACTION_CONFORMITE_ENREGISTRER = 'enregistrer une conformité de contrat';
    public const ACTION_PROJECTION_GENERER = 'générer une projection de contrat';

    /** Source technique inscrite par défaut dans les écritures gouvernées. */
    public const SOURCE = 'CAP-CORE-009 — registre des contrats';

    /** Autorité institutionnelle déjà canonique dans le corpus. */
    public const AUTORITE = 'AUT-GAMAD-001';

    /** Types de contrat. Liste close initiale (section 5 de la fiche). */
    public const TYPES_CONTRAT = [
        'COMMANDE', 'REQUETE', 'EVENEMENT', 'SIGNAL', 'ATTESTATION',
        'REFERENCE_TEMPORAIRE', 'HTTP_API', 'INTERCAPACITE',
    ];

    /** États du cycle d'une version. Liste close, en ajout seul. */
    public const ETATS_CYCLE = [
        'BROUILLON', 'EN_VALIDATION', 'ACTIVE', 'DEPRECIEE', 'SUSPENDUE', 'REMPLACEE', 'RETIREE',
    ];

    /** Valeurs de compatibilité annoncée par le producteur à la création d'une version. */
    public const COMPATIBILITES_ANNONCEES = ['COMPATIBLE', 'COMPATIBLE_AVEC_ADAPTATION', 'RUPTURE'];

    /** Résultats de l'analyse de compatibilité effective. */
    public const RESULTATS_COMPATIBILITE = ['COMPATIBLE', 'ADAPTATION_REQUISE', 'RUPTURE', 'INDETERMINE'];

    /** Résultats de conformité. */
    public const RESULTATS_CONFORMITE = ['CONFORME', 'NON_CONFORME', 'INCOMPLET'];

    /** Rôles d'une partie prenante d'une version de contrat. */
    public const ROLES_PARTIE = ['PRODUCTEUR', 'CONSOMMATEUR', 'OPERATEUR', 'VERIFICATEUR'];

    /** Types de partie : la partie référence une capacité ou un produit connu. */
    public const TYPES_PARTIE = ['CAPACITE', 'PRODUIT'];

    /** Types d'opération. */
    public const TYPES_OPERATION = ['COMMANDER', 'INTERROGER', 'PUBLIER', 'CONSOMMER', 'VERIFIER', 'REVOQUER'];

    /** Sens d'un schéma. */
    public const SENS_SCHEMA = ['ENTREE', 'SORTIE', 'EVENEMENT', 'ERREUR'];

    /** Formats de schéma supportés. */
    public const FORMATS_SCHEMA = ['JSON_SCHEMA', 'OPENAPI_SCHEMA', 'TEXTE_STRUCTURE', 'AUCUN_CORPS'];

    /** Types d'obligation contractuelle. */
    public const TYPES_OBLIGATION = [
        'AUTORISATION', 'AUDIT', 'FINALITE', 'SOURCE', 'EXPIRATION', 'MINIMISATION',
        'CONFIDENTIALITE', 'IDEMPOTENCE', 'ASSURANCE_SESSION',
    ];

    /** Types de projection dérivée. */
    public const TYPES_PROJECTION = ['OPENAPI', 'JSON_SCHEMA', 'PHP_INTERFACE', 'DOCUMENTATION'];

    /** Format de version attendu : SemVer simplifié (X.Y.Z). */
    public const FORMAT_VERSION = '/^\d+\.\d+\.\d+$/';
}
