<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Health;

final readonly class HealthSummary
{
    /** @param list<WorkerStatus> $workers */
    public function __construct(
        public bool $healthy,
        public int $liveWorkers,
        public int $readyWorkers,
        public int $staleWorkers,
        public array $workers,
    ) {
    }
}
