<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

use Gamad\Core\Shared\Contract\IdentityId;

/**
 * GENESIS-014 §A — transient: enters the engine, produces an
 * AccessDecision, and disappears. Never persisted — no `access_requests`
 * table exists. Reuses Shared\Contract\IdentityId, the same type
 * AccessControlGateway::can() speaks (ADR-0021) — Domain may depend on
 * Shared, unlike the reverse (ADR-0013).
 */
final readonly class AccessRequest
{
    public function __construct(
        public IdentityId $actor,
        public string $action,
        public IdentityId $context,
    ) {
    }
}
