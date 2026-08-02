<?php

declare(strict_types=1);

namespace Gamad\RegistreSources;

/**
 * Vocabulaire technique fermé du registre des sources (CAP-CORE-006).
 *
 * Cette classe ne décide rien : la permission d'inscrire, modifier, activer,
 * suspendre, retirer, déclarer une finalité, l'enregistrer, la fermer,
 * enregistrer une vérification ou déclarer une lignée est portée par l'index
 * (`politique` / `regle`) et évaluée par CAP-CORE-004, comme
 * `PolitiqueProduits` le fait pour CAP-CORE-011. Elle borne seulement les
 * valeurs que le code sait traiter.
 */
final class PolitiqueSources
{
    public const CAPACITE = 'CAP-CORE-006';

    /**
     * Politique technique versionnée. Portée provisoirement par la source
     * technique versionnée actuelle tant que CAP-CORE-007 reste NO GO.
     */
    public const POLITIQUE = 'POL-SOURCES-V1';

    public const ACTION_LIRE = 'lire une source non active du registre';
    public const ACTION_INSCRIRE = 'inscrire une source dans le registre';
    public const ACTION_MODIFIER = 'modifier les métadonnées d’une source inscrite';
    public const ACTION_ACTIVER = 'activer une source inscrite';
    public const ACTION_SUSPENDRE = 'suspendre une source active';
    public const ACTION_RETIRER = 'retirer une source du registre';
    public const ACTION_FINALITE_DECLARER = 'déclarer une finalité de source';
    public const ACTION_FINALITE_FERMER = 'fermer une finalité de source';
    public const ACTION_VERIFICATION_ENREGISTRER = 'enregistrer une vérification de source';
    public const ACTION_LIGNEE_DECLARER = 'déclarer une lignée de source';
    public const ACTION_UTILISABILITE_VERIFIER = 'vérifier l’utilisabilité d’une source';

    /** Source technique inscrite par défaut dans les écritures gouvernées. */
    public const SOURCE = 'CAP-CORE-006 — registre des sources';

    /** Autorité institutionnelle déjà canonique dans le corpus. */
    public const AUTORITE = 'AUT-GAMAD-001';

    /** Types de source. Liste close (fiche CAP-CORE-006, §9.1). */
    public const TYPES_SOURCE = [
        'PRODUIT_GAMAD', 'SERVICE_CORE', 'ORGANISATION', 'INSTITUTION',
        'PARTENAIRE', 'SYSTEME_EXTERNE', 'IMPORT_GOUVERNE', 'CANAL_DECLARATIF',
    ];

    /** États du cycle de vie. Liste close, en ajout seul. */
    public const ETATS_CYCLE = ['PREPARATION', 'ACTIVE', 'SUSPENDUE', 'RETIREE'];

    /** États depuis lesquels une activation est recevable. RETIREE est terminal. */
    public const ETATS_ACTIVABLES = ['PREPARATION', 'SUSPENDUE'];

    /** Niveaux de vérification opérationnelle. Liste close, en ajout seul. */
    public const NIVEAUX_VERIFICATION = ['NON_VERIFIEE', 'DECLAREE', 'CONTROLEE', 'ATTESTEE'];

    /** Résultats de vérification. Liste close. */
    public const RESULTATS_VERIFICATION = ['VALIDE', 'INVALIDE', 'EXPIREE'];

    /** Niveaux exigeant une preuve de contrôle distincte du producteur. */
    public const NIVEAUX_EXIGEANT_PREUVE = ['CONTROLEE', 'ATTESTEE'];

    /** Types de relation de lignée. Liste close, en ajout seul. */
    public const TYPES_LIGNEE = ['DERIVEE_DE', 'AGREGE', 'REMPLACE', 'CORRIGE'];
}
