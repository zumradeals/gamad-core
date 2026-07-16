<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

interface RoleRepository
{
    public function save(Role $role): void;

    public function findById(RoleId $id): ?Role;

    public function findByName(string $name): ?Role;

    /** @return list<Role> */
    public function findAll(): array;
}
