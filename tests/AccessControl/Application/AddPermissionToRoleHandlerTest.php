<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Application;

use Gamad\Core\AccessControl\Application\AtomicRolePersister;
use Gamad\Core\AccessControl\Application\Command\AddPermissionToRole;
use Gamad\Core\AccessControl\Application\Command\AddPermissionToRoleHandler;
use Gamad\Core\AccessControl\Application\Exception\PermissionNotFound;
use Gamad\Core\AccessControl\Application\Exception\RoleNotFound;
use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryPermissionRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryRoleRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class AddPermissionToRoleHandlerTest extends TestCase
{
    public function test_it_adds_a_permission_to_a_role(): void
    {
        $roles = new InMemoryRoleRepository();
        $permissions = new InMemoryPermissionRepository();
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $roles->save($role);
        $permission = new Permission(PermissionId::generate(), 'membership:create', 'Create a membership');
        $permissions->save($permission);
        $handler = new AddPermissionToRoleHandler(
            roles: $roles,
            permissions: $permissions,
            persister: new AtomicRolePersister($roles, new InMemoryOutboxRepository(), new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $updated = $handler(new AddPermissionToRole((string) $role->id(), (string) $permission->id));

        self::assertTrue($updated->permissionIds()[0]->equals($permission->id));
    }

    public function test_it_rejects_an_unknown_role(): void
    {
        $handler = new AddPermissionToRoleHandler(
            roles: new InMemoryRoleRepository(),
            permissions: new InMemoryPermissionRepository(),
            persister: new AtomicRolePersister(new InMemoryRoleRepository(), new InMemoryOutboxRepository(), new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $this->expectException(RoleNotFound::class);

        $handler(new AddPermissionToRole(RoleId::generate()->value, PermissionId::generate()->value));
    }

    public function test_it_rejects_an_unknown_permission(): void
    {
        $roles = new InMemoryRoleRepository();
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $roles->save($role);
        $handler = new AddPermissionToRoleHandler(
            roles: $roles,
            permissions: new InMemoryPermissionRepository(),
            persister: new AtomicRolePersister($roles, new InMemoryOutboxRepository(), new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $this->expectException(PermissionNotFound::class);

        $handler(new AddPermissionToRole((string) $role->id(), PermissionId::generate()->value));
    }
}
