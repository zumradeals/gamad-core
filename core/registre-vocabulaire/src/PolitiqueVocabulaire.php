<?php

declare(strict_types=1);

namespace Gamad\RegistreVocabulaire;

/**
 * Vocabulaire technique fermé du registre du vocabulaire canonique
 * (CAP-CORE-010).
 *
 * Cette classe ne décide rien : la permission d'inscrire, de versionner, de
 * déclarer un terme, un libellé, un alias, une relation, un mapping, un
 * usage, de soumettre, d'analyser, d'activer, de déprécier ou de retirer est
 * portée par `POL-VOCABULAIRE-V1` et évaluée par `Ctr03` (CAP-CORE-004).
 */
final class PolitiqueVocabulaire
{
    public const CAPACITE = 'CAP-CORE-010';

    /** Politique technique d'administration de ce registre, bootstrapée avec lui. */
    public const POLITIQUE = 'POL-VOCABULAIRE-V1';

    public const ACTION_LIRE = 'lire un vocabulaire';
    public const ACTION_INSCRIRE = 'inscrire un vocabulaire';
    public const ACTION_VERSION_CREER = 'créer une version de vocabulaire';
    public const ACTION_TERME_AJOUTER = 'ajouter un terme à une version de vocabulaire';
    public const ACTION_TERME_EVOLUER = 'faire évoluer un terme existant vers une nouvelle version de vocabulaire';
    public const ACTION_TERME_MODIFIER = 'modifier un terme d’une version de vocabulaire';
    public const ACTION_ALIAS_AJOUTER = 'ajouter un alias à un terme';
    public const ACTION_MAPPING_AJOUTER = 'ajouter un mapping externe à un terme';
    public const ACTION_USAGE_DECLARER = 'déclarer un usage de terme';
    public const ACTION_VERSION_SOUMETTRE = 'soumettre une version de vocabulaire à validation';
    public const ACTION_VERSION_ANALYSER = 'analyser la compatibilité d’une version de vocabulaire';
    public const ACTION_VERSION_ACTIVER = 'activer une version de vocabulaire';
    public const ACTION_VERSION_DEPRECIER = 'déprécier une version de vocabulaire active';
    public const ACTION_VERSION_RETIRER = 'retirer une version de vocabulaire';
    public const ACTION_PROJECTION_GENERER = 'générer une projection de vocabulaire';
    public const ACTION_CONFORMITE_ENREGISTRER = 'enregistrer une conformité de vocabulaire';

    /** Source technique inscrite par défaut dans les écritures gouvernées. */
    public const SOURCE = 'CAP-CORE-010 — registre du vocabulaire canonique';

    /** Autorité institutionnelle déjà canonique dans le corpus. */
    public const AUTORITE = 'AUT-GAMAD-001';

    /** Portées d'un vocabulaire. Liste close. */
    public const PORTEES = ['CORE', 'ECOSYSTEME', 'CONTRAT', 'CAPACITE', 'PRODUIT_PARTAGE'];

    /** États du cycle d'une version. Liste close, en ajout seul. */
    public const ETATS_CYCLE = ['BROUILLON', 'EN_VALIDATION', 'ACTIVE', 'DEPRECIEE', 'REMPLACEE', 'RETIREE'];

    /** Types sémantiques d'un terme. Liste close. */
    public const TYPES_SEMANTIQUES = [
        'TYPE', 'ETAT', 'ACTION', 'FINALITE', 'RELATION', 'NIVEAU', 'RESULTAT',
        'ROLE', 'CATEGORIE', 'ERREUR', 'ENVIRONNEMENT', 'CLASSIFICATION',
    ];

    /** Types d'alias. */
    public const TYPES_ALIAS = ['ANCIEN_CODE', 'LIBELLE', 'ABREVIATION', 'CODE_EXTERNE', 'ORTHOGRAPHE_HISTORIQUE'];

    /** Types de relation entre deux termes. */
    public const TYPES_RELATION = [
        'PLUS_LARGE_QUE', 'PLUS_ETROIT_QUE', 'EQUIVALENT_EXPLICITE', 'REMPLACE',
        'ASSOCIE_A', 'INCOMPATIBLE_AVEC',
    ];

    /** Sens d'un mapping externe. */
    public const SENS_MAPPING = ['ENTRANT', 'SORTANT', 'BIDIRECTIONNEL'];

    /** Statuts d'un mapping externe. */
    public const STATUTS_MAPPING = ['EXACT', 'APPROXIMATIF', 'PERTE_INFORMATION', 'INTERDIT'];

    /** Types d'usage d'un terme. */
    public const TYPES_USAGE = [
        'ENTREE', 'SORTIE', 'REGLE', 'ETAT_PERSISTE', 'AFFICHAGE', 'MAPPING', 'EVENEMENT', 'SIGNAL',
    ];

    /** Résultats de conformité. */
    public const RESULTATS_CONFORMITE = ['CONFORME', 'NON_CONFORME', 'INCOMPLET'];

    /** Types de consommateur d'une conformité ou d'un usage. */
    public const TYPES_CONSOMMATEUR = ['CAPACITE', 'PRODUIT'];

    /** Types de projection dérivée. */
    public const TYPES_PROJECTION = ['JSON', 'PHP_CONSTANTS', 'OPENAPI_ENUM', 'SQL_CHECK', 'DOCUMENTATION'];

    /** Résultats de l'analyse de compatibilité. */
    public const RESULTATS_COMPATIBILITE = ['COMPATIBLE', 'ADAPTATION_REQUISE', 'RUPTURE'];

    /** Format de version attendu : SemVer simplifié (X.Y.Z). */
    public const FORMAT_VERSION = '/^\d+\.\d+\.\d+$/';

    /** Locales acceptées pour un libellé. Liste close, alignée sur l'exploitation actuelle. */
    public const LOCALES = ['fr', 'fr-FR', 'fr-CI', 'en'];
}
