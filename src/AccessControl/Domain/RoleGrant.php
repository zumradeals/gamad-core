<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

/**
 * A pre-resolved input to AccessControlEngine::evaluate() — one active
 * RoleAssignment, joined with its Role's current permission names and the
 * organization it was granted in. Building this join (RoleAssignment +
 * Role + Permission names) is Application/Infrastructure's job
 * (EvaluateAccessHandler); the engine itself never queries anything
 * (GENESIS-013 §6 invariant 8).
 */
final readonly class RoleGrant
{
    /** @param list<string> $permissionNames */
    public function __construct(
        public Role $role,
        public array $permissionNames,
        public string $organizationId,
    ) {
    }
}
