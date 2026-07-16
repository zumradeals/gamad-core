<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

interface PermissionRepository
{
    public function save(Permission $permission): void;

    public function findById(PermissionId $id): ?Permission;

    public function findByName(string $name): ?Permission;

    /** @return list<Permission> */
    public function findAll(): array;
}
