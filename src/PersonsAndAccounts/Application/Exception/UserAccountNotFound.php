<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Exception;

use RuntimeException;

final class UserAccountNotFound extends RuntimeException
{
    public static function withId(string $accountId): self
    {
        return new self(sprintf('User account "%s" was not found.', $accountId));
    }
}
