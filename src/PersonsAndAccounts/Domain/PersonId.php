<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

use InvalidArgumentException;

/**
 * A Person's identifier is the identity_id already issued by the Identity
 * Registry for that person (GENESIS-010 §B) — this context never mints its
 * own identifier. The format is duplicated from IdentityRegistry\Domain\IdentityId
 * deliberately: each bounded context owns its own vocabulary (ADR-0008), so
 * this value object is not shared across the context boundary.
 */
final readonly class PersonId
{
    private const PATTERN = '/^GAM-[A-Z0-9]{2,6}-[A-Z]{3}-[0-9]{6,}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Invalid GAMAD identity identifier for a Person.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
