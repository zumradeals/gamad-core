<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

use DateTimeImmutable;

final readonly class CreateOrganization
{
    public function __construct(
        public string $identityId,
        public string $name,
        public ?string $parentId = null,
        public ?DateTimeImmutable $foundedAt = null,
    ) {
    }
}
