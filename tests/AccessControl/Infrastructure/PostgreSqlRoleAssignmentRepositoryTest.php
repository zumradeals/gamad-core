<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Infrastructure;

use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleAssignmentStatus;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlRoleAssignmentRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlRoleRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlRoleAssignmentRepositoryTest extends TestCase
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

    public function test_it_saves_and_finds_an_active_assignment(): void
    {
        $roles = new PostgreSqlRoleRepository($this->connection);
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $roles->save($role);

        $repository = new PostgreSqlRoleAssignmentRepository($this->connection);
        $assignment = RoleAssignment::create(RoleAssignmentId::generate(), $role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001');
        $repository->save($assignment);

        $found = $repository->findById($assignment->id());
        self::assertNotNull($found);
        self::assertSame(RoleAssignmentStatus::Active, $found->status());

        $active = $repository->findActive($role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001');
        self::assertNotNull($active);
        self::assertTrue($active->id()->equals($assignment->id()));

        $byPerson = $repository->findActiveByPerson('GAM-GAT-PER-000001');
        self::assertCount(1, $byPerson);
    }

    public function test_revoking_removes_it_from_active_lookups(): void
    {
        $roles = new PostgreSqlRoleRepository($this->connection);
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $roles->save($role);

        $repository = new PostgreSqlRoleAssignmentRepository($this->connection);
        $assignment = RoleAssignment::create(RoleAssignmentId::generate(), $role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001');
        $repository->save($assignment);

        $assignment->revoke();
        $repository->save($assignment);

        self::assertNull($repository->findActive($role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));
        self::assertCount(0, $repository->findActiveByPerson('GAM-GAT-PER-000001'));

        $found = $repository->findById($assignment->id());
        self::assertSame(RoleAssignmentStatus::Revoked, $found?->status());
    }

    public function test_the_active_uniqueness_index_rejects_a_duplicate_active_triplet(): void
    {
        $roles = new PostgreSqlRoleRepository($this->connection);
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $roles->save($role);

        $repository = new PostgreSqlRoleAssignmentRepository($this->connection);
        $repository->save(RoleAssignment::create(RoleAssignmentId::generate(), $role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));

        $this->expectException(\PDOException::class);

        $repository->save(RoleAssignment::create(RoleAssignmentId::generate(), $role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));
    }
}
