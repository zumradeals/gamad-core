<?php

declare(strict_types=1);

namespace Gamad\RegistrePolitiques;

/**
 * Vocabulaire technique fermé du registre des politiques (CAP-CORE-007).
 *
 * Cette classe ne décide rien : la permission d'inscrire, de créer une
 * version, d'y ajouter des règles, de la soumettre, de la simuler, de
 * l'activer, de la suspendre ou de retirer une politique est portée par
 * `POL-POLITIQUES-V1` et évaluée par CTR-03 — la seule différence avec les
 * autres politiques techniques est que CTR-03 lit désormais ce magasin pour
 * se juger lui-même. Elle borne seulement les valeurs que le code sait
 * traiter.
 */
final class PolitiqueAdministration
{
    public const CAPACITE = 'CAP-CORE-007';

    /**
     * Politique technique d'administration du registre des politiques
     * lui-même. Bootstrapée comme les autres, activée avec elles.
     */
    public const POLITIQUE = 'POL-POLITIQUES-V1';

    public const ACTION_LIRE = 'lire une politique en brouillon';
    public const ACTION_INSCRIRE = 'inscrire une politique dans le registre';
    public const ACTION_VERSION_CREER = 'créer une version de politique';
    public const ACTION_VERSION_MODIFIER = 'modifier une version de politique en brouillon';
    public const ACTION_VERSION_SOUMETTRE = 'soumettre une version de politique à validation';
    public const ACTION_VERSION_SIMULER = 'simuler une version de politique';
    public const ACTION_VERSION_ACTIVER = 'activer une version de politique';
    public const ACTION_VERSION_SUSPENDRE = 'suspendre une version de politique active';
    public const ACTION_VERSION_REMPLACER = 'remplacer une version de politique active';
    public const ACTION_RETIRER = 'retirer une politique du registre';

    /** Source technique inscrite par défaut dans les écritures gouvernées. */
    public const SOURCE = 'CAP-CORE-007 — registre des politiques';

    /** Autorité institutionnelle déjà canonique dans le corpus. */
    public const AUTORITE = 'AUT-GAMAD-001';

    /** États du cycle d'une version. Liste close, en ajout seul. */
    public const ETATS_CYCLE = ['BROUILLON', 'EN_VALIDATION', 'ACTIVE', 'SUSPENDUE', 'REMPLACEE', 'RETIREE'];

    /** Effets d'une règle. Liste close. */
    public const EFFETS = ['PERMET', 'REFUSE'];

    /** Résultats de simulation. Liste close. */
    public const RESULTATS_SIMULATION = ['REUSSIE', 'ECHEC', 'INCOMPLETE'];

    /** Format de version attendu : SemVer simplifié (X.Y.Z). */
    public const FORMAT_VERSION = '/^\d+\.\d+\.\d+$/';
}
