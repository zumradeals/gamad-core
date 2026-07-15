<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Exception;

use RuntimeException;

/**
 * The applicative half of the "at most one active membership per person per
 * organization" invariant — the partial unique index (ADR-0020) is the
 * structural half (GENESIS-011 §4 invariant 6).
 */
final class MembershipAlreadyActive extends RuntimeException
{
    public static function forPersonAndOrganization(string $personId, string $organizationId): self
    {
        return new self(sprintf('Person "%s" already has an active membership in organization "%s".', $personId, $organizationId));
    }
}
