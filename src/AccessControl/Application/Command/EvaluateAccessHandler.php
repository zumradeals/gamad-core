<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

use DateTimeImmutable;
use Gamad\Core\AccessControl\Domain\AccessControlEngine;
use Gamad\Core\AccessControl\Domain\AccessDecision;
use Gamad\Core\AccessControl\Domain\AccessRequest;
use Gamad\Core\AccessControl\Domain\Event\AccessDecisionMade;
use Gamad\Core\AccessControl\Domain\PermissionRepository;
use Gamad\Core\AccessControl\Domain\RoleAssignmentRepository;
use Gamad\Core\AccessControl\Domain\RoleGrant;
use Gamad\Core\AccessControl\Domain\RoleRepository;
use Gamad\Core\AccessControl\Domain\RoleStatus;
use Gamad\Core\Shared\Contract\IdentityId;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;

/**
 * The applicative entry point of the engine (ADR-0021, GENESIS-013 §6
 * invariant 2 / ADR-0022) — loads the actor's active RoleAssignments,
 * resolves each into a RoleGrant, evaluates, and audits every decision
 * (ALLOW and DENY alike) to the dedicated `access_decisions` Outbox queue.
 * $accessDecisionsOutbox is deliberately a distinct OutboxRepository
 * instance from the one used by AtomicRolePersister /
 * AtomicRoleAssignmentPersister — wired to the access_decisions_outbox
 * table at the composition root (ADR-0022 §4).
 */
final readonly class EvaluateAccessHandler
{
    public function __construct(
        private RoleAssignmentRepository $assignments,
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private AccessControlEngine $engine,
        private OutboxRepository $accessDecisionsOutbox,
    ) {
    }

    public function __invoke(EvaluateAccess $command): AccessDecision
    {
        $grants = [];
        foreach ($this->assignments->findActiveByPerson($command->actorId) as $assignment) {
            $role = $this->roles->findById($assignment->roleId());
            if ($role === null || $role->status() !== RoleStatus::Active) {
                continue;
            }

            $permissionNames = [];
            foreach ($role->permissionIds() as $permissionId) {
                $permission = $this->permissions->findById($permissionId);
                if ($permission !== null) {
                    $permissionNames[] = $permission->name;
                }
            }

            $grants[] = new RoleGrant($role, $permissionNames, $assignment->organizationId());
        }

        $request = new AccessRequest(
            new IdentityId($command->actorId),
            $command->action,
            new IdentityId($command->contextId),
        );
        $decision = $this->engine->evaluate($request, $grants);

        $evaluatedAt = new DateTimeImmutable();
        $this->accessDecisionsOutbox->append(OutboxMessage::fromDomainEvent(new AccessDecisionMade(
            $command->actorId,
            $command->action,
            $command->contextId,
            $decision->allowed,
            $decision->reason,
            $evaluatedAt,
        )));

        return $decision;
    }
}
