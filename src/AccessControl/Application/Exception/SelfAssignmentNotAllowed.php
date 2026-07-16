<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Exception;

use RuntimeException;

/**
 * GENESIS-013 §6 invariant 5 — a role assignment always requires a distinct
 * actor holding `role:assign`; the sole exception is the institutional
 * bootstrap (bin/bootstrap-access-control), never a handler reachable from
 * HTTP (DIRECTIVE-007 Task 5).
 */
final class SelfAssignmentNotAllowed extends RuntimeException
{
    public static function forActor(string $actorId): self
    {
        return new self(sprintf('Actor "%s" cannot assign a role to themselves.', $actorId));
    }
}
