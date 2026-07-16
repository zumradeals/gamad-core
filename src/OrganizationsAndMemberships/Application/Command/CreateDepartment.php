<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

final readonly class CreateDepartment
{
    public function __construct(
        public string $organizationId,
        public string $name,
        public ?string $actorId = null,
    ) {
    }
}
