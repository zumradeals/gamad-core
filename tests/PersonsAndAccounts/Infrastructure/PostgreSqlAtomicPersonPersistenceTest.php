<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Infrastructure;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Application\AtomicPersonPersister;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxRepository;
use Gamad\Core\Shared\Infrastructure\Persistence\PdoTransactionManager;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;
use Gamad\Core\Shared\Outbox\PendingOutboxMessage;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('integration')]
final class PostgreSqlAtomicPersonPersistenceTest extends TestCase
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
        $this->connection->exec('DROP TABLE IF EXISTS identities CASCADE');

        foreach ([1, 2, 3, 11, 12, 13, 14] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }

        $this->connection->prepare(
            'INSERT INTO identities (id, type, status, registered_at) VALUES (:id, :type, :status, :registered_at)'
        )->execute(['id' => 'GAM-PER-900001', 'type' => 'person', 'status' => 'active', 'registered_at' => (new DateTimeImmutable())->format(DATE_ATOM)]);
    }

    public function test_person_and_event_are_committed_in_the_same_transaction(): void
    {
        $persister = new AtomicPersonPersister(
            persons: new PostgreSqlPersonRepository($this->connection),
            outbox: new PostgreSqlOutboxRepository($this->connection),
            events: new DomainEventCollector(),
            transactions: new PdoTransactionManager($this->connection),
        );
        $person = Person::register(new PersonId('GAM-PER-900001'), 'Amina Traoré');

        $persister->persist($person);

        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM persons')->fetchColumn());
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM outbox_messages')->fetchColumn());
        self::assertSame([], $person->recordedEvents());
    }

    public function test_person_insert_is_rolled_back_when_outbox_write_fails(): void
    {
        $failingOutbox = new class implements OutboxRepository {
            public function append(OutboxMessage $message): void
            {
                throw new RuntimeException('Simulated outbox failure.');
            }

            public function claimPending(int $limit, string $workerId, DateTimeImmutable $lockedUntil): array
            {
                return [];
            }

            public function markPublished(string $messageId, DateTimeImmutable $publishedAt): void
            {
            }

            public function markFailed(string $messageId, int $attempts, string $error, DateTimeImmutable $availableAt): void
            {
            }

            public function moveToDeadLetter(PendingOutboxMessage $message, string $error, DateTimeImmutable $failedAt): void
            {
            }
        };
        $persister = new AtomicPersonPersister(
            persons: new PostgreSqlPersonRepository($this->connection),
            outbox: $failingOutbox,
            events: new DomainEventCollector(),
            transactions: new PdoTransactionManager($this->connection),
        );
        $person = Person::register(new PersonId('GAM-PER-900001'), 'Amina Traoré');

        try {
            $persister->persist($person);
            self::fail('The simulated outbox failure should have been raised.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated outbox failure.', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM persons')->fetchColumn());
        self::assertCount(1, $person->recordedEvents());
    }
}
