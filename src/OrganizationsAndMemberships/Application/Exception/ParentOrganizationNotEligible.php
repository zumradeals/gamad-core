<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Exception;

use RuntimeException;

final class ParentOrganizationNotEligible extends RuntimeException
{
    public static function notFound(string $organizationId): self
    {
        return new self(sprintf('Parent organization "%s" does not exist.', $organizationId));
    }

    public static function notActive(string $organizationId): self
    {
        return new self(sprintf('Parent organization "%s" is not active.', $organizationId));
    }
}
