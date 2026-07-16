<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Contract;

/**
 * Sealed two-state value object (GENESIS-013 §6 invariant 1) — PHP has no
 * `sealed` keyword, so a private constructor plus two named factories are
 * what make a third state unreachable. Mirrors
 * AccessControl\Domain\AccessDecision (ADR-0021): the domain is the
 * canonical source, this is the shape exposed across the contract
 * boundary, produced by RbacAccessControlGateway from the domain decision.
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
