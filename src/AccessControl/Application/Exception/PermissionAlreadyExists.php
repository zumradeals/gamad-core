<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Exception;

use RuntimeException;

final class PermissionAlreadyExists extends RuntimeException
{
    public static function withName(string $name): self
    {
        return new self(sprintf('Permission "%s" is already registered.', $name));
    }
}
