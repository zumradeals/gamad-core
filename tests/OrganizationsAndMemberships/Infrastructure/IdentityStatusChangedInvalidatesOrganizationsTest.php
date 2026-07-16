<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Infrastructure;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Application\IdentityLifecycleService;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityInternalId;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\PostgreSqlIdentityRepository;
use Gamad\Core\OrganizationsAndMemberships\Application\AtomicMembershipPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\AtomicOrganizationPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\ReactToIdentityStatusChangedForOrganizations;
use Gamad\Core\OrganizationsAndMemberships\Application\ReactToOrganizationSuspended;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipType;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationStatus;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Messaging\IdentityStatusChangedReactingEventBus;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Messaging\OrganizationSuspendedReactingEventBus;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlMembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlOrganizationRepository;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Infrastructure\AccessControl\PermissiveAccessControlGateway;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxRepository;
use Gamad\Core\Shared\Infrastructure\Persistence\PdoTransactionManager;
use Gamad\Core\Shared\Messaging\EventBus;
use Gamad\Core\Shared\Outbox\OutboxPublisher;
use Gamad\Core\Shared\Outbox\PendingOutboxMessage;
use Gamad\Core\Shared\Outbox\RetryPolicy;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/** DIRECTIVE-006 Task 8 acceptance: suspending an Identity invalidates what this context attaches to it. */
#[Group('integration')]
final class IdentityStatusChangedInvalidatesOrganizationsTest extends TestCase
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

        $this->connection->exec('DROP TABLE IF EXISTS memberships CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS departments CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS organizations CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS persons CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_dead_letters CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_messages CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identity_identifier_sequences CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identities CASCADE');

        foreach ([1, 2, 3, 10, 11, 15, 16, 19, 20, 21] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    public function test_suspending_an_organizations_identity_suspends_the_organization_then_its_active_memberships(): void
    {
        $identityRepository = new PostgreSqlIdentityRepository($this->connection);
        $transactions = new PdoTransactionManager($this->connection);
        $outbox = new PostgreSqlOutboxRepository($this->connection);
        $identityPersister = new AtomicIdentityPersister($identityRepository, $outbox, new DomainEventCollector(), $transactions);

        $identity = Identity::register(IdentityInternalId::generate(), new IdentityId('GAM-GAT-ORG-000001'), IdentityType::Organization);
        $identityPersister->persist($identity);
        $this->insertIdentity('GAM-GAT-PER-900001', 'person');
        (new PostgreSqlPersonRepository($this->connection))->save(Person::register(new PersonId('GAM-GAT-PER-900001'), 'Amina Traoré'));

        $organizationRepository = new PostgreSqlOrganizationRepository($this->connection);
        $organizationRepository->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'Structure fictive'));

        $membershipRepository = new PostgreSqlMembershipRepository($this->connection);
        $membership = Membership::create(MembershipId::generate(), 'GAM-GAT-PER-900001', new OrganizationId('GAM-GAT-ORG-000001'), MembershipType::GamadCitizen);
        $membershipRepository->save($membership);

        $publisher = $this->publisher($organizationRepository, $membershipRepository, $outbox, $transactions);

        $lifecycle = new IdentityLifecycleService($identityRepository, $identityPersister, new PermissiveAccessControlGateway());
        $lifecycle->transition(new IdentityId('GAM-GAT-ORG-000001'), IdentityStatus::Suspended, 'GAM-GAT-PER-000001');

        // First poll: identity.status_changed.v1 is published, suspending the
        // Organization and appending organization.suspended.v1 to the outbox
        // — the membership cascade has not run yet, by design (Task 8 docblock).
        $firstReport = $publisher->publishPending();
        self::assertSame(2, $firstReport->published); // identity.registered.v1 + identity.status_changed.v1

        $organization = $organizationRepository->findById(new OrganizationId('GAM-GAT-ORG-000001'));
        self::assertSame(OrganizationStatus::Inactive, $organization->status());
        self::assertSame(MembershipStatus::Active, $membershipRepository->findById($membership->id())->status());

        // Second poll: organization.suspended.v1 is now published, cascading
        // to the organization's active memberships.
        $secondReport = $publisher->publishPending();
        self::assertSame(1, $secondReport->published);
        self::assertSame(MembershipStatus::Suspended, $membershipRepository->findById($membership->id())->status());
    }

    public function test_suspending_a_persons_identity_suspends_their_active_memberships_directly(): void
    {
        $identityRepository = new PostgreSqlIdentityRepository($this->connection);
        $transactions = new PdoTransactionManager($this->connection);
        $outbox = new PostgreSqlOutboxRepository($this->connection);
        $identityPersister = new AtomicIdentityPersister($identityRepository, $outbox, new DomainEventCollector(), $transactions);

        $identity = Identity::register(IdentityInternalId::generate(), new IdentityId('GAM-GAT-PER-900002'), IdentityType::Person);
        $identityPersister->persist($identity);
        (new PostgreSqlPersonRepository($this->connection))->save(Person::register(new PersonId('GAM-GAT-PER-900002'), 'Zakaria Le SOUFI'));
        $this->insertIdentity('GAM-GAT-ORG-000001', 'organization');

        $organizationRepository = new PostgreSqlOrganizationRepository($this->connection);
        $organizationRepository->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'Structure fictive'));

        $membershipRepository = new PostgreSqlMembershipRepository($this->connection);
        $membership = Membership::create(MembershipId::generate(), 'GAM-GAT-PER-900002', new OrganizationId('GAM-GAT-ORG-000001'), MembershipType::GamadCitizen);
        $membershipRepository->save($membership);

        $publisher = $this->publisher($organizationRepository, $membershipRepository, $outbox, $transactions);

        $lifecycle = new IdentityLifecycleService($identityRepository, $identityPersister, new PermissiveAccessControlGateway());
        $lifecycle->transition(new IdentityId('GAM-GAT-PER-900002'), IdentityStatus::Suspended, 'GAM-GAT-PER-000001');

        $report = $publisher->publishPending();

        self::assertSame(2, $report->published);
        self::assertSame(MembershipStatus::Suspended, $membershipRepository->findById($membership->id())->status());
        // The organization itself is untouched — only the person's own membership was.
        self::assertSame(OrganizationStatus::Active, $organizationRepository->findById(new OrganizationId('GAM-GAT-ORG-000001'))->status());
    }

    private function publisher(
        PostgreSqlOrganizationRepository $organizationRepository,
        PostgreSqlMembershipRepository $membershipRepository,
        PostgreSqlOutboxRepository $outbox,
        PdoTransactionManager $transactions,
    ): OutboxPublisher {
        $events = new DomainEventCollector();
        $organizationPersister = new AtomicOrganizationPersister($organizationRepository, $outbox, $events, $transactions);
        $membershipPersister = new AtomicMembershipPersister($membershipRepository, $outbox, $events, $transactions);

        $organizationSuspendedReactor = new ReactToOrganizationSuspended($membershipRepository, $membershipPersister);
        $identityReactor = new ReactToIdentityStatusChangedForOrganizations(
            organizations: $organizationRepository,
            memberships: $membershipRepository,
            organizationPersister: $organizationPersister,
            membershipPersister: $membershipPersister,
        );

        $noopInnerBus = new class implements EventBus {
            public function publish(PendingOutboxMessage $message): void
            {
            }
        };
        $eventBus = new IdentityStatusChangedReactingEventBus($noopInnerBus, $identityReactor);
        $eventBus = new OrganizationSuspendedReactingEventBus($eventBus, $organizationSuspendedReactor);

        return new OutboxPublisher(
            outbox: $outbox,
            eventBus: $eventBus,
            retryPolicy: new RetryPolicy(maxAttempts: 5, baseDelaySeconds: 5, maxDelaySeconds: 300),
            workerId: 'test-worker',
        );
    }

    private function insertIdentity(string $id, string $type): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO identities (internal_id, id, type, status, registered_at) VALUES (gen_random_uuid(), :id, :type, :status, :registered_at)'
        );
        $statement->execute([
            'id' => $id,
            'type' => $type,
            'status' => 'active',
            'registered_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
