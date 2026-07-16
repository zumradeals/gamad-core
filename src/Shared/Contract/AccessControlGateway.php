<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Contract;

/**
 * The single door through which every other bounded context asks Access
 * Control a question (ADR-0021, GENESIS-014 §C) — never a direct
 * dependency on src/AccessControl/. The real engine
 * (AccessControl\Infrastructure\RbacAccessControlGateway) and the
 * provisional one (PermissiveAccessControlGateway) are interchangeable
 * behind this interface; only public/index.php decides which is wired in.
 */
interface AccessControlGateway
{
    public function can(IdentityId $actor, string $action, IdentityId $context): AccessDecision;
}
