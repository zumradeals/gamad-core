<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Domain;

use DomainException;
use Gamad\Core\AccessControl\Domain\Event\PermissionAddedToRole;
use Gamad\Core\AccessControl\Domain\Event\RoleCreated;
use Gamad\Core\AccessControl\Domain\Event\RoleDeprecated;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Domain\RoleStatus;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_it_creates_an_active_role(): void
    {
        $id = RoleId::generate();

        $role = Role::create($id, 'org_admin', RoleScope::Organization);

        self::assertTrue($role->id()->equals($id));
        self::assertSame('org_admin', $role->name());
        self::assertSame(RoleScope::Organization, $role->scope());
        self::assertSame(RoleStatus::Active, $role->status());
        self::assertSame([], $role->permissionIds());
        self::assertInstanceOf(RoleCreated::class, $role->releaseEvents()[0]);
    }

    public function test_it_adds_a_permission(): void
    {
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $role->releaseEvents();
        $permissionId = PermissionId::generate();

        $role->addPermission($permissionId);

        self::assertTrue($role->permissionIds()[0]->equals($permissionId));
        self::assertInstanceOf(PermissionAddedToRole::class, $role->releaseEvents()[0]);
    }

    public function test_adding_the_same_permission_twice_is_idempotent(): void
    {
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $permissionId = PermissionId::generate();
        $role->addPermission($permissionId);
        $role->releaseEvents();

        $role->addPermission($permissionId);

        self::assertCount(1, $role->permissionIds());
        self::assertSame([], $role->releaseEvents());
    }

    public function test_it_deprecates_an_active_role(): void
    {
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $role->releaseEvents();

        $role->deprecate();

        self::assertSame(RoleStatus::Deprecated, $role->status());
        self::assertInstanceOf(RoleDeprecated::class, $role->releaseEvents()[0]);
    }

    public function test_it_rejects_deprecating_an_already_deprecated_role(): void
    {
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $role->deprecate();

        $this->expectException(DomainException::class);

        $role->deprecate();
    }

    public function test_it_rejects_adding_a_permission_to_a_deprecated_role(): void
    {
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $role->deprecate();

        $this->expectException(DomainException::class);

        $role->addPermission(PermissionId::generate());
    }
}
