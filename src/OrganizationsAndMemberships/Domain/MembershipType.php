<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain;

/**
 * An institutional category, never an access role (GENESIS-011 §5, GENESIS-012 §C
 * red line) — Access Control alone decides what a membership type permits.
 */
enum MembershipType: string
{
    case GamadCitizen = 'GAMAD_CITIZEN';
    case OrdinaryCitizen = 'ORDINARY_CITIZEN';
    case Partner = 'PARTNER';
}
