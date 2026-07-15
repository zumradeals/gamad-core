<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

final readonly class ResumeMembership
{
    public function __construct(public string $membershipId)
    {
    }
}
