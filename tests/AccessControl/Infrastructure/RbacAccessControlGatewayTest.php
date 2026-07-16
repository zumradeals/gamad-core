<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Infrastructure;

use Gamad\Core\AccessControl\Application\Command\EvaluateAccessHandler;
use Gamad\Core\AccessControl\Domain\AccessControlEngine;
use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Infrastructure\Outbox\PostgreSqlAccessDecisionsOutboxRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlPermissionRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlRoleAssignmentRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlRoleRepository;
use Gamad\Core\AccessControl\Infrastructure\RbacAccessControlGateway;
use Gamad\Core\Shared\Contract\IdentityId;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * DIRECTIVE-007 Task 6 acceptance: ALLOW for an actor with the right role,
 * DENY for an actor without one, and ALLOW on any action for a
 * realm-scoped superadmin.
 */
#[Group('integration')]
final class RbacAccessControlGatewayTest extends TestCase
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

        $this->connection->exec('DROP TABLE IF EXISTS access_decisions_outbox CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS role_assignments CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS role_permissions CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS roles CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS permissions CASCADE');

        foreach ([22, 23, 24, 25, 26] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    private function gateway(): RbacAccessControlGateway
    {
        return new RbacAccessControlGateway(new EvaluateAccessHandler(
            new PostgreSqlRoleAssignmentRepository($this->connection),
            new PostgreSqlRoleRepository($this->connection),
            new PostgreSqlPermissionRepository($this->connection),
            new AccessControlEngine(),
            new PostgreSqlAccessDecisionsOutboxRepository($this->connection),
        ));
    }

    public function test_it_allows_an_actor_with_the_right_role(): void
    {
        $permissions = new PostgreSqlPermissionRepository($this->connection);
        $permission = new Permission(\Gamad\Core\AccessControl\Domain\PermissionId::generate(), 'membership:create', 'Create a membership');
        $permissions->save($permission);

        $roles = new PostgreSqlRoleRepository($this->connection);
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $role->addPermission($permission->id);
        $roles->save($role);

        $assignments = new PostgreSqlRoleAssignmentRepository($this->connection);
        $assignments->save(RoleAssignment::create(RoleAssignmentId::generate(), $role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));

        $decision = $this->gateway()->can(new IdentityId('GAM-GAT-PER-000001'), 'membership:create', new IdentityId('GAM-GAT-ORG-000001'));

        self::assertTrue($decision->allowed);
    }

    public function test_it_denies_an_actor_without_any_role(): void
    {
        $decision = $this->gateway()->can(new IdentityId('GAM-GAT-PER-000002'), 'membership:create', new IdentityId('GAM-GAT-ORG-000001'));

        self::assertFalse($decision->allowed);
    }

    public function test_it_allows_a_realm_scoped_superadmin_on_any_action(): void
    {
        $roles = new PostgreSqlRoleRepository($this->connection);
        $role = Role::create(RoleId::generate(), 'superadmin', RoleScope::Realm);
        $roles->save($role);
        // superadmin holds every permission at bootstrap; here only the one
        // being exercised needs to exist to prove the realm-scope bypass.
        $permissions = new PostgreSqlPermissionRepository($this->connection);
        $permission = new Permission(\Gamad\Core\AccessControl\Domain\PermissionId::generate(), 'outbox:read', 'Read the outbox state');
        $permissions->save($permission);
        $role->addPermission($permission->id);
        $roles->save($role);

        $assignments = new PostgreSqlRoleAssignmentRepository($this->connection);
        $assignments->save(RoleAssignment::create(RoleAssignmentId::generate(), $role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));

        $decision = $this->gateway()->can(new IdentityId('GAM-GAT-PER-000001'), 'outbox:read', new IdentityId('GAM-GAT-ORG-999999'));

        self::assertTrue($decision->allowed);
    }
}
