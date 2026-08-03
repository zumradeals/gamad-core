<?php

declare(strict_types=1);

namespace Gamad\RegistreRealms;

/**
 * Vocabulaire technique fermé du registre des realms (CAP-CORE-012).
 *
 * Cette classe ne décide rien : la permission d'inscrire, modifier, activer,
 * suspendre, fermer, retirer un realm, ou de gouverner ses relations,
 * périmètres, rattachements et franchissements, est portée par
 * `POL-REALMS-V1`, un registre persistant de `CAP-CORE-007`, et évaluée par
 * `Ctr03` (CAP-CORE-004) dans la couche applicative. Elle borne seulement les
 * valeurs que le code sait traiter.
 *
 * Contrairement à `CAP-CORE-002`, ces listes n'étaient pas déjà bootstrapées
 * dans `CAP-CORE-010` avant ce chantier : `BootstrapRealmsCommand` inscrit
 * `VOC-GAMAD-REALM` (et ses termes, un par valeur ci-dessous) au même moment
 * qu'il inscrit `POL-REALMS-V1` et `CTR-12`, pour que le premier registre de
 * cette capacité s'auto-gouverne sans dépendre d'un chantier ultérieur (fiche
 * §13, §28, §46). Ajouter un terme au vocabulaire ne modifie jamais
 * automatiquement ces listes ni les autorisations du registre.
 */
final class PolitiqueRealms
{
    public const CAPACITE = 'CAP-CORE-012';

    /** Politique technique d'administration de ce registre, bootstrapée avec lui. */
    public const POLITIQUE = 'POL-REALMS-V1';

    /** Contrat interne CAP-CORE-009 décrivant la lecture du registre (fiche §46). */
    public const CONTRAT = 'CTR-12';

    /** Vocabulaire CAP-CORE-010 couvrant les types, rôles et motifs de ce registre (fiche §13, §41). */
    public const VOCABULAIRE = 'VOC-GAMAD-REALM';

    public const ACTION_LIRE = 'lire un realm non public du registre';
    public const ACTION_INSCRIRE = 'inscrire un realm dans le registre';
    public const ACTION_MODIFIER = 'modifier la fiche d’un realm inscrit';
    public const ACTION_ACTIVER = 'activer un realm inscrit';
    public const ACTION_SUSPENDRE = 'suspendre un realm actif';
    public const ACTION_FERMER = 'fermer un realm';
    public const ACTION_RETIRER = 'retirer un realm du registre';
    public const ACTION_RELATION_DECLARER = 'déclarer une relation entre realms';
    public const ACTION_RELATION_FERMER = 'fermer une relation entre realms';
    public const ACTION_PERIMETRE_DECLARER = 'déclarer un périmètre de realm';
    public const ACTION_PERIMETRE_FERMER = 'fermer un périmètre de realm';
    public const ACTION_IDENTIFIANT_DECLARER = 'déclarer un identifiant externe de realm';
    public const ACTION_IDENTIFIANT_FERMER = 'fermer un identifiant externe de realm';
    public const ACTION_ORGANISATION_RATTACHER = 'rattacher une organisation à un realm';
    public const ACTION_ORGANISATION_DETACHER = 'détacher une organisation d’un realm';
    public const ACTION_PRODUIT_RATTACHER = 'rattacher un produit à un realm';
    public const ACTION_PRODUIT_DETACHER = 'détacher un produit d’un realm';
    public const ACTION_CONTRAT_RATTACHER = 'rattacher un contrat à un realm';
    public const ACTION_CONTRAT_DETACHER = 'détacher un contrat d’un realm';
    public const ACTION_FRANCHISSEMENT_DECLARER = 'déclarer un franchissement entre realms';
    public const ACTION_FRANCHISSEMENT_FERMER = 'fermer un franchissement entre realms';
    public const ACTION_VERIFICATION_ENREGISTRER = 'enregistrer une vérification de realm';
    public const ACTION_PORTEE_VERIFIER = 'vérifier une portée de realm';

    /** Source technique inscrite par défaut dans les écritures gouvernées. */
    public const SOURCE = 'CAP-CORE-012 — registre des realms';

    /** Autorité institutionnelle déjà canonique dans le corpus. */
    public const AUTORITE = 'AUT-GAMAD-001';

    /**
     * Types de realm. Liste close (fiche §13). VOC-GAMAD-REALM code TYPE_*.
     */
    public const TYPES_REALM = [
        'TERRITORIAL', 'INSTITUTIONNEL', 'PROGRAMME', 'MARCHE', 'PRODUIT', 'TECHNIQUE', 'COOPERATION',
    ];

    /** États du cycle de realm. Liste close, en ajout seul (fiche §15). */
    public const ETATS_CYCLE = ['PREPARATION', 'ACTIF', 'SUSPENDU', 'FERME', 'RETIRE'];

