<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Exception;

use RuntimeException;

final class MembershipNotFound extends RuntimeException
{
    public static function withId(string $membershipId): self
    {
        return new self(sprintf('Membership "%s" was not found.', $membershipId));
    }
}
