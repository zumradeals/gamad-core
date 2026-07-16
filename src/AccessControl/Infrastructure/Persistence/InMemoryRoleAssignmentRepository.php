<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure\Persistence;

use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleAssignmentRepository;
use Gamad\Core\AccessControl\Domain\RoleAssignmentStatus;
use Gamad\Core\AccessControl\Domain\RoleId;

final class InMemoryRoleAssignmentRepository implements RoleAssignmentRepository
{
    /** @var array<string, RoleAssignment> */
    private array $assignments = [];

    public function save(RoleAssignment $assignment): void
    {
        $this->assignments[(string) $assignment->id()] = $assignment;
    }

    public function findById(RoleAssignmentId $id): ?RoleAssignment
    {
        return $this->assignments[(string) $id] ?? null;
    }

    public function findActive(RoleId $roleId, string $personId, string $organizationId): ?RoleAssignment
    {
        foreach ($this->assignments as $assignment) {
            if (
                $assignment->status() === RoleAssignmentStatus::Active
                && $assignment->roleId()->equals($roleId)
                && $assignment->personId() === $personId
                && $assignment->organizationId() === $organizationId
            ) {
                return $assignment;
            }
        }

        return null;
    }

    public function findActiveByPerson(string $personId): array
    {
        return array_values(array_filter(
            $this->assignments,
            static fn (RoleAssignment $candidate): bool => $candidate->status() === RoleAssignmentStatus::Active
                && $candidate->personId() === $personId,
        ));
    }
}
