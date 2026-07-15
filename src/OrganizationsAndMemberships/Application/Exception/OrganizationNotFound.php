<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Exception;

use RuntimeException;

final class OrganizationNotFound extends RuntimeException
{
    public static function withId(string $organizationId): self
    {
        return new self(sprintf('Organization "%s" was not found.', $organizationId));
    }
}
