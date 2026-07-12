<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Outbox;

use DateTimeImmutable;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxRepository;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxPublisher;
use Gamad\Core\Shared\Outbox\RetryPolicy;
use Gamad\Core\Tests\Support\RecordingEventBus;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlOutboxPublisherWorkerTest extends TestCase
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

        $this->connection->exec('DROP TABLE IF EXISTS outbox_dead_letters');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_messages');
        $this->connection->exec((string) file_get_contents(__DIR__ . '/../../../database/migrations/002_create_outbox_messages.sql'));
        $this->connection->exec((string) file_get_contents(__DIR__ . '/../../../database/migrations/003_add_outbox_delivery_lifecycle.sql'));
    }

    public function test_worker_claims_publishes_and_marks_message_completed(): void
    {
        $repository = new PostgreSqlOutboxRepository($this->connection);
        $repository->append(new OutboxMessage(
            id: '44444444-4444-4444-8444-444444444444',
            aggregateId: 'GAM-PER-900100',
            eventName: 'identity.registered.v1',
            payload: ['identity_id' => 'GAM-PER-900100'],
            occurredAt: new DateTimeImmutable('2026-07-12T12:00:00+00:00'),
            recordedAt: new DateTimeImmutable('2026-07-12T12:00:00+00:00'),
        ));
        $bus = new RecordingEventBus();
        $publisher = new OutboxPublisher(
            outbox: $repository,
            eventBus: $bus,
            retryPolicy: new RetryPolicy(maxAttempts: 3, baseDelaySeconds: 1, maxDelaySeconds: 10),
            workerId: 'integration-worker-1',
        );

        $report = $publisher->publishPending(new DateTimeImmutable('2026-07-12T12:01:00+00:00'));

        self::assertSame(1, $report->published);
        self::assertCount(1, $bus->published);
        self::assertSame(
            1,
            (int) $this->connection->query('SELECT COUNT(*) FROM outbox_messages WHERE published_at IS NOT NULL')->fetchColumn(),
        );
        self::assertSame(
            0,
            (int) $this->connection->query('SELECT COUNT(*) FROM outbox_messages WHERE locked_by IS NOT NULL')->fetchColumn(),
        );
    }

    public function test_second_worker_cannot_claim_an_active_lock(): void
    {
        $repository = new PostgreSqlOutboxRepository($this->connection);
        $repository->append(new OutboxMessage(
            id: '55555555-5555-4555-8555-555555555555',
            aggregateId: 'GAM-ORG-900100',
            eventName: 'identity.registered.v1',
            payload: ['identity_id' => 'GAM-ORG-900100'],
            occurredAt: new DateTimeImmutable('2026-07-12T12:00:00+00:00'),
            recordedAt: new DateTimeImmutable('2026-07-12T12:00:00+00:00'),
        ));

        $firstClaim = $repository->claimPending(
            limit: 10,
            workerId: 'worker-a',
            lockedUntil: new DateTimeImmutable('2099-01-01T00:00:00+00:00'),
        );
        $secondClaim = $repository->claimPending(
            limit: 10,
            workerId: 'worker-b',
            lockedUntil: new DateTimeImmutable('2099-01-01T00:00:00+00:00'),
        );

        self::assertCount(1, $firstClaim);
        self::assertSame([], $secondClaim);
    }
}
