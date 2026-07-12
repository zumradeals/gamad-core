<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

use DateTimeImmutable;

interface OutboxRepository
{
    public function append(OutboxMessage $message): void;

    /** @return list<PendingOutboxMessage> */
    public function claimPending(
        int $limit,
        string $workerId,
        DateTimeImmutable $lockedUntil,
    ): array;

    public function markPublished(string $messageId, DateTimeImmutable $publishedAt): void;

    public function markFailed(
        string $messageId,
        int $attempts,
        string $error,
        DateTimeImmutable $availableAt,
    ): void;

    public function moveToDeadLetter(
        PendingOutboxMessage $message,
        string $error,
        DateTimeImmutable $failedAt,
    ): void;
}
