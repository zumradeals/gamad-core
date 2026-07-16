<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

final readonly class SuspendMembership
{
    public function __construct(
        public string $membershipId,
        public ?string $actorId = null,
    ) {
    }
}
