<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Contract;

use InvalidArgumentException;

/**
 * The identity identifier type used strictly at bounded-context contract
 * boundaries (ADR-0021), e.g. AccessControlGateway::can(). Shared exposes
 * this as a plain, technical value carrying only the format rule from
 * ADR-0017 — never IdentityRegistry\Domain\IdentityId: importing that
 * would violate ADR-0013. Every bounded context that needs its own richer
 * identifier duplicates this format independently (see OrganizationId,
 * PersonId) — this copy exists only so contracts living in Shared have a
 * type to speak in.
 */
final readonly class IdentityId
{
    private const PATTERN = '/^GAM-[A-Z0-9]{2,6}-[A-Z]{3}-[0-9]{6,}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Invalid GAMAD identity identifier.');
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
