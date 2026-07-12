<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

use DateTimeImmutable;

final readonly class DeadLetterMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $aggregateId,
        public string $eventName,
        public array $payload,
        public int $attempts,
        public string $lastError,
        public DateTimeImmutable $failedAt,
    ) {
    }
}
