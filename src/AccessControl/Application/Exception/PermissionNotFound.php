<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Exception;

use RuntimeException;

final class PermissionNotFound extends RuntimeException
{
    public static function withId(string $permissionId): self
    {
        return new self(sprintf('Permission "%s" was not found.', $permissionId));
    }
}
