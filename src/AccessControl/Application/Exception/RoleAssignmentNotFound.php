<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Exception;

use RuntimeException;

final class RoleAssignmentNotFound extends RuntimeException
{
    public static function withId(string $assignmentId): self
    {
        return new self(sprintf('Role assignment "%s" was not found.', $assignmentId));
    }
}
