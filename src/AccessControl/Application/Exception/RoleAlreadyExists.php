<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Exception;

use RuntimeException;

final class RoleAlreadyExists extends RuntimeException
{
    public static function withName(string $name): self
    {
        return new self(sprintf('Role "%s" is already registered.', $name));
    }
}
