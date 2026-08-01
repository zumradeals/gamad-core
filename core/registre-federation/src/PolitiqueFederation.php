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

    /**
     * Marqueur d'un produit reconnu dans l'état dérivé de l'index. Un partenaire
     * externe non entériné n'est pas fédérable.
     */
    public const MARQUEUR_RECONNU = 'RECONNU';
}
