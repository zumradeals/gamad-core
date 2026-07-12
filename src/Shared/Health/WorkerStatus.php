<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Health;

use DateTimeImmutable;

final readonly class WorkerStatus
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $workerId,
        public DateTimeImmutable $lastSeenAt,
        public bool $live,
        public bool $ready,
        public bool $stale,
        public array $metadata = [],
    ) {
    }
}
