<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure\Persistence;

use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleRepository;

final class InMemoryRoleRepository implements RoleRepository
{
    /** @var array<string, Role> */
    private array $roles = [];

    public function save(Role $role): void
    {
        $this->roles[(string) $role->id()] = $role;
    }

    public function findById(RoleId $id): ?Role
    {
        return $this->roles[(string) $id] ?? null;
    }

    public function findByName(string $name): ?Role
    {
        foreach ($this->roles as $role) {
            if ($role->name() === $name) {
                return $role;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        return array_values($this->roles);
    }
}
