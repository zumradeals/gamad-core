<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Vocabulaire technique fermé de la première implémentation révisée.
 *
 * La loi adoptée par ADOPTION-0064 impose une écriture gouvernée, l'échelle
 * A0–A3 et des politiques nommées. Cette classe ne prétend pas remplacer le
 * futur registre des politiques : chaque commande doit encore fournir la
 * politique, sa source, son producteur et sa preuve. Elle borne seulement les
 * valeurs que le code sait traiter sans inventer de profil ni de droit.
 */
final class PolitiqueInscription
{
    /**
     * Canaux supportés par ce premier incrément.
     *
     * Un canal ne produit JAMAIS un niveau d'assurance supérieur au sien, et
     * n'inscrit JAMAIS un type absent de sa liste. Aucun produit n'inscrit un
     * agent, un service, un produit ou un realm : ces types relèvent de
     * l'autorité seule.
     *
     * @var array<string,array{assurance:string,types:list<string>}>
     */
    public const CANAUX = [
        'AUTO_INSCRIPTION' => [
            'assurance' => 'A0',
            'types'     => ['personne'],
        ],
        'PRODUIT_RECONNU' => [
            'assurance' => 'A1',
            'types'     => ['personne', 'organisation'],
        ],
        'ORGANISATION_RECONNUE' => [
            'assurance' => 'A1',
            'types'     => ['personne'],
        ],
        'AUTORITE' => [
            'assurance' => 'A3',
            'types'     => ['personne', 'organisation', 'produit', 'realm', 'agent', 'service'],
        ],
        'CREATION_TECHNIQUE' => [
            'assurance' => 'A3',
            'types'     => ['agent', 'service', 'produit', 'realm'],
        ],
    ];

    /**
     * Les canaux que l'autorité d'inscription seule exerce.
     * Un produit ou une organisation qui les invoquerait est refusé.
     */
    public const CANAUX_RESERVES = ['AUTORITE', 'CREATION_TECHNIQUE'];

    /** Autorité institutionnelle déjà canonique dans le corpus. */
    public const AUTORITE_INSCRIPTION = 'AUT-GAMAD-001';

    /**
     * L'échelle d'assurance ordonnée adoptée pour CAP-CORE-001.
     *
     * @var array<string,int>
     */
    public const ASSURANCE = ['A0' => 0, 'A1' => 1, 'A2' => 2, 'A3' => 3];

    /**
     * Niveau minimal traité par finalité dans cette première version.
     *
     * `REPRESENTATION` exige `A3` ET un mandat vérifié par `CAP-CORE-003` : le
     * niveau seul n'y suffit jamais, et c'est `resoudreEtatUtilisable` qui
     * vérifie la seconde condition.
     *
     * @var array<string,string>
     */
    public const FINALITES = [
        'EXISTENCE'         => 'A0',
        'USAGE_PRODUIT'     => 'A1',
        'ACTION_ENGAGEANTE' => 'A2',
        'REPRESENTATION'    => 'A3',
    ];

    /** Les finalités qui exigent en outre un mandat vérifié. */
    public const FINALITES_A_MANDAT = ['REPRESENTATION'];

    /**
     * Plafond d'assurance d'une identité provisoire ou pseudonyme, tant
     * qu'aucune conversion gouvernée n'est intervenue.
     */
    public const PLAFOND_PROVISOIRE = 'A1';

    /**
     * Types de relation à un produit (loi révisée, Article 21). Liste close.
     *
     * @var list<string>
     */
    public const RELATIONS_PRODUIT = [
        'UTILISATEUR', 'CLIENT', 'ANNONCEUR', 'ADMINISTRATEUR', 'OPERATEUR',
        'RESPONSABLE_PRODUIT', 'PROPRIETAIRE_INSTITUTIONNEL', 'PARTENAIRE',
    ];

    /**
     * Types de relation à une organisation (loi révisée, Article 21). Liste close.
     *
     * @var list<string>
     */
    public const RELATIONS_ORGANISATION = [
        'MEMBRE', 'EMPLOYE', 'DIRIGEANT', 'REPRESENTANT', 'BENEFICIAIRE',
        'CLIENT', 'FOURNISSEUR', 'PARTENAIRE', 'CONTACT_AUTORISE',
    ];

    /**
     * Relations qui ne sont JAMAIS opposables sans mandat vérifié par
     * `CAP-CORE-003`. Elles sont inscriptibles ; elles ne valent pas
     * représentation (loi révisée, Article 21 ; INV-77).
     *
     * @var list<string>
     */
    public const RELATIONS_A_MANDAT = ['REPRESENTANT', 'DIRIGEANT'];

    /**
     * Événements de cycle de vie (loi révisée, Article 22). Liste close.
     *
     * @var list<string>
     */
    public const EVENEMENTS = [
        'CREATION', 'VERIFICATION', 'SUSPENSION', 'REACTIVATION', 'FUSION',
        'SCISSION', 'CLOTURE', 'DISSOLUTION', 'CORRECTION',
        'CONVERSION_PROVISOIRE', 'RATTACHEMENT_PRODUIT', 'RETRAIT_PRODUIT',
        'RATTACHEMENT_ORGANISATION', 'RETRAIT_ORGANISATION',
    ];

    /**
     * Classifications (loi révisée, Article 30). Liste close.
     *
     * @var list<string>
     */
    public const CLASSIFICATIONS = [
        'PUBLIC_ECOSYSTEME', 'INTERNE', 'CONFIDENTIEL', 'RESTREINT', 'SECRET_CORE',
    ];

    /** États propres aux identités persistantes. */
    public const ETATS_IDENTITE = [
        'PROVISOIRE', 'ACTIVE', 'SUSPENDUE', 'CLOTUREE', 'DISSOUTE',
    ];

    /** Résultats permis pour une proposition de rapprochement. */
    public const QUALIFICATIONS_RAPPROCHEMENT = [
        'AUCUNE_CORRESPONDANCE',
        'CORRESPONDANCE_POSSIBLE',
        'CORRESPONDANCE_PROBABLE',
    ];

    /** États gouvernés d'un rapprochement ; aucun n'opère une fusion. */
    public const ETATS_RAPPROCHEMENT = [
        'VALIDATION_REQUISE', 'VALIDEE', 'REJETEE',
    ];

    /** Préfixe de référence par type. Une référence n'est jamais réattribuée (INV-17). */
    public const PREFIXE = [
        'personne'     => 'IDN-PER',
        'organisation' => 'IDN-ORG',
        'agent'        => 'IDN-AGE',
        'service'      => 'IDN-SER',
        'produit'      => 'IDN-PRD',
        'realm'        => 'IDN-RLM',
        'INDETERMINE'  => 'IDN-IND',
    ];

    /** Le niveau `a` atteint-il au moins le niveau `b` ? */
    public static function auMoins(string $a, string $b): bool
    {
        return (self::ASSURANCE[$a] ?? -1) >= (self::ASSURANCE[$b] ?? PHP_INT_MAX);
    }
}
