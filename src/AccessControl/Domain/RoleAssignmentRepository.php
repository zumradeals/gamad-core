<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

interface RoleAssignmentRepository
{
    public function save(RoleAssignment $assignment): void;

    public function findById(RoleAssignmentId $id): ?RoleAssignment;

    public function findActive(RoleId $roleId, string $personId, string $organizationId): ?RoleAssignment;

    /** @return list<RoleAssignment> */
    public function findActiveByPerson(string $personId): array;
}
