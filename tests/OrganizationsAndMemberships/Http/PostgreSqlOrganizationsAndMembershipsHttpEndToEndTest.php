<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Http;

use DateTimeImmutable;
use Gamad\Core\OrganizationsAndMemberships\Application\AtomicMembershipPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\AtomicOrganizationPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateDepartmentHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateOrganizationHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\EndMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\ResumeMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\SuspendMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Http\OrganizationsAndMembershipsHttpController;
use Gamad\Core\OrganizationsAndMemberships\Http\OrganizationsAndMembershipsHttpKernel;
use Gamad\Core\OrganizationsAndMemberships\Http\OrganizationsAndMembershipsResponseValidator;
use Gamad\Core\OrganizationsAndMemberships\Http\OrganizationsAndMembershipsRoutes;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\IdentityRegistry\PostgreSqlIdentityLookup;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlMembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlOrganizationRepository;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\PersonsAndAccounts\PostgreSqlPersonLookup;
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
final class PostgreSqlOrganizationsAndMembershipsHttpEndToEndTest extends TestCase
{
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

        $this->connection->exec('DROP TABLE IF EXISTS memberships CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS departments CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS organizations CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS sessions CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS authentication_methods CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS user_accounts CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS persons CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS administrative_audit CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_dead_letters CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_messages CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identities CASCADE');

        foreach ([1, 2, 3, 6, 7, 11, 12, 13, 14, 15, 16, 19, 20, 21] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }

        // Seed one authenticated session directly (bypassing HTTP), and the
        // organization identity + person this suite will exercise.
        $this->insertIdentity('GAM-GAT-PER-900001', 'person');
        $this->insertIdentity('GAM-GAT-ORG-000001', 'organization');

