<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application;

final readonly class IdentityLookupResult
{
    public function __construct(
        public string $identityId,
        public string $type,
        public string $status,
    ) {
    }
}
