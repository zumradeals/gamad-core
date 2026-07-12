<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Health;

use DateTimeImmutable;

interface WorkerStatusRepository
{
    /** @return list<WorkerStatus> */
    public function listStatuses(DateTimeImmutable $now, int $staleAfterSeconds): array;

    public function findStatus(string $workerId, DateTimeImmutable $now, int $staleAfterSeconds): ?WorkerStatus;
}
