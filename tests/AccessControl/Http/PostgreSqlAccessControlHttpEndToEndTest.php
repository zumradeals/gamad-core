<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Http;

use DateTimeImmutable;
use Gamad\Core\AccessControl\Application\AtomicRoleAssignmentPersister;
use Gamad\Core\AccessControl\Application\AtomicRolePersister;
use Gamad\Core\AccessControl\Application\Command\AddPermissionToRoleHandler;
use Gamad\Core\AccessControl\Application\Command\AssignRoleHandler;
use Gamad\Core\AccessControl\Application\Command\CreatePermissionHandler;
use Gamad\Core\AccessControl\Application\Command\CreateRoleHandler;
use Gamad\Core\AccessControl\Application\Command\EvaluateAccessHandler;
use Gamad\Core\AccessControl\Application\Command\RevokeRoleHandler;
use Gamad\Core\AccessControl\Domain\AccessControlEngine;
use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Http\AccessControlHttpController;
use Gamad\Core\AccessControl\Http\AccessControlHttpKernel;
use Gamad\Core\AccessControl\Http\AccessControlResponseValidator;
use Gamad\Core\AccessControl\Http\AccessControlRoutes;
use Gamad\Core\AccessControl\Infrastructure\Outbox\PostgreSqlAccessDecisionsOutboxRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlAccessControlLookup;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlPermissionRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlRoleAssignmentRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlRoleRepository;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Http\SessionTokenAuthenticator;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlSessionRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlUserAccountRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAdministrativeAuditRepository;
use Gamad\Core\Shared\Infrastructure\Http\InMemoryRateLimiter;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxRepository;
use Gamad\Core\Shared\Infrastructure\Persistence\PdoTransactionManager;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlAccessControlHttpEndToEndTest extends TestCase
{
    private const string REALM_ROOT = 'GAM-GAT-ORG-000001';

    private PDO $connection;
    private string $rawSessionToken;

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
        $this->connection->exec('DROP TABLE IF EXISTS organizations CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS sessions CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS authentication_methods CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS user_accounts CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS persons CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS administrative_audit CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_dead_letters CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_messages CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identities CASCADE');

        foreach ([1, 2, 3, 6, 7, 11, 12, 13, 14, 15, 16, 19, 22, 23, 24, 25, 26] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }

        $this->insertIdentity('GAM-GAT-PER-900001', 'person');
        $this->insertIdentity('GAM-GAT-PER-900002', 'person');
        $this->insertIdentity(self::REALM_ROOT, 'organization');

        $personRepository = new PostgreSqlPersonRepository($this->connection);
        $personRepository->save(Person::register(new PersonId('GAM-GAT-PER-900001'), 'Zakaria Le SOUFI'));
        $personRepository->save(Person::register(new PersonId('GAM-GAT-PER-900002'), 'Nouvelle Recrue'));

        $organizations = $this->connection->prepare(
            'INSERT INTO organizations (identity_id, parent_id, name, status, founded_at) VALUES (:id, NULL, :name, :status, :founded_at)'
        );
        $organizations->execute([
            'id' => self::REALM_ROOT,
            'name' => 'GAMAD SAS',
            'status' => 'active',
            'founded_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $accountRepository = new PostgreSqlUserAccountRepository($this->connection);
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-900001'));
        $account->addAuthenticationMethod(AuthenticationMethodId::generate(), AuthenticationMethodType::Password, 'argon2id$seed');
        $accountRepository->save($account);

        $sessionRepository = new PostgreSqlSessionRepository($this->connection);
        $this->rawSessionToken = bin2hex(random_bytes(32));
        $session = Session::issue(
            SessionId::generate(),
            $account->id(),
            $account->currentPasswordMethod()->id(),
            hash('sha256', $this->rawSessionToken),
            new DateTimeImmutable('+1 hour'),
        );
        $sessionRepository->save($session);

        // The session actor (GAM-GAT-PER-900001) is granted a realm-scoped
        // superadmin role directly through the repositories — the same
        // institutional bootstrap bin/bootstrap-access-control performs
        // (Task 9), done here without HTTP to set up the fixture.
        $permissions = new PostgreSqlPermissionRepository($this->connection);
        $roles = new PostgreSqlRoleRepository($this->connection);
        $assignments = new PostgreSqlRoleAssignmentRepository($this->connection);
        $superadmin = Role::create(RoleId::generate(), 'superadmin', RoleScope::Realm);
        foreach (['role:create', 'role:read', 'permission:assign', 'role:assign', 'role:revoke', 'runtime:health:read'] as $name) {
            $permission = new Permission(PermissionId::generate(), $name, ucfirst(str_replace(':', ' ', $name)));
            $permissions->save($permission);
            $superadmin->addPermission($permission->id);
        }
        $roles->save($superadmin);
        $assignments->save(RoleAssignment::create(RoleAssignmentId::generate(), $superadmin->id(), 'GAM-GAT-PER-900001', self::REALM_ROOT));
    }

    public function test_creating_a_permission_requires_a_session_and_role_create(): void
    {
        $kernel = $this->kernel();

        $unauthenticated = $kernel->handle(new Request('POST', '/permissions', [], [], (string) json_encode(['name' => 'membership:create', 'description' => 'Create a membership'])));
        self::assertSame(401, $unauthenticated->status);

        $authenticated = $kernel->handle(new Request('POST', '/permissions', ['Authorization' => 'Bearer ' . $this->rawSessionToken], [], (string) json_encode(['name' => 'membership:create', 'description' => 'Create a membership'])));
        self::assertSame(201, $authenticated->status);
        $body = json_decode($authenticated->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('membership:create', $body['name']);
    }

    public function test_an_actor_without_the_permission_is_denied(): void
    {
        $kernel = $this->kernel();
        $auth = ['Authorization' => 'Bearer ' . $this->rawSessionToken];

        // Revoke every grant so the session actor has no permission at all.
        $this->connection->exec("UPDATE role_assignments SET status = 'revoked'");

        $response = $kernel->handle(new Request('POST', '/permissions', $auth, [], (string) json_encode(['name' => 'membership:create', 'description' => 'Create a membership'])));

        self::assertSame(403, $response->status);
    }

    public function test_full_flow_role_and_permission_management_and_assignment(): void
    {
        $kernel = $this->kernel();
        $auth = ['Authorization' => 'Bearer ' . $this->rawSessionToken];

        $permission = $kernel->handle(new Request('POST', '/permissions', $auth, [], (string) json_encode(['name' => 'membership:create', 'description' => 'Create a membership'])));
        self::assertSame(201, $permission->status);
        $permissionId = json_decode($permission->body, true, flags: JSON_THROW_ON_ERROR)['permission_id'];

        $role = $kernel->handle(new Request('POST', '/roles', $auth, [], (string) json_encode(['name' => 'org_admin', 'scope' => 'organization'])));
        self::assertSame(201, $role->status);
        $roleId = json_decode($role->body, true, flags: JSON_THROW_ON_ERROR)['role_id'];

        $added = $kernel->handle(new Request('POST', "/roles/{$roleId}/permissions", $auth, [], (string) json_encode(['permission_id' => $permissionId])));
        self::assertSame(200, $added->status);

        $listedRoles = $kernel->handle(new Request('GET', '/roles', $auth));
        self::assertSame(200, $listedRoles->status);
        self::assertGreaterThanOrEqual(1, count(json_decode($listedRoles->body, true, flags: JSON_THROW_ON_ERROR)['items']));

        $listedPermissions = $kernel->handle(new Request('GET', '/permissions', $auth));
        self::assertSame(200, $listedPermissions->status);

        $assigned = $kernel->handle(new Request('POST', '/role-assignments', $auth, [], (string) json_encode([
            'role_id' => $roleId,
            'person_id' => 'GAM-GAT-PER-900002',
            'organization_id' => self::REALM_ROOT,
        ])));
        self::assertSame(201, $assigned->status);
        $assignmentBody = json_decode($assigned->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('active', $assignmentBody['status']);

        $evaluate = $kernel->handle(new Request('POST', '/access/evaluate', $auth, [], (string) json_encode([
            'actor_id' => 'GAM-GAT-PER-900002',
            'action' => 'membership:create',
            'context_id' => self::REALM_ROOT,
        ])));
        self::assertSame(200, $evaluate->status);
        self::assertSame('ALLOW', json_decode($evaluate->body, true, flags: JSON_THROW_ON_ERROR)['decision']);

        $assignmentId = $assignmentBody['assignment_id'];
        $revoked = $kernel->handle(new Request('DELETE', "/role-assignments/{$assignmentId}", $auth));
        self::assertSame(200, $revoked->status);
        self::assertSame('revoked', json_decode($revoked->body, true, flags: JSON_THROW_ON_ERROR)['status']);
    }

    private function kernel(): AccessControlHttpKernel
    {
        $permissions = new PostgreSqlPermissionRepository($this->connection);
        $roles = new PostgreSqlRoleRepository($this->connection);
        $assignments = new PostgreSqlRoleAssignmentRepository($this->connection);
        $transactions = new PdoTransactionManager($this->connection);
        $outbox = new PostgreSqlOutboxRepository($this->connection);
        $accessDecisionsOutbox = new PostgreSqlAccessDecisionsOutboxRepository($this->connection);
        $events = new DomainEventCollector();

        $evaluateAccess = new EvaluateAccessHandler($assignments, $roles, $permissions, new AccessControlEngine(), $accessDecisionsOutbox);
        $lookup = new PostgreSqlAccessControlLookup($this->connection);

        $controller = new AccessControlHttpController(
            createPermission: new CreatePermissionHandler($permissions),
            permissions: $permissions,
            createRole: new CreateRoleHandler($roles, new AtomicRolePersister($roles, $outbox, $events, $transactions)),
            roles: $roles,
            addPermissionToRole: new AddPermissionToRoleHandler($roles, $permissions, new AtomicRolePersister($roles, $outbox, $events, $transactions)),
            assignRole: new AssignRoleHandler(
                roles: $roles,
                assignments: $assignments,
                lookup: $lookup,
                evaluateAccess: $evaluateAccess,
                persister: new AtomicRoleAssignmentPersister($assignments, $outbox, $events, $transactions),
            ),
            revokeRole: new RevokeRoleHandler($assignments, new AtomicRoleAssignmentPersister($assignments, $outbox, $events, $transactions)),
            evaluateAccess: $evaluateAccess,
            lookup: $lookup,
            realmRootOrganizationId: self::REALM_ROOT,
        );

        $sessionRepository = new PostgreSqlSessionRepository($this->connection);
        $routes = AccessControlRoutes::forController($controller);

        return new AccessControlHttpKernel(
            validator: new OpenApiRequestValidator($routes),
            responseValidator: new AccessControlResponseValidator(),
            sessionAuthentication: new SessionTokenAuthenticator($sessionRepository),
            rateLimiter: new InMemoryRateLimiter(),
            audit: new PostgreSqlAdministrativeAuditRepository($this->connection),
        );
    }

    private function insertIdentity(string $id, string $type): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO identities (id, type, status, registered_at) VALUES (:id, :type, :status, :registered_at)'
        );
        $statement->execute([
            'id' => $id,
            'type' => $type,
            'status' => 'active',
            'registered_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
