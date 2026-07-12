<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Support;

use DateTimeImmutable;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;
use Gamad\Core\Shared\Outbox\PendingOutboxMessage;

final class InMemoryOutboxRepository implements OutboxRepository
{
    /** @var array<string, PendingOutboxMessage> */
    public array $pending = [];

    /** @var list<PendingOutboxMessage> */
    public array $published = [];

    /** @var list<PendingOutboxMessage> */
    public array $deadLetters = [];

    /** @var list<OutboxMessage> */
    public array $messages = [];

    public function append(OutboxMessage $message): void
    {
        $this->messages[] = $message;
        $this->pending[$message->id] = new PendingOutboxMessage(
            id: $message->id,
            aggregateId: $message->aggregateId,
            eventName: $message->eventName,
            payload: $message->payload,
            occurredAt: $message->occurredAt,
            recordedAt: $message->recordedAt,
            attempts: 0,
        );
    }

    public function claimPending(int $limit, string $workerId, DateTimeImmutable $lockedUntil): array
    {
        return array_slice(array_values($this->pending), 0, $limit);
    }

    public function markPublished(string $messageId, DateTimeImmutable $publishedAt): void
    {
        if (!isset($this->pending[$messageId])) {
            return;
        }

        $this->published[] = $this->pending[$messageId];
        unset($this->pending[$messageId]);
    }

    public function markFailed(string $messageId, int $attempts, string $error, DateTimeImmutable $availableAt): void
    {
        if (!isset($this->pending[$messageId])) {
            return;
        }

        $message = $this->pending[$messageId];
        $this->pending[$messageId] = new PendingOutboxMessage(
            id: $message->id,
            aggregateId: $message->aggregateId,
            eventName: $message->eventName,
            payload: $message->payload,
            occurredAt: $message->occurredAt,
            recordedAt: $message->recordedAt,
            attempts: $attempts,
        );
    }

    public function moveToDeadLetter(PendingOutboxMessage $message, string $error, DateTimeImmutable $failedAt): void
    {
        $this->deadLetters[] = $message;
        unset($this->pending[$message->id]);
    }
}
