<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

/**
 * GENESIS-013 §6 invariant 8 / GENESIS-014 §B — a pure, stateless domain
 * service: the same request and the same grants always produce the same
 * decision, and it never queries anything itself. The caller
 * (EvaluateAccessHandler) has already loaded the actor's active
 * RoleAssignments and resolved each into a RoleGrant.
 *
 * No organizational inheritance is evaluated here (GENESIS-013 §2.3 limited
 * read inheritance is a read-permission concern for a later sub-phase,
 * never implemented in the engine itself) — a grant applies only to its
 * own organization_id, except a `realm`-scoped role, which applies to any
 * context (the sole transversal exception, GENESIS-013 §2.2).
 */
final class AccessControlEngine
{
    /** @param list<RoleGrant> $grants */
    public function evaluate(AccessRequest $request, array $grants): AccessDecision
    {
        foreach ($grants as $grant) {
            if ($grant->role->status() !== RoleStatus::Active) {
                continue;
            }

            if (!in_array($request->action, $grant->permissionNames, true)) {
                continue;
            }

            if ($grant->role->scope() === RoleScope::Realm) {
                return AccessDecision::allow($grant->role->name());
            }

            if ($grant->organizationId === (string) $request->context) {
                return AccessDecision::allow($grant->role->name());
            }
        }

        return AccessDecision::deny('no_matching_role');
    }
}
