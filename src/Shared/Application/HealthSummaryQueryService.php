<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Application;

use DateTimeImmutable;
use Gamad\Core\Shared\Health\HealthSummary;
use Gamad\Core\Shared\Health\WorkerStatusRepository;

final readonly class HealthSummaryQueryService
{
    public function __construct(
        private WorkerStatusRepository $workers,
        private int $staleAfterSeconds = 45,
    ) {
    }

    public function summary(?DateTimeImmutable $now = null): HealthSummary
    {
        $statuses = $this->workers->listStatuses($now ?? new DateTimeImmutable(), $this->staleAfterSeconds);
        $live = count(array_filter($statuses, static fn ($status): bool => $status->live));
        $ready = count(array_filter($statuses, static fn ($status): bool => $status->ready));
        $stale = count(array_filter($statuses, static fn ($status): bool => $status->stale));

        return new HealthSummary(
            healthy: $ready > 0 && $stale === 0,
            liveWorkers: $live,
            readyWorkers: $ready,
            staleWorkers: $stale,
            workers: $statuses,
        );
    }
}
