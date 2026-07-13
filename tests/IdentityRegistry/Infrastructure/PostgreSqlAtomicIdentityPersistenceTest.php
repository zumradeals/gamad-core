<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Infrastructure;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityInternalId;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\PostgreSqlIdentityRepository;
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
final class PostgreSqlAtomicIdentityPersistenceTest extends TestCase
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

        $this->connection->exec('DROP TABLE IF EXISTS identity_identifier_sequences');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_dead_letters');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_messages');
        $this->connection->exec('DROP TABLE IF EXISTS identities');
        foreach ([1, 2, 3, 10] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    public function test_identity_and_event_are_committed_in_the_same_transaction(): void
    {
        $persister = new AtomicIdentityPersister(
            identities: new PostgreSqlIdentityRepository($this->connection),
            outbox: new PostgreSqlOutboxRepository($this->connection),
            events: new DomainEventCollector(),
            transactions: new PdoTransactionManager($this->connection),
        );
        $identity = Identity::register(
            new IdentityInternalId('11111111-1111-4111-8111-111111111111'),
            new IdentityId('GAM-PER-900001'),
            IdentityType::Person,
            new DateTimeImmutable('2026-07-12T10:00:00+00:00'),
        );

        $persister->persist($identity);

        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM identities')->fetchColumn());
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM outbox_messages')->fetchColumn());
        self::assertSame([], $identity->recordedEvents());
    }

    public function test_identity_insert_is_rolled_back_when_outbox_write_fails(): void
    {
        $failingOutbox = new class implements OutboxRepository {
            public function append(OutboxMessage $message): void { throw new RuntimeException('Simulated outbox failure.'); }
            public function claimPending(int $limit, string $workerId, DateTimeImmutable $lockedUntil): array { return []; }
            public function markPublished(string $messageId, DateTimeImmutable $publishedAt): void {}
            public function markFailed(string $messageId, int $attempts, string $error, DateTimeImmutable $availableAt): void {}
            public function moveToDeadLetter(PendingOutboxMessage $message, string $error, DateTimeImmutable $failedAt): void {}
        };
        $persister = new AtomicIdentityPersister(
            identities: new PostgreSqlIdentityRepository($this->connection),
            outbox: $failingOutbox,
            events: new DomainEventCollector(),
            transactions: new PdoTransactionManager($this->connection),
        );
        $identity = Identity::register(
            new IdentityInternalId('22222222-2222-4222-8222-222222222222'),
            new IdentityId('GAM-PER-900002'),
            IdentityType::Person,
            new DateTimeImmutable('2026-07-12T10:05:00+00:00'),
        );

        try {
            $persister->persist($identity);
            self::fail('The simulated outbox failure should have been raised.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated outbox failure.', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM identities')->fetchColumn());
        self::assertCount(1, $identity->recordedEvents());
    }
}
