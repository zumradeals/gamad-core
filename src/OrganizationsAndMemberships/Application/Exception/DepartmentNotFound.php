<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Exception;

use RuntimeException;

final class DepartmentNotFound extends RuntimeException
{
    public static function withId(string $departmentId, string $organizationId): self
    {
        return new self(sprintf('Department "%s" was not found in organization "%s".', $departmentId, $organizationId));
    }
}
