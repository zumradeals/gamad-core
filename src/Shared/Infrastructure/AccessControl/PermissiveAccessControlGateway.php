<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\AccessControl;

use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDecision;
use Gamad\Core\Shared\Contract\IdentityId;

/**
 * Provisional implementation (ADR-0021) active while the real Access
 * Control engine (RbacAccessControlGateway) is being built — always
 * ALLOW, and publishes no audit event, so its call volume never pollutes
 * the audit chain (ADR-0022 §3). Replaced in public/index.php at
 * DIRECTIVE-007 Task 10, once the engine is validated in production.
 */
final class PermissiveAccessControlGateway implements AccessControlGateway
{
    public function can(IdentityId $actor, string $action, IdentityId $context): AccessDecision
    {
        return AccessDecision::allow('permissive_gateway');
    }
}