        $personRepository = new PostgreSqlPersonRepository($this->connection);
        $personRepository->save(Person::register(new PersonId('GAM-GAT-PER-900001'), 'Amina Traoré'));

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
    }

    public function test_creating_an_organization_requires_a_session_and_an_eligible_identity(): void
    {
        $kernel = $this->kernel();

        $unauthenticated = $kernel->handle(new Request('POST', '/organizations', [], [], json_encode(['identity_id' => 'GAM-GAT-ORG-000001', 'name' => 'GAMAD SAS'])));
        self::assertSame(401, $unauthenticated->status);

        $authenticated = $kernel->handle(new Request('POST', '/organizations', [
            'Authorization' => 'Bearer ' . $this->rawSessionToken,
        ], [], json_encode(['identity_id' => 'GAM-GAT-ORG-000001', 'name' => 'GAMAD SAS'])));
        self::assertSame(201, $authenticated->status);
        $body = json_decode($authenticated->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('GAM-GAT-ORG-000001', $body['organization_id']);
        self::assertGreaterThan(0, (int) $this->connection->query('SELECT COUNT(*) FROM administrative_audit')->fetchColumn());
    }

    public function test_full_flow_department_membership_and_lifecycle_transitions(): void
    {
        $kernel = $this->kernel();
        $auth = ['Authorization' => 'Bearer ' . $this->rawSessionToken];

        $kernel->handle(new Request('POST', '/organizations', $auth, [], json_encode(['identity_id' => 'GAM-GAT-ORG-000001', 'name' => 'GAMAD SAS'])));

        $department = $kernel->handle(new Request('POST', '/organizations/GAM-GAT-ORG-000001/departments', $auth, [], json_encode(['name' => 'Direction Générale'])));
        self::assertSame(201, $department->status);

        $membership = $kernel->handle(new Request('POST', '/organizations/GAM-GAT-ORG-000001/memberships', $auth, [], json_encode(['person_id' => 'GAM-GAT-PER-900001', 'membership_type' => 'GAMAD_CITIZEN'])));
        self::assertSame(201, $membership->status);
        $membershipBody = json_decode($membership->body, true, flags: JSON_THROW_ON_ERROR);

        $duplicate = $kernel->handle(new Request('POST', '/organizations/GAM-GAT-ORG-000001/memberships', $auth, [], json_encode(['person_id' => 'GAM-GAT-PER-900001', 'membership_type' => 'GAMAD_CITIZEN'])));
        self::assertSame(409, $duplicate->status);

        $list = $kernel->handle(new Request('GET', '/organizations/GAM-GAT-ORG-000001/memberships', $auth));
        self::assertSame(200, $list->status);
        $listBody = json_decode($list->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $listBody['items']);

        $membershipId = $membershipBody['membership_id'];
        $suspend = $kernel->handle(new Request('POST', "/memberships/{$membershipId}/suspend", $auth));
        self::assertSame(200, $suspend->status);
        self::assertSame('suspended', json_decode($suspend->body, true, flags: JSON_THROW_ON_ERROR)['status']);

        $resume = $kernel->handle(new Request('POST', "/memberships/{$membershipId}/resume", $auth));
        self::assertSame(200, $resume->status);

        $end = $kernel->handle(new Request('POST', "/memberships/{$membershipId}/end", $auth));
        self::assertSame(200, $end->status);
        self::assertSame('ended', json_decode($end->body, true, flags: JSON_THROW_ON_ERROR)['status']);
    }

    public function test_getting_children_of_a_parent_organization(): void
    {
        $kernel = $this->kernel();
        $auth = ['Authorization' => 'Bearer ' . $this->rawSessionToken];
        $this->insertIdentity('GAM-GAT-ORG-000002', 'organization');

        $kernel->handle(new Request('POST', '/organizations', $auth, [], json_encode(['identity_id' => 'GAM-GAT-ORG-000001', 'name' => 'GAMAD SAS'])));
        $kernel->handle(new Request('POST', '/organizations', $auth, [], json_encode(['identity_id' => 'GAM-GAT-ORG-000002', 'name' => 'GAMAD Technologie', 'parent_id' => 'GAM-GAT-ORG-000001'])));

        $children = $kernel->handle(new Request('GET', '/organizations/GAM-GAT-ORG-000001/children', $auth));

        self::assertSame(200, $children->status);
        $body = json_decode($children->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
        self::assertSame('GAM-GAT-ORG-000002', $body['items'][0]['organization_id']);
    }

    private function kernel(): OrganizationsAndMembershipsHttpKernel
    {
        $organizations = new PostgreSqlOrganizationRepository($this->connection);
        $memberships = new PostgreSqlMembershipRepository($this->connection);
        $transactions = new PdoTransactionManager($this->connection);
        $outbox = new PostgreSqlOutboxRepository($this->connection);
        $events = new DomainEventCollector();

        $controller = new OrganizationsAndMembershipsHttpController(
            createOrganization: new CreateOrganizationHandler(
                identities: new PostgreSqlIdentityLookup($this->connection),
                organizations: $organizations,
                persister: new AtomicOrganizationPersister($organizations, $outbox, $events, $transactions),
            ),
            organizations: $organizations,
            createDepartment: new CreateDepartmentHandler(
                organizations: $organizations,
                persister: new AtomicOrganizationPersister($organizations, $outbox, $events, $transactions),
            ),
            createMembership: new CreateMembershipHandler(
                persons: new PostgreSqlPersonLookup($this->connection),
                organizations: $organizations,
                memberships: $memberships,
                persister: new AtomicMembershipPersister($memberships, $outbox, $events, $transactions),
            ),
            memberships: $memberships,
            suspendMembership: new SuspendMembershipHandler($memberships, new AtomicMembershipPersister($memberships, $outbox, $events, $transactions)),
            resumeMembership: new ResumeMembershipHandler($memberships, new AtomicMembershipPersister($memberships, $outbox, $events, $transactions)),
            endMembership: new EndMembershipHandler($memberships, new AtomicMembershipPersister($memberships, $outbox, $events, $transactions)),
        );

        $sessionRepository = new PostgreSqlSessionRepository($this->connection);
        $routes = OrganizationsAndMembershipsRoutes::forController($controller);

        return new OrganizationsAndMembershipsHttpKernel(
            validator: new OpenApiRequestValidator($routes),
            responseValidator: new OrganizationsAndMembershipsResponseValidator(),
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
