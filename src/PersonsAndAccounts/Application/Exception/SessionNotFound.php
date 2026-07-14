<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Exception;

use RuntimeException;

final class SessionNotFound extends RuntimeException
{
    public static function withId(string $sessionId): self
    {
        return new self(sprintf('Session "%s" was not found.', $sessionId));
    }
}
