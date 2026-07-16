<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Exception;

use RuntimeException;

/**
 * The applicative half of the "at most one active assignment per triplet"
 * invariant — the partial unique index (GENESIS-014 §D) is the structural
 * half, same patron as ADR-0020.
 */
final class RoleAssignmentAlreadyActive extends RuntimeException
{
    public static function forTriplet(string $roleId, string $personId, string $organizationId): self
    {
        return new self(sprintf('Person "%s" already holds role "%s" in organization "%s".', $personId, $roleId, $organizationId));
    }
}
