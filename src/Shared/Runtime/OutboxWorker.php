<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Runtime;

use DateTimeImmutable;
use Gamad\Core\Shared\Health\HeartbeatRepository;
use Gamad\Core\Shared\Metrics\MetricsCollector;
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
        private readonly MetricsCollector $metrics,
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
        $labels = ['worker_id' => $this->workerId];
        $this->logger->info('outbox_worker_started', $labels);
        $this->metrics->increment('gamad_outbox_worker_starts_total', 1, $labels);
        $this->metrics->gauge('gamad_outbox_worker_ready', 1, $labels);
        $lastHeartbeatAt = null;

        while ($this->running) {
            $now = new DateTimeImmutable();
            $cycleStartedAt = microtime(true);

            try {
                if ($lastHeartbeatAt === null || ($now->getTimestamp() - $lastHeartbeatAt->getTimestamp()) >= $this->heartbeatIntervalSeconds) {
                    $this->heartbeats->beat($this->workerId, $now, ['status' => 'running']);
                    $lastHeartbeatAt = $now;
                }

                $report = $this->publisher->publishPending($now);
                $this->metrics->increment('gamad_outbox_claimed_total', $report->claimed, $labels);
                $this->metrics->increment('gamad_outbox_published_total', $report->published, $labels);
                $this->metrics->increment('gamad_outbox_retried_total', $report->retried, $labels);
                $this->metrics->increment('gamad_outbox_dead_lettered_total', $report->deadLettered, $labels);

                if ($report->claimed > 0) {
                    $this->logger->info('outbox_batch_processed', $labels + [
                        'claimed' => $report->claimed,
                        'published' => $report->published,
                        'retried' => $report->retried,
                        'dead_lettered' => $report->deadLettered,
                    ]);
                }
            } catch (Throwable $exception) {
                $this->metrics->increment('gamad_outbox_worker_cycle_failures_total', 1, $labels);
                $this->logger->error('outbox_worker_cycle_failed', $labels + [
                    'exception' => $exception::class,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                $this->metrics->gauge(
                    'gamad_outbox_worker_cycle_duration_seconds',
                    microtime(true) - $cycleStartedAt,
                    $labels,
                );
            }

            if ($this->running) {
                usleep($this->pollIntervalMilliseconds * 1000);
            }
        }

        $this->metrics->gauge('gamad_outbox_worker_ready', 0, $labels);

        try {
            $this->heartbeats->beat($this->workerId, new DateTimeImmutable(), ['status' => 'stopped']);
        } catch (Throwable $exception) {
            $this->logger->error('outbox_worker_stop_heartbeat_failed', $labels + [
                'error' => $exception->getMessage(),
            ]);
        }

        $this->logger->info('outbox_worker_stopped', $labels);

        return 0;
    }
}
