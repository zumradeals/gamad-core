<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Outbox;

use DateTimeImmutable;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxPublisher;
use Gamad\Core\Shared\Outbox\RetryPolicy;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class OutboxPublisherTest extends TestCase
{
    public function test_it_publishes_and_marks_a_message_completed(): void
    {
        $outbox = new InMemoryOutboxRepository();
        $bus = new RecordingEventBus();
        $message = new OutboxMessage(
            id: '11111111-1111-4111-8111-111111111111',
            aggregateId: 'GAM-GAT-PER-000001',
            eventName: 'identity.registered.v1',
            payload: ['identity_id' => 'GAM-GAT-PER-000001'],
            occurredAt: new DateTimeImmutable('2026-07-12T11:00:00+00:00'),
            recordedAt: new DateTimeImmutable('2026-07-12T11:00:00+00:00'),
        );
        $outbox->append($message);

        $report = (new OutboxPublisher(
            outbox: $outbox,
            eventBus: $bus,
            retryPolicy: new RetryPolicy(maxAttempts: 3, baseDelaySeconds: 1, maxDelaySeconds: 10),
            workerId: 'worker-1',
        ))->publishPending(new DateTimeImmutable('2026-07-12T11:01:00+00:00'));

        self::assertSame(1, $report->claimed);
        self::assertSame(1, $report->published);
        self::assertCount(1, $bus->published);
        self::assertCount(1, $outbox->published);
        self::assertSame([], $outbox->pending);
    }

    public function test_it_schedules_a_retry_after_a_transient_failure(): void
    {
        $outbox = new InMemoryOutboxRepository();
        $bus = new RecordingEventBus();
        $bus->failuresRemaining = 1;
        $outbox->append(new OutboxMessage(
            id: '22222222-2222-4222-8222-222222222222',
            aggregateId: 'GAM-GAT-ORG-000001',
            eventName: 'identity.registered.v1',
            payload: ['identity_id' => 'GAM-GAT-ORG-000001'],
            occurredAt: new DateTimeImmutable('2026-07-12T11:00:00+00:00'),
            recordedAt: new DateTimeImmutable('2026-07-12T11:00:00+00:00'),
        ));

        $report = (new OutboxPublisher(
            outbox: $outbox,
            eventBus: $bus,
            retryPolicy: new RetryPolicy(maxAttempts: 3, baseDelaySeconds: 1, maxDelaySeconds: 10),
            workerId: 'worker-1',
        ))->publishPending(new DateTimeImmutable('2026-07-12T11:01:00+00:00'));

        self::assertSame(1, $report->retried);
        self::assertSame(1, array_values($outbox->pending)[0]->attempts);
        self::assertSame([], $outbox->deadLetters);
    }

    public function test_it_moves_a_message_to_dead_letter_after_maximum_attempts(): void
    {
        $outbox = new InMemoryOutboxRepository();
        $bus = new RecordingEventBus();
        $bus->failuresRemaining = 1;
        $message = new OutboxMessage(
            id: '33333333-3333-4333-8333-333333333333',
            aggregateId: 'GAM-GAT-APP-000001',
            eventName: 'identity.registered.v1',
            payload: ['identity_id' => 'GAM-GAT-APP-000001'],
            occurredAt: new DateTimeImmutable('2026-07-12T11:00:00+00:00'),
            recordedAt: new DateTimeImmutable('2026-07-12T11:00:00+00:00'),
        );
        $outbox->append($message);
        $pending = $outbox->pending[$message->id];
        $outbox->markFailed($message->id, 2, 'Previous failure', new DateTimeImmutable('2026-07-12T11:00:30+00:00'));

        $report = (new OutboxPublisher(
            outbox: $outbox,
            eventBus: $bus,
            retryPolicy: new RetryPolicy(maxAttempts: 3, baseDelaySeconds: 1, maxDelaySeconds: 10),
            workerId: 'worker-1',
        ))->publishPending(new DateTimeImmutable('2026-07-12T11:01:00+00:00'));

        self::assertSame(1, $report->deadLettered);
        self::assertCount(1, $outbox->deadLetters);
        self::assertSame([], $outbox->pending);
    }
}
