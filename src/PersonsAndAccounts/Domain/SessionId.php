<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

use InvalidArgumentException;

final readonly class SessionId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new InvalidArgumentException('Invalid Session UUID.');
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::v4());
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
