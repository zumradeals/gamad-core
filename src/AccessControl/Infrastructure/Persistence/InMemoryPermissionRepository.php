<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure\Persistence;

use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\PermissionRepository;

final class InMemoryPermissionRepository implements PermissionRepository
{
    /** @var array<string, Permission> */
    private array $permissions = [];

    public function save(Permission $permission): void
    {
        $this->permissions[(string) $permission->id] = $permission;
    }

    public function findById(PermissionId $id): ?Permission
    {
        return $this->permissions[(string) $id] ?? null;
    }

    public function findByName(string $name): ?Permission
    {
        foreach ($this->permissions as $permission) {
            if ($permission->name === $name) {
                return $permission;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        return array_values($this->permissions);
    }
}
