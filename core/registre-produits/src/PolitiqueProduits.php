<?php

declare(strict_types=1);

namespace Gamad\RegistreProduits;

/**
 * Vocabulaire technique fermé du registre des produits (CAP-CORE-011).
 *
 * Cette classe ne décide rien : la permission d'inscrire, modifier, activer,
 * suspendre ou retirer un produit est portée par l'index (`politique` /
 * `regle`) et évaluée par CAP-CORE-004, comme `PolitiqueFederation` le fait
 * pour CAP-CORE-022. Elle borne seulement les valeurs que le code sait
 * traiter.
 */
final class PolitiqueProduits
{
    public const CAPACITE = 'CAP-CORE-011';

    /**
     * Politique technique versionnée. Portée provisoirement par la source
     * technique versionnée actuelle tant que CAP-CORE-007 reste NO GO.
     */
    public const POLITIQUE = 'POL-PRODUITS-V1';

    public const ACTION_LIRE = 'lire un produit non actif du registre';
    public const ACTION_INSCRIRE = 'inscrire un produit dans le registre';
    public const ACTION_MODIFIER = 'modifier les métadonnées d’un produit inscrit';
    public const ACTION_ACTIVER = 'activer un produit inscrit';
    public const ACTION_SUSPENDRE = 'suspendre un produit actif';
    public const ACTION_RETIRER = 'retirer un produit du registre';
    public const ACTION_ENVIRONNEMENT_DECLARER = 'déclarer un environnement de produit';
    public const ACTION_ENVIRONNEMENT_FERMER = 'fermer un environnement de produit';

    /** Source technique inscrite par défaut dans les écritures gouvernées. */
    public const SOURCE = 'CAP-CORE-011 — registre des produits';

    /** Autorité institutionnelle déjà canonique dans le corpus. */
    public const AUTORITE = 'AUT-GAMAD-001';

    /** Types de produit. Liste close (fiche CAP-CORE-011, §6.1). */
    public const TYPES_PRODUIT = [
        'PORTAIL', 'SATELLITE', 'SERVICE_CORE', 'PARTENAIRE', 'APPLICATION_INTERNE',
    ];

    /** États du cycle de vie. Liste close, en ajout seul. */
    public const ETATS_CYCLE = ['PREPARATION', 'ACTIF', 'SUSPENDU', 'RETIRE'];

    /** États depuis lesquels une activation est recevable. RETIRE est terminal. */
    public const ETATS_ACTIVABLES = ['PREPARATION', 'SUSPENDU'];

    /** Environnements déclarables. Liste close. */
    public const ENVIRONNEMENTS = ['DEVELOPPEMENT', 'RECETTE', 'PRODUCTION'];
}
