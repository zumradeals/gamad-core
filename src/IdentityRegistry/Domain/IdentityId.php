<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Domain;

use InvalidArgumentException;

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

    /** ADR-0017 §3 — the realm segment, e.g. "GAT" for "GAM-GAT-PER-000001". */
    public function realm(): string
    {
        return explode('-', $this->value)[1];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
