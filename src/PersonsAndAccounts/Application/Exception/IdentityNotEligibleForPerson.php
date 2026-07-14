<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Exception;

use RuntimeException;

final class IdentityNotEligibleForPerson extends RuntimeException
{
    public static function notFound(string $identityId): self
    {
        return new self(sprintf('Identity "%s" does not exist in this realm.', $identityId));
    }

    public static function wrongType(string $identityId, string $type): self
    {
        return new self(sprintf('Identity "%s" is not a person identity (type: %s).', $identityId, $type));
    }

    public static function notActive(string $identityId, string $status): self
    {
        return new self(sprintf('Identity "%s" is not active (status: %s).', $identityId, $status));
    }
}
