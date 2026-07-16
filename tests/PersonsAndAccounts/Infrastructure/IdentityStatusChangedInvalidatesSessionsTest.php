<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Infrastructure;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Application\IdentityLifecycleService;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityInternalId;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\PostgreSqlIdentityRepository;
use Gamad\Core\PersonsAndAccounts\Application\AtomicSessionPersister;
use Gamad\Core\PersonsAndAccounts\Application\ReactToIdentityStatusChanged;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Messaging\IdentityStatusChangedReactingEventBus;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlSessionRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlUserAccountRepository;
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

/** Task 8 acceptance: suspending an Identity invalidates its Person's active sessions. */
#[Group('integration')]
final class IdentityStatusChangedInvalidatesSessionsTest extends TestCase
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

        $this->connection->exec('DROP TABLE IF EXISTS sessions CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS authentication_methods CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS user_accounts CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS persons CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_dead_letters CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_messages CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identity_identifier_sequences CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identities CASCADE');

        foreach ([1, 2, 3, 10, 11, 12, 13, 14, 15, 16] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    public function test_suspending_the_identity_revokes_active_sessions_of_its_account(): void
    {
        $identityRepository = new PostgreSqlIdentityRepository($this->connection);
        $transactions = new PdoTransactionManager($this->connection);
        $outbox = new PostgreSqlOutboxRepository($this->connection);
        $identityPersister = new AtomicIdentityPersister($identityRepository, $outbox, new DomainEventCollector(), $transactions);

        $identity = Identity::register(
            IdentityInternalId::generate(),
            new IdentityId('GAM-GAT-PER-900001'),
            IdentityType::Person,
        );
        $identityPersister->persist($identity);

        $personRepository = new PostgreSqlPersonRepository($this->connection);
        $personRepository->save(Person::register(new PersonId('GAM-GAT-PER-900001'), 'Amina Traoré'));

        $accountRepository = new PostgreSqlUserAccountRepository($this->connection);
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-900001'));
        $account->addAuthenticationMethod(AuthenticationMethodId::generate(), AuthenticationMethodType::Password, 'argon2id$hash');
        $accountRepository->save($account);

        $sessionRepository = new PostgreSqlSessionRepository($this->connection);
        $session = Session::issue(
            SessionId::generate(),
            $account->id(),
            $account->currentPasswordMethod()->id(),
            hash('sha256', 'raw-token'),
            new DateTimeImmutable('+1 hour'),
        );
        $sessionRepository->save($session);

        self::assertCount(1, $sessionRepository->findActiveByUserAccountId($account->id()));

        // Suspend the identity — this only appends an outbox message; the
        // reaction happens when that message is actually published, exactly
        // as it would in the real outbox-worker process.
        $lifecycle = new IdentityLifecycleService($identityRepository, $identityPersister, new PermissiveAccessControlGateway());
        $lifecycle->transition(new IdentityId('GAM-GAT-PER-900001'), IdentityStatus::Suspended, 'GAM-GAT-PER-000001');

        $reactor = new ReactToIdentityStatusChanged(
            persons: $personRepository,
            accounts: $accountRepository,
            sessions: $sessionRepository,
            persister: new AtomicSessionPersister($sessionRepository, $outbox, new DomainEventCollector(), $transactions),
        );
        $noopInnerBus = new class implements EventBus {
            public function publish(PendingOutboxMessage $message): void
            {
            }
        };
        $publisher = new OutboxPublisher(
            outbox: $outbox,
            eventBus: new IdentityStatusChangedReactingEventBus($noopInnerBus, $reactor),
            retryPolicy: new RetryPolicy(maxAttempts: 5, baseDelaySeconds: 5, maxDelaySeconds: 300),
            workerId: 'test-worker',
        );

        $report = $publisher->publishPending();

        self::assertSame(2, $report->published); // identity.registered.v1 + identity.status_changed.v1
        self::assertCount(0, $sessionRepository->findActiveByUserAccountId($account->id()));
        $revoked = $sessionRepository->findById($session->id());
        self::assertNotNull($revoked);
        self::assertTrue($revoked->isRevoked());
    }

    public function test_it_does_not_touch_sessions_for_an_unrelated_identity_status_change(): void
    {
        $identityRepository = new PostgreSqlIdentityRepository($this->connection);
        $transactions = new PdoTransactionManager($this->connection);
        $outbox = new PostgreSqlOutboxRepository($this->connection);
        $identityPersister = new AtomicIdentityPersister($identityRepository, $outbox, new DomainEventCollector(), $transactions);

        $identity = Identity::register(IdentityInternalId::generate(), new IdentityId('GAM-GAT-ORG-900001'), IdentityType::Organization);
        $identityPersister->persist($identity);

        $reactor = new ReactToIdentityStatusChanged(
            persons: new PostgreSqlPersonRepository($this->connection),
            accounts: new PostgreSqlUserAccountRepository($this->connection),
            sessions: new PostgreSqlSessionRepository($this->connection),
            persister: new AtomicSessionPersister(new PostgreSqlSessionRepository($this->connection), $outbox, new DomainEventCollector(), $transactions),
        );
        $noopInnerBus = new class implements EventBus {
            public function publish(PendingOutboxMessage $message): void
            {
            }
        };
        $publisher = new OutboxPublisher(
            outbox: $outbox,
            eventBus: new IdentityStatusChangedReactingEventBus($noopInnerBus, $reactor),
            retryPolicy: new RetryPolicy(maxAttempts: 5, baseDelaySeconds: 5, maxDelaySeconds: 300),
            workerId: 'test-worker',
        );

        $lifecycle = new IdentityLifecycleService($identityRepository, $identityPersister, new PermissiveAccessControlGateway());
        $lifecycle->transition(new IdentityId('GAM-GAT-ORG-900001'), IdentityStatus::Suspended, 'GAM-GAT-PER-000001');

        $report = $publisher->publishPending();

        self::assertSame(2, $report->published);
        self::assertSame(0, $report->deadLettered);
    }
}
