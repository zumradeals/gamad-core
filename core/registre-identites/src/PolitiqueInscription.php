<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * La politique d'inscription et l'échelle d'assurance, telles que l'autorité
 * les a arrêtées par `ADOPTION-0066`, Articles 212 et 213.
 *
 * Ces valeurs ne sont pas des choix d'ingénierie. Elles sont RECOPIÉES d'un
 * acte adopté, et une modification du code qui les changerait sans acte
 * ferait mentir le corpus. Chaque constante nomme l'article qui la porte.
 *
 * INV-79 — l'écriture est gouvernée, jamais libre. Six conditions cumulatives
 * pèsent sur toute commande, et la présente classe en porte trois : le canal
 * autorisé, le type que ce canal peut inscrire, et le niveau d'assurance
 * initial que ce canal produit — jamais davantage.
 */
final class PolitiqueInscription
{
    /**
     * Les cinq canaux d'inscription (ADOPTION-0066, Article 212).
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
     * Les canaux que l'autorité seule exerce (ADOPTION-0066, Article 214).
     * Un produit ou une organisation qui les invoquerait est refusé.
     */
    public const CANAUX_RESERVES = ['AUTORITE', 'CREATION_TECHNIQUE'];

    /** L'autorité d'inscription (ADOPTION-0066, Article 214). */
    public const AUTORITE_INSCRIPTION = 'AUT-GAMAD-001';

    /**
     * L'échelle d'assurance, ordonnée (ADOPTION-0066, Article 213).
     *
     * @var array<string,int>
     */
    public const ASSURANCE = ['A0' => 0, 'A1' => 1, 'A2' => 2, 'A3' => 3];

    /**
     * Niveau minimal exigé par finalité (ADOPTION-0066, Article 213).
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
     * qu'aucune conversion gouvernée n'est intervenue (ADOPTION-0066, Art. 213).
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
