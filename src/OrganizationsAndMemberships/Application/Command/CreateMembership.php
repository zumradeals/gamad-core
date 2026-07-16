<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

use DateTimeImmutable;

final readonly class CreateMembership
{
    public function __construct(
        public string $personId,
        public string $organizationId,
        public string $membershipType,
        public ?string $departmentId = null,
        public ?DateTimeImmutable $startedAt = null,
        public ?string $actorId = null,
    ) {
    }
}
