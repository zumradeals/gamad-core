<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

use DateTimeImmutable;

final readonly class PendingOutboxMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $aggregateId,
        public string $eventName,
        public array $payload,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $recordedAt,
        public int $attempts,
    ) {
    }
}
