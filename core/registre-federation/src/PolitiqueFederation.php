<?php

declare(strict_types=1);

namespace Gamad\RegistreFederation;

/**
 * Vocabulaire technique fermé de la fédération des satellites (CAP-CORE-022).
 *
 * Cette classe borne ce que le Core sait émettre et vérifier. Elle n'invente
 * aucun droit métier : un jeton fédéré n'ouvre qu'une session locale chez le
 * satellite désigné. Les rôles, plans, quotas et règles économiques restent
 * la propriété du satellite et ne traversent jamais cette frontière.
 */
final class PolitiqueFederation
{
    /** La capacité servie par ce module. */
    public const CAPACITE = 'CAP-CORE-022';

    /**
     * Politique technique versionnée qui autorise l'ouverture fédérée. Elle est
     * portée par l'index (`politique` / `regle`) et évaluée par CAP-CORE-004 ;
     * elle n'est jamais écrite ici.
     */
    public const POLITIQUE = 'POL-FEDERATION-SATELLITES-V1';

    /** Action soumise à CAP-CORE-004 avant toute ouverture. */
    public const ACTION_OUVERTURE = 'ouvrir un accès fédéré à un produit reconnu';

    /** Action soumise à CAP-CORE-004 avant toute révocation d'accès. */
    public const ACTION_REVOCATION = 'révoquer un accès fédéré à un produit reconnu';

    /**
     * Actions de raccordement : délivrer et retirer l'identifiant avec lequel
     * un satellite s'authentifie auprès du Core.
     *
     * Elles sont distinctes des actions d'accès : ouvrir un accès concerne une
     * personne, délivrer un identifiant concerne le satellite lui-même. La
     * politique les réserve à l'autorité d'inscription.
     */
    public const ACTION_IDENTIFIANT = 'délivrer un identifiant de raccordement à un satellite';
    public const ACTION_RETRAIT_IDENTIFIANT = 'retirer un identifiant de raccordement à un satellite';

    /** Type d'authentificateur inscrit pour un satellite (CAP-CORE-005). */
    public const TYPE_IDENTIFIANT = 'secret_raccordement_satellite';

    /**
     * Deux identifiants actifs au plus : le courant, et son remplaçant pendant
     * une rotation. Au-delà, un secret oublié resterait valable indéfiniment.
     */
    public const MAX_IDENTIFIANTS = 2;

    /** Octets d'entropie d'un secret de raccordement. */
    public const ENTROPIE_SECRET = 24;

    /**
     * Engendre un secret de raccordement.
     *
     * Le Core l'engendre lui-même plutôt que de le faire saisir : un secret de
     * service tapé par une personne est court, réutilisé et mémorisable. Celui-ci
     * ne l'est pas, n'est jamais conservé en clair, et n'est montré qu'une fois.
     */
    public static function engendrerSecret(): string
    {
        return 'SAT-'.strtoupper(bin2hex(random_bytes(self::ENTROPIE_SECRET)));
    }

    /** Source technique inscrite dans les relations produites par la fédération. */
    public const SOURCE = 'CAP-CORE-022 — fédération des satellites';

    /**
     * Portées d'un jeton fédéré. Liste close et volontairement minimale :
     * le jeton sert à ouvrir une session locale, rien d'autre.
     *
     * @var list<string>
     */
    public const PORTEES = ['ouverture_session_locale'];

    /** Durée par défaut d'un jeton fédéré, en secondes. */
    public const DUREE_JETON = 120;

    /** Bornes d'une durée demandée. Aucun jeton fédéré n'est de longue vie. */
    public const DUREE_MINIMALE = 30;
    public const DUREE_MAXIMALE = 300;

    /**
     * Finalité CAP-CORE-001 exigée du porteur. `USAGE_PRODUIT` impose au moins
     * `A1` et un état exploitable ; une identité A0 ou provisoire n'ouvre rien.
     */
    public const FINALITE = 'USAGE_PRODUIT';

    /** Relation créée lorsque le provisionnement n'en précise aucune. */
    public const RELATION_PAR_DEFAUT = 'UTILISATEUR';
}
