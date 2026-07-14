<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application;

use Gamad\Core\PersonsAndAccounts\Domain\Session;

/**
 * Carries the raw bearer token exactly once, on its way out of
 * AuthenticateHandler to the HTTP layer — Session itself never holds
 * anything but the token's hash (ADR-0018 §1).
 */
final readonly class AuthenticationResult
{
    public function __construct(
        public Session $session,
        public string $token,
    ) {
    }
}
