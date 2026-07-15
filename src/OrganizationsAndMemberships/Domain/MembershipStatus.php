<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain;

enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';
}
