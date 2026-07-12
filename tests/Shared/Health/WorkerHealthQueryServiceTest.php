<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Health;

use DateTimeImmutable;
use Gamad\Core\Shared\Application\WorkerHealthQueryService;
use Gamad\Core\Shared\Health\WorkerStatus;
use Gamad\Core\Shared\Health\WorkerStatusRepository;
use PHPUnit\Framework\TestCase;

final class WorkerHealthQueryServiceTest extends TestCase
{
    public function test_it_reports_fresh_running_worker_as_live_and_ready(): void
    {
        $repository = new class implements WorkerStatusRepository {
            public function listStatuses(DateTimeImmutable $now, int $staleAfterSeconds): array
            {
                return [$this->findStatus('worker-1', $now, $staleAfterSeconds)];
            }

            public function findStatus(string $workerId, DateTimeImmutable $now, int $staleAfterSeconds): ?WorkerStatus
            {
                return new WorkerStatus(
                    workerId: $workerId,
                    lastSeenAt: $now->modify('-5 seconds'),
                    live: true,
                    ready: true,
                    stale: false,
                    metadata: ['status' => 'running'],
                );
            }
        };

        $status = (new WorkerHealthQueryService($repository, 45))->one(
            'worker-1',
            new DateTimeImmutable('2026-07-12T13:00:00+00:00'),
        );

        self::assertTrue($status?->live);
        self::assertTrue($status?->ready);
        self::assertFalse($status?->stale);
    }
}
