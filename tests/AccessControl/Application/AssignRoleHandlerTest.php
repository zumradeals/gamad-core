<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Application;

use Gamad\Core\AccessControl\Application\AtomicRoleAssignmentPersister;
use Gamad\Core\AccessControl\Application\Command\AssignRole;
use Gamad\Core\AccessControl\Application\Command\AssignRoleHandler;
use Gamad\Core\AccessControl\Application\Command\EvaluateAccessHandler;
use Gamad\Core\AccessControl\Application\Exception\OrganizationNotFound;
use Gamad\Core\AccessControl\Application\Exception\PersonNotFound;
use Gamad\Core\AccessControl\Application\Exception\RoleAssignmentAlreadyActive;
use Gamad\Core\AccessControl\Application\Exception\RoleNotFound;
use Gamad\Core\AccessControl\Application\Exception\SelfAssignmentNotAllowed;
use Gamad\Core\AccessControl\Domain\AccessControlEngine;
use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleAssignmentStatus;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryAccessControlLookup;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryPermissionRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryRoleAssignmentRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryRoleRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class AssignRoleHandlerTest extends TestCase
{
    private function assignRolePermission(): Permission
    {
        return new Permission(PermissionId::generate(), 'role:assign', 'Assign a role');
    }

    /** @return array{0: AssignRoleHandler, 1: InMemoryRoleRepository, 2: InMemoryRoleAssignmentRepository, 3: InMemoryAccessControlLookup, 4: InMemoryPermissionRepository} */
    private function handler(): array
    {
        $roles = new InMemoryRoleRepository();
        $permissions = new InMemoryPermissionRepository();
        $assignments = new InMemoryRoleAssignmentRepository();
        $lookup = new InMemoryAccessControlLookup();
        $lookup->registerPerson('GAM-GAT-PER-000002');
        $lookup->registerOrganization('GAM-GAT-ORG-000001');

        $evaluateAccess = new EvaluateAccessHandler($assignments, $roles, $permissions, new AccessControlEngine(), new InMemoryOutboxRepository());

        $handler = new AssignRoleHandler(
            roles: $roles,
            assignments: $assignments,
            lookup: $lookup,
            evaluateAccess: $evaluateAccess,
            persister: new AtomicRoleAssignmentPersister($assignments, new InMemoryOutboxRepository(), new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        return [$handler, $roles, $assignments, $lookup, $permissions];
    }

    public function test_it_assigns_a_role_when_the_actor_holds_role_assign(): void
    {
        [$handler, $roles, $assignments, , $permissions] = $this->handler();
        $permission = $this->assignRolePermission();
        $permissions->save($permission);
        $granterRole = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $granterRole->addPermission($permission->id);
        $roles->save($granterRole);
        $assignments->save(RoleAssignment::create(RoleAssignmentId::generate(), $granterRole->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));

        $targetRole = Role::create(RoleId::generate(), 'member_viewer', RoleScope::Organization);
        $roles->save($targetRole);

        $assignment = $handler(new AssignRole((string) $targetRole->id(), 'GAM-GAT-PER-000002', 'GAM-GAT-ORG-000001', 'GAM-GAT-PER-000001'));

        self::assertSame('GAM-GAT-PER-000002', $assignment->personId());
        self::assertSame(RoleAssignmentStatus::Active, $assignment->status());
    }

    public function test_it_rejects_self_assignment_outside_bootstrap(): void
    {
        [$handler, $roles] = $this->handler();
        $role = Role::create(RoleId::generate(), 'member_viewer', RoleScope::Organization);
        $roles->save($role);

        $this->expectException(SelfAssignmentNotAllowed::class);

        $handler(new AssignRole((string) $role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001', 'GAM-GAT-PER-000001'));
    }

    public function test_bootstrap_allows_self_assignment(): void
    {
        [$handler, $roles, , $lookup] = $this->handler();
        $lookup->registerPerson('GAM-GAT-PER-000001');
        $role = Role::create(RoleId::generate(), 'superadmin', RoleScope::Realm);
        $roles->save($role);

        $assignment = $handler(new AssignRole((string) $role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001', 'GAM-GAT-PER-000001', isBootstrap: true));

        self::assertSame('GAM-GAT-PER-000001', $assignment->personId());
    }

    public function test_it_denies_when_the_actor_lacks_role_assign(): void
    {
        [$handler, $roles] = $this->handler();
        $role = Role::create(RoleId::generate(), 'member_viewer', RoleScope::Organization);
        $roles->save($role);

        $this->expectException(AccessDenied::class);

        $handler(new AssignRole((string) $role->id(), 'GAM-GAT-PER-000002', 'GAM-GAT-ORG-000001', 'GAM-GAT-PER-000999'));
    }

    public function test_it_rejects_an_unknown_person(): void
    {
        [$handler, $roles, $assignments, , $permissions] = $this->handler();
        $permission = $this->assignRolePermission();
        $permissions->save($permission);
        $granterRole = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $granterRole->addPermission($permission->id);
        $roles->save($granterRole);
        $assignments->save(RoleAssignment::create(RoleAssignmentId::generate(), $granterRole->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));
        $targetRole = Role::create(RoleId::generate(), 'member_viewer', RoleScope::Organization);
        $roles->save($targetRole);

        $this->expectException(PersonNotFound::class);

        $handler(new AssignRole((string) $targetRole->id(), 'GAM-GAT-PER-999999', 'GAM-GAT-ORG-000001', 'GAM-GAT-PER-000001'));
    }

    public function test_it_rejects_an_unknown_organization(): void
    {
        // A realm-scoped granter is required here: an organization-scoped
        // grant can never authorize a context that does not exist, so the
        // role:assign check itself would deny first (correctly) rather than
        // reach the organization-existence check this test targets.
        [$handler, $roles, $assignments, , $permissions] = $this->handler();
        $permission = $this->assignRolePermission();
        $permissions->save($permission);
        $granterRole = Role::create(RoleId::generate(), 'superadmin', RoleScope::Realm);
        $granterRole->addPermission($permission->id);
        $roles->save($granterRole);
        $assignments->save(RoleAssignment::create(RoleAssignmentId::generate(), $granterRole->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));
        $targetRole = Role::create(RoleId::generate(), 'member_viewer', RoleScope::Organization);
        $roles->save($targetRole);

        $this->expectException(OrganizationNotFound::class);

        $handler(new AssignRole((string) $targetRole->id(), 'GAM-GAT-PER-000002', 'GAM-GAT-ORG-999999', 'GAM-GAT-PER-000001'));
    }

    public function test_it_rejects_an_unknown_role(): void
    {
        [$handler, $roles, $assignments, , $permissions] = $this->handler();
        $permission = $this->assignRolePermission();
        $permissions->save($permission);
        $granterRole = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $granterRole->addPermission($permission->id);
        $roles->save($granterRole);
        $assignments->save(RoleAssignment::create(RoleAssignmentId::generate(), $granterRole->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));

        $this->expectException(RoleNotFound::class);

        $handler(new AssignRole(RoleId::generate()->value, 'GAM-GAT-PER-000002', 'GAM-GAT-ORG-000001', 'GAM-GAT-PER-000001'));
    }

    public function test_it_rejects_a_duplicate_active_assignment(): void
    {
        [$handler, $roles, $assignments, , $permissions] = $this->handler();
        $permission = $this->assignRolePermission();
        $permissions->save($permission);
        $granterRole = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $granterRole->addPermission($permission->id);
        $roles->save($granterRole);
        $assignments->save(RoleAssignment::create(RoleAssignmentId::generate(), $granterRole->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));
        $targetRole = Role::create(RoleId::generate(), 'member_viewer', RoleScope::Organization);
        $roles->save($targetRole);
        $handler(new AssignRole((string) $targetRole->id(), 'GAM-GAT-PER-000002', 'GAM-GAT-ORG-000001', 'GAM-GAT-PER-000001'));

        $this->expectException(RoleAssignmentAlreadyActive::class);

        $handler(new AssignRole((string) $targetRole->id(), 'GAM-GAT-PER-000002', 'GAM-GAT-ORG-000001', 'GAM-GAT-PER-000001'));
    }
}
