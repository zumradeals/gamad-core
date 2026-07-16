<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

use Gamad\Core\AccessControl\Application\AccessControlLookup;
use Gamad\Core\AccessControl\Application\AtomicRoleAssignmentPersister;
use Gamad\Core\AccessControl\Application\Exception\OrganizationNotFound;
use Gamad\Core\AccessControl\Application\Exception\PersonNotFound;
use Gamad\Core\AccessControl\Application\Exception\RoleAssignmentAlreadyActive;
use Gamad\Core\AccessControl\Application\Exception\RoleNotFound;
use Gamad\Core\AccessControl\Application\Exception\SelfAssignmentNotAllowed;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleAssignmentRepository;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleRepository;
use Gamad\Core\AccessControl\Domain\RoleStatus;
use Gamad\Core\Shared\Contract\AccessDenied;

/**
 * GENESIS-013 §6 invariant 5 — a role never assigns itself: the requesting
 * actor must be distinct from the person receiving the role and must hold
 * `role:assign` in the target organization, checked here via
 * EvaluateAccessHandler (this context's own engine, not the Shared gateway
 * — AccessControl is the engine's home, it never calls back into itself
 * through the indirection other contexts use). The sole exception is the
 * institutional bootstrap (`$command->isBootstrap`), reachable only from
 * bin/bootstrap-access-control, never from an HTTP route (Task 9).
 */
final readonly class AssignRoleHandler
{
    public function __construct(
        private RoleRepository $roles,
        private RoleAssignmentRepository $assignments,
        private AccessControlLookup $lookup,
        private EvaluateAccessHandler $evaluateAccess,
        private AtomicRoleAssignmentPersister $persister,
    ) {
    }

    public function __invoke(AssignRole $command): RoleAssignment
    {
        if (!$command->isBootstrap) {
            if ($command->actorId === $command->personId) {
                throw SelfAssignmentNotAllowed::forActor($command->actorId);
            }

            $decision = ($this->evaluateAccess)(new EvaluateAccess($command->actorId, 'role:assign', $command->organizationId));
            if (!$decision->allowed) {
                throw AccessDenied::forDecision('role:assign', $decision->reason);
            }
        }

        if (!$this->lookup->personExists($command->personId)) {
            throw PersonNotFound::withId($command->personId);
        }
        if (!$this->lookup->organizationExists($command->organizationId)) {
            throw OrganizationNotFound::withId($command->organizationId);
        }

        $roleId = new RoleId($command->roleId);
        $role = $this->roles->findById($roleId);
        if ($role === null || $role->status() !== RoleStatus::Active) {
            throw RoleNotFound::withId($command->roleId);
        }

        // Applicative half of the "at most one active assignment per
        // triplet" invariant — the partial unique index (GENESIS-014 §D) is
        // the structural half.
        if ($this->assignments->findActive($roleId, $command->personId, $command->organizationId) !== null) {
            throw RoleAssignmentAlreadyActive::forTriplet($command->roleId, $command->personId, $command->organizationId);
        }

        $assignment = RoleAssignment::create(RoleAssignmentId::generate(), $roleId, $command->personId, $command->organizationId);
        $this->persister->persist($assignment);

        return $assignment;
    }
}
