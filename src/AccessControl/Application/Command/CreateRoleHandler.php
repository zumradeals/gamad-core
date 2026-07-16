<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

use Gamad\Core\AccessControl\Application\AtomicRolePersister;
use Gamad\Core\AccessControl\Application\Exception\RoleAlreadyExists;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleRepository;
use Gamad\Core\AccessControl\Domain\RoleScope;

final readonly class CreateRoleHandler
{
    public function __construct(
        private RoleRepository $roles,
        private AtomicRolePersister $persister,
    ) {
    }

    public function __invoke(CreateRole $command): Role
    {
        if ($this->roles->findByName($command->name) !== null) {
            throw RoleAlreadyExists::withName($command->name);
        }

        $role = Role::create(RoleId::generate(), $command->name, RoleScope::from($command->scope));
        $this->persister->persist($role);

        return $role;
    }
}
