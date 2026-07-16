<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Infrastructure;

use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Domain\RoleStatus;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlPermissionRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlRoleRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlRoleRepositoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $dsn = getenv('GAMAD_TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set GAMAD_TEST_PG_DSN to run PostgreSQL integration tests.');
        }

        $this->connection = new PDO(
            $dsn,
            getenv('GAMAD_TEST_PG_USER') ?: null,
            getenv('GAMAD_TEST_PG_PASSWORD') ?: null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $this->connection->exec('DROP TABLE IF EXISTS role_assignments CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS role_permissions CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS roles CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS permissions CASCADE');

        foreach ([22, 23, 24, 25] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    public function test_it_saves_and_finds_a_role_with_its_permissions(): void
    {
        $permissions = new PostgreSqlPermissionRepository($this->connection);
        $permission = new Permission(PermissionId::generate(), 'membership:create', 'Create a membership');
        $permissions->save($permission);

        $repository = new PostgreSqlRoleRepository($this->connection);
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $role->addPermission($permission->id);
        $repository->save($role);

        $found = $repository->findById($role->id());
        self::assertNotNull($found);
        self::assertSame('org_admin', $found->name());
        self::assertSame(RoleScope::Organization, $found->scope());
        self::assertSame(RoleStatus::Active, $found->status());
        self::assertCount(1, $found->permissionIds());
        self::assertTrue($found->permissionIds()[0]->equals($permission->id));

        $byName = $repository->findByName('org_admin');
        self::assertNotNull($byName);
        self::assertTrue($byName->id()->equals($role->id()));
    }

    public function test_it_persists_deprecation(): void
    {
        $repository = new PostgreSqlRoleRepository($this->connection);
        $role = Role::create(RoleId::generate(), 'member_viewer', RoleScope::Organization);
        $repository->save($role);

        $role->deprecate();
        $repository->save($role);

        $found = $repository->findById($role->id());
        self::assertSame(RoleStatus::Deprecated, $found?->status());
    }
}
