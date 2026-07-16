<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

use Gamad\Core\AccessControl\Application\AtomicRolePersister;
use Gamad\Core\AccessControl\Application\Exception\PermissionNotFound;
use Gamad\Core\AccessControl\Application\Exception\RoleNotFound;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\PermissionRepository;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleRepository;

final readonly class AddPermissionToRoleHandler
{
    public function __construct(
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private AtomicRolePersister $persister,
    ) {
    }

    public function __invoke(AddPermissionToRole $command): Role
    {
        $role = $this->roles->findById(new RoleId($command->roleId));
        if ($role === null) {
            throw RoleNotFound::withId($command->roleId);
        }

        $permissionId = new PermissionId($command->permissionId);
        if ($this->permissions->findById($permissionId) === null) {
            throw PermissionNotFound::withId($command->permissionId);
        }

        $role->addPermission($permissionId);
        $this->persister->persist($role);

        return $role;
    }
}
