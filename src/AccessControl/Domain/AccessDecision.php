<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

/**
 * Sealed two-state value object (GENESIS-013 §6 invariant 1) — the
 * canonical source (ADR-0021); Shared\Contract\AccessDecision mirrors this
 * shape for callers outside this bounded context, and
 * RbacAccessControlGateway converts between the two.
 */
final readonly class AccessDecision
{
    private function __construct(
        public bool $allowed,
        public string $reason,
    ) {
    }

    public static function allow(string $reason): self
    {
        return new self(true, $reason);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
