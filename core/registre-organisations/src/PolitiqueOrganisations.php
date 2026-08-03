<?php

declare(strict_types=1);

namespace Gamad\RegistreOrganisations;

/**
 * Vocabulaire technique fermé du registre des organisations (CAP-CORE-002).
 *
 * Cette classe ne décide rien : la permission d'inscrire, modifier, activer,
 * suspendre, dissoudre, retirer une organisation, ou de gouverner ses
 * unités, relations, affiliations et fonctions, est portée par
 * `POL-ORGANISATIONS-V1`, un registre persistant de `CAP-CORE-007`, et
 * évaluée par `Ctr03` (CAP-CORE-004) dans la couche applicative. Elle borne
 * seulement les valeurs que le code sait traiter.
 *
 * Les listes ci-dessous sont la reprise fidèle des vocabulaires
 * `CAP-CORE-010` correspondants (voir `resources/bootstrap-organisations-v1.json`
 * et les vocabulaires `VOC-GAMAD-ORGANISATION-*` / `VOC-GAMAD-RELATION-ORGANISATION`
 * / `VOC-GAMAD-IDENTITE-CLASSIFICATION` / `VOC-GAMAD-IDENTITE-ASSURANCE`).
 * Ajouter un terme au vocabulaire ne modifie jamais automatiquement ces
 * listes ni les autorisations du registre (fiche §12).
 */
final class PolitiqueOrganisations
{
    public const CAPACITE = 'CAP-CORE-002';

    /** Politique technique d'administration de ce registre, bootstrapée avec lui. */
    public const POLITIQUE = 'POL-ORGANISATIONS-V1';

    public const ACTION_LIRE = 'lire une organisation non publique du registre';
    public const ACTION_INSCRIRE = 'inscrire une organisation dans le registre';
    public const ACTION_MODIFIER = 'modifier la fiche d’une organisation inscrite';
    public const ACTION_ACTIVER = 'activer une organisation inscrite';
    public const ACTION_SUSPENDRE = 'suspendre une organisation active';
    public const ACTION_DISSOUDRE = 'dissoudre une organisation';
    public const ACTION_RETIRER = 'retirer une organisation du registre';
    public const ACTION_IDENTIFIANT_DECLARER = 'déclarer un identifiant externe d’organisation';
    public const ACTION_IDENTIFIANT_FERMER = 'fermer un identifiant externe d’organisation';
    public const ACTION_UNITE_CREER = 'créer une unité d’organisation';
    public const ACTION_UNITE_MODIFIER = 'modifier ou déplacer une unité d’organisation';
    public const ACTION_UNITE_FERMER = 'fermer une unité d’organisation';
    public const ACTION_RELATION_DECLARER = 'déclarer une relation interorganisationnelle';
    public const ACTION_RELATION_FERMER = 'fermer une relation interorganisationnelle';
    public const ACTION_AFFILIATION_PROPOSER = 'proposer une affiliation d’organisation';
    public const ACTION_AFFILIATION_ACTIVER = 'activer une affiliation d’organisation';
    public const ACTION_AFFILIATION_SUSPENDRE = 'suspendre une affiliation d’organisation';
    public const ACTION_AFFILIATION_FERMER = 'fermer une affiliation d’organisation';
    public const ACTION_FONCTION_CREER = 'créer une fonction interne d’organisation';
    public const ACTION_REPRESENTATION_VERIFIER = 'vérifier une représentation d’organisation';

    /** Source technique inscrite par défaut dans les écritures gouvernées. */
    public const SOURCE = 'CAP-CORE-002 — registre des organisations';

    /** Autorité institutionnelle déjà canonique dans le corpus. */
    public const AUTORITE = 'AUT-GAMAD-001';

    /**
     * Types d'organisation. Liste close (fiche §4).
     * VOC-GAMAD-ORGANISATION-TYPE.
     */
    public const TYPES_ORGANISATION = [
        'SOCIETE', 'ASSOCIATION', 'INSTITUTION', 'ADMINISTRATION', 'FONDATION',
        'COOPERATIVE', 'ONG', 'GROUPE', 'ETABLISSEMENT', 'UNITE_INTERNE',
        'STRUCTURE_PARTENAIRE', 'INDETERMINE',
    ];

    /**
     * Formes d'organisation, facultatives et non exhaustives : le registre
     * n'invente aucune forme juridique (fiche §4). VOC-GAMAD-ORGANISATION-FORME.
     */
    public const FORMES_ORGANISATION = [
        'SARL', 'SA', 'SAS', 'ASSOCIATION_LOI', 'ETABLISSEMENT_PUBLIC',
        'COOPERATIVE_FORME', 'ENTREPRISE_INDIVIDUELLE', 'INDETERMINEE',
    ];

