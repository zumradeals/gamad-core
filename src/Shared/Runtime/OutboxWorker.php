<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Runtime;

use DateTimeImmutable;
use Gamad\Core\Shared\Health\HeartbeatRepository;
use Gamad\Core\Shared\Observability\StructuredLogger;
use Gamad\Core\Shared\Outbox\OutboxPublisher;
use Throwable;

final class OutboxWorker
{
    private bool $running = true;

    public function __construct(
        private readonly OutboxPublisher $publisher,
        private readonly HeartbeatRepository $heartbeats,
        private readonly StructuredLogger $logger,
        private readonly string $workerId,
        private readonly int $pollIntervalMilliseconds = 1000,
        private readonly int $heartbeatIntervalSeconds = 15,
    ) {
    }

    public function requestStop(): void
    {
        $this->running = false;
    }

    public function run(): int
    {
        $this->logger->info('outbox_worker_started', ['worker_id' => $this->workerId]);
        $lastHeartbeatAt = null;

        while ($this->running) {
            $now = new DateTimeImmutable();

            try {
                if ($lastHeartbeatAt === null || ($now->getTimestamp() - $lastHeartbeatAt->getTimestamp()) >= $this->heartbeatIntervalSeconds) {
                    $this->heartbeats->beat($this->workerId, $now, ['status' => 'running']);
                    $lastHeartbeatAt = $now;
                }

                $report = $this->publisher->publishPending($now);

                if ($report->claimed > 0) {
                    $this->logger->info('outbox_batch_processed', [
                        'worker_id' => $this->workerId,
                        'claimed' => $report->claimed,
                        'published' => $report->published,
                        'retried' => $report->retried,
                        'dead_lettered' => $report->deadLettered,
                    ]);
                }
            } catch (Throwable $exception) {
                $this->logger->error('outbox_worker_cycle_failed', [
                    'worker_id' => $this->workerId,
                    'exception' => $exception::class,
                    'error' => $exception->getMessage(),
                ]);
            }

            if ($this->running) {
                usleep($this->pollIntervalMilliseconds * 1000);
            }
        }

        try {
            $this->heartbeats->beat($this->workerId, new DateTimeImmutable(), ['status' => 'stopped']);
        } catch (Throwable $exception) {
            $this->logger->error('outbox_worker_stop_heartbeat_failed', [
                'worker_id' => $this->workerId,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->logger->info('outbox_worker_stopped', ['worker_id' => $this->workerId]);

        return 0;
    }
}
