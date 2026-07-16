<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Exception;

use RuntimeException;

final class RoleNotFound extends RuntimeException
{
    public static function withId(string $roleId): self
    {
        return new self(sprintf('Role "%s" was not found.', $roleId));
    }
}
