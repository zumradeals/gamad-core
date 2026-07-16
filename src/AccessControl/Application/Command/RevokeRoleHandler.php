<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

use Gamad\Core\AccessControl\Application\AtomicRoleAssignmentPersister;
use Gamad\Core\AccessControl\Application\Exception\RoleAssignmentNotFound;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleAssignmentRepository;

final readonly class RevokeRoleHandler
{
    public function __construct(
        private RoleAssignmentRepository $assignments,
        private AtomicRoleAssignmentPersister $persister,
    ) {
    }

    public function __invoke(RevokeRole $command): RoleAssignment
    {
        $assignment = $this->assignments->findById(new RoleAssignmentId($command->assignmentId));
        if ($assignment === null) {
            throw RoleAssignmentNotFound::withId($command->assignmentId);
        }

        $assignment->revoke();
        $this->persister->persist($assignment);

        return $assignment;
    }
}