    /** États depuis lesquels une activation est recevable. FERME et RETIRE sont terminaux sans commande dédiée. */
    public const ETATS_ACTIVABLES = ['PREPARATION', 'SUSPENDU'];

    /**
     * Classifications. Reprise fidèle de `VOC-GAMAD-IDENTITE-CLASSIFICATION`
     * (CAP-CORE-001/CAP-CORE-010) — même liste close que CAP-CORE-002.
     */
    public const CLASSIFICATIONS = [
        'PUBLIC_ECOSYSTEME', 'INTERNE', 'CONFIDENTIEL', 'RESTREINT', 'SECRET_CORE',
    ];

    /** Types de relation entre realms. Liste close (fiche §16). */
    public const TYPES_RELATION = [
        'PARENT_DE', 'INCLUS_DANS', 'CHEVAUCHE', 'EQUIVALENT_OPERATIONNEL', 'SUCCEDE_A', 'COOPERE_AVEC',
    ];

    /**
     * Seul `PARENT_DE` est enregistré pour construire la hiérarchie
     * canonique (fiche §16, §23) : `INCLUS_DANS` se dérive en lecture, jamais
     * doublée en base.
     */
    public const TYPES_RELATION_HIERARCHIQUE = ['PARENT_DE'];

    /** Dimensions de périmètre. Liste close (fiche §17). */
    public const DIMENSIONS_PERIMETRE = [
        'PAYS', 'REGION', 'VILLE', 'JURIDICTION', 'MARCHE', 'DOMAINE_ACTIVITE',
        'PROGRAMME', 'ENVIRONNEMENT', 'INSTITUTION', 'CLASSIFICATION_DONNEES',
    ];

    /** Rôles d'organisation rattachée à un realm. Liste close (fiche §19). */
    public const ROLES_ORGANISATION = [
        'RESPONSABLE', 'OPERATEUR', 'PARTICIPANT', 'REGULATEUR', 'BENEFICIAIRE', 'OBSERVATEUR',
    ];

    /** Rôles nécessitant un mandat vérifié (CAP-CORE-003) pour l'acteur qui engage l'organisation. */
    public const ROLES_ORGANISATION_A_MANDAT = ['RESPONSABLE', 'REGULATEUR'];

    /** Rôles de produit rattaché à un realm. Liste close (fiche §20). */
    public const ROLES_PRODUIT = [
        'OPERE_DANS', 'FOURNIT_SERVICE', 'CONSOMME_SERVICE', 'ADMINISTRE', 'OBSERVE',
    ];

    /** Rôles de contrat rattaché à un realm. Liste close (fiche §22). */
    public const ROLES_CONTRAT = ['GOUVERNE', 'SUPPORTE', 'AUDITE'];

    /** Types de vérification de realm. Liste close (fiche §24). */
    public const TYPES_VERIFICATION = ['CONFORMITE_JURIDIQUE', 'CONFORMITE_TECHNIQUE', 'REVUE_AUTORITE'];

    /** Résultats de vérification de realm. Liste close (fiche §24). */
    public const RESULTATS_VERIFICATION = ['CONFORME', 'NON_CONFORME', 'A_REVOIR'];

    /**
     * Vérificateurs habilités pour une vérification forte. Une vérification
     * dont le vérificateur est le realm lui-même ou son propre producteur
     * n'est jamais recevable (fiche §24, §38, épreuve 49).
     */
    public const AUTO_ATTESTATION_INTERDITE = true;

    /** Effets de franchissement. Liste close (fiche §23). */
    public const EFFETS_FRANCHISSEMENT = ['PERMET', 'REFUSE'];

    /** Motifs canoniques de refus du moteur de portée (fiche §41). VOC-GAMAD-REALM code MOTIF_*. */
    public const MOTIFS_REFUS = [
        'REALM_INCONNU', 'REALM_EN_PREPARATION', 'REALM_SUSPENDU', 'REALM_FERME', 'REALM_RETIRE',
        'ORGANISATION_NON_RATTACHEE', 'ORGANISATION_INACTIVE', 'MANDAT_INSUFFISANT',
        'PRODUIT_NON_RATTACHE', 'PRODUIT_INACTIF', 'CONTRAT_NON_RATTACHE', 'CONTRAT_INACTIF',
        'FINALITE_INCONNUE', 'PERIMETRE_NON_SATISFAIT', 'FRANCHISSEMENT_NON_DECLARE',
        'FRANCHISSEMENT_REFUSE', 'VERIFICATION_EXPIREE', 'DEPENDANCE_INDISPONIBLE',
    ];

    /** Préfixe de référence par entité gouvernée. Une référence retirée n'est jamais réattribuée. */
    public const PREFIXE = [
        'realm' => 'RLM-GAMAD',
        'relation' => 'RRL-GAMAD',
        'organisation' => 'ROR-GAMAD',
        'produit' => 'RPR-GAMAD',
    ];
}
