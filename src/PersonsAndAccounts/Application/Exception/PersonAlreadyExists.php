<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Exception;

use RuntimeException;

final class PersonAlreadyExists extends RuntimeException
{
    public static function withId(string $personId): self
    {
        return new self(sprintf('Person "%s" is already registered.', $personId));
    }
}
