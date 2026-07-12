<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

final readonly class OutboxDashboard
{
    public function __construct(
        public int $pending,
        public int $locked,
        public int $published,
        public int $deadLetters,
        public ?string $oldestPendingAt,
        public ?string $lastPublishedAt,
    ) {
    }
}
