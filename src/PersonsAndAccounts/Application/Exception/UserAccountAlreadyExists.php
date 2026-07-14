<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Exception;

use RuntimeException;

/** GENESIS-010 §A — a Person never has more than one UserAccount at the Core level. */
final class UserAccountAlreadyExists extends RuntimeException
{
    public static function forPerson(string $personId): self
    {
        return new self(sprintf('Person "%s" already has a user account.', $personId));
    }
}
