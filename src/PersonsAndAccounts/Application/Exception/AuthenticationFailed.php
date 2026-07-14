<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Exception;

use RuntimeException;

/**
 * Deliberately generic (Task 4, ADR-0018) — never distinguishes "no such
 * account" from "wrong password" in the message, so a caller cannot probe
 * for the existence of an account.
 */
final class AuthenticationFailed extends RuntimeException
{
    public static function invalidCredentials(): self
    {
        return new self('Invalid credentials.');
    }
}
