<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Exception;

use RuntimeException;

final class PersonNotFound extends RuntimeException
{
    public static function withId(string $personId): self
    {
        return new self(sprintf('Person "%s" was not found.', $personId));
    }
}
