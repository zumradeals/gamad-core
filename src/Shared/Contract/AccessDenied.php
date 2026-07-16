<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Contract;

use RuntimeException;

/**
 * Raised by a handler's AccessControlGateway checkpoint when the decision
 * is DENY (ADR-0021 Task 2). Callers map it to HTTP 403 — never a 404 or
 * 409, so a denied action is never confused with a business rejection.
 */
final class AccessDenied extends RuntimeException
{
    public static function forDecision(string $action, string $reason): self
    {
        return new self(sprintf('Access denied for action "%s": %s', $action, $reason));
    }
}
