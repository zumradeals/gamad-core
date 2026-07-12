<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Application;

use DateTimeImmutable;
use Gamad\Core\Shared\Health\WorkerStatus;
use Gamad\Core\Shared\Health\WorkerStatusRepository;

final readonly class WorkerHealthQueryService
{
    public function __construct(
        private WorkerStatusRepository $statuses,
        private int $staleAfterSeconds = 45,
    ) {
    }

    /** @return list<WorkerStatus> */
    public function all(?DateTimeImmutable $now = null): array
    {
        return $this->statuses->listStatuses($now ?? new DateTimeImmutable(), $this->staleAfterSeconds);
    }

    public function one(string $workerId, ?DateTimeImmutable $now = null): ?WorkerStatus
    {
        return $this->statuses->findStatus($workerId, $now ?? new DateTimeImmutable(), $this->staleAfterSeconds);
    }
}
