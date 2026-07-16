<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure;

use Gamad\Core\AccessControl\Application\Command\EvaluateAccess;
use Gamad\Core\AccessControl\Application\Command\EvaluateAccessHandler;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDecision;
use Gamad\Core\Shared\Contract\IdentityId;

/**
 * The real engine behind AccessControlGateway (ADR-0021) — the only class
 * in this bounded context that both the Shared contract types
 * (IdentityId, AccessDecision) and this context's own Application layer
 * (EvaluateAccessHandler) are allowed to meet, converting the domain's
 * canonical AccessDecision into the shape Shared exposes to callers.
 * Wired in at public/index.php only after DIRECTIVE-007 Task 9 validates
 * the engine in production (Task 10) — until then,
 * PermissiveAccessControlGateway remains active.
 */
final readonly class RbacAccessControlGateway implements AccessControlGateway
{
    public function __construct(private EvaluateAccessHandler $evaluateAccess)
    {
    }

    public function can(IdentityId $actor, string $action, IdentityId $context): AccessDecision
    {
        $decision = ($this->evaluateAccess)(new EvaluateAccess((string) $actor, $action, (string) $context));

        return $decision->allowed ? AccessDecision::allow($decision->reason) : AccessDecision::deny($decision->reason);
    }
}