    /** États du cycle organisationnel. Liste close, en ajout seul (fiche §11.3). */
    public const ETATS_CYCLE = ['PREPARATION', 'ACTIVE', 'SUSPENDUE', 'DISSOUTE', 'RETIREE'];

    /** États depuis lesquels une activation est recevable. DISSOUTE et RETIREE sont terminaux. */
    public const ETATS_ACTIVABLES = ['PREPARATION', 'SUSPENDUE'];

    /** Types d'unité ou d'établissement. Liste close (fiche §11.5). VOC-GAMAD-ORGANISATION-UNITE-TYPE. */
    public const TYPES_UNITE = [
        'SIEGE', 'DIRECTION', 'DEPARTEMENT', 'SERVICE', 'AGENCE',
        'ETABLISSEMENT', 'SUCCURSALE', 'PROJET', 'COMITE',
    ];

    /** États du cycle d'une unité. Liste close (fiche §11.6). */
    public const ETATS_UNITE = ['ACTIVE', 'SUSPENDUE', 'FERMEE'];

    /** Types de relation organisation-à-organisation. Liste close (fiche §11.7). VOC-GAMAD-ORGANISATION-RELATION-TYPE. */
    public const TYPES_RELATION = [
        'PARENTE_DE', 'FILIALE_DE', 'AFFILIEE_A', 'MEMBRE_DE', 'PARTENAIRE_DE', 'OPERE_POUR',
    ];

    /** Types de relation soumis au contrôle d'acyclicité hiérarchique (fiche §11.7, §24). */
    public const TYPES_RELATION_HIERARCHIQUE = ['PARENTE_DE', 'FILIALE_DE'];

    /**
     * Types d'affiliation. Reprise fidèle de `VOC-GAMAD-RELATION-ORGANISATION`,
     * déjà bootstrapé pour CAP-CORE-001/CAP-CORE-010 — liste close identique
     * (fiche §11.8), pour ne pas créer une seconde vérité.
     */
    public const TYPES_AFFILIATION = [
        'MEMBRE', 'EMPLOYE', 'DIRIGEANT', 'REPRESENTANT', 'BENEFICIAIRE',
        'CLIENT', 'FOURNISSEUR', 'PARTENAIRE', 'CONTACT_AUTORISE',
    ];

    /**
     * Affiliations qui ne sont JAMAIS opposables sans mandat vérifié par
     * `CAP-CORE-003` (fiche §6, §11.8, §32).
     */
    public const AFFILIATIONS_A_MANDAT = ['REPRESENTANT', 'DIRIGEANT'];

    /** États du cycle d'une affiliation. Liste close, en ajout seul (fiche §11.9). */
    public const ETATS_AFFILIATION = ['PROPOSEE', 'ACTIVE', 'SUSPENDUE', 'CLOSE', 'REJETEE'];

    /** Types d'identifiant externe. Liste close (fiche §11.4). VOC-GAMAD-ORGANISATION-IDENTIFIANT-TYPE. */
    public const TYPES_IDENTIFIANT_EXTERNE = [
        'REGISTRE_COMMERCE', 'NUMERO_FISCAL', 'IDENTIFIANT_SECTORIEL', 'AUTRE',
    ];

    /** Types de fonction interne descriptive. Liste close (fiche §11.10). VOC-GAMAD-ORGANISATION-FONCTION-TYPE. */
    public const TYPES_FONCTION = [
        'DIRECTION_GENERALE', 'GERANCE', 'PRESIDENCE', 'TRESORERIE',
        'SECRETARIAT', 'RESPONSABLE_UNITE', 'AUTRE',
    ];

    /**
     * Classifications. Reprise fidèle de `VOC-GAMAD-IDENTITE-CLASSIFICATION`
     * (CAP-CORE-001/CAP-CORE-010) — même liste close (fiche §22).
     */
    public const CLASSIFICATIONS = [
        'PUBLIC_ECOSYSTEME', 'INTERNE', 'CONFIDENTIEL', 'RESTREINT', 'SECRET_CORE',
    ];

    /**
     * Niveaux d'assurance. Reprise fidèle de `VOC-GAMAD-IDENTITE-ASSURANCE`
     * (CAP-CORE-001/CAP-CORE-010).
     */
    public const NIVEAUX_ASSURANCE = ['A0', 'A1', 'A2', 'A3'];

    /** Préfixe de référence par entité gouvernée. Une référence retirée n'est jamais réattribuée. */
    public const PREFIXE = [
        'organisation' => 'ORG-GAMAD',
        'unite' => 'UNI-GAMAD',
        'relation' => 'REL-GAMAD',
        'affiliation' => 'AFL-GAMAD',
        'fonction' => 'FNI-GAMAD',
    ];
}
