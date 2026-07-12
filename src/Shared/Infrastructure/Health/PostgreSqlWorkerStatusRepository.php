<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Health;

use DateTimeImmutable;
use Gamad\Core\Shared\Health\WorkerStatus;
use Gamad\Core\Shared\Health\WorkerStatusRepository;
use PDO;

final readonly class PostgreSqlWorkerStatusRepository implements WorkerStatusRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function listStatuses(DateTimeImmutable $now, int $staleAfterSeconds): array
    {
        $rows = $this->connection->query(
            'SELECT worker_id, last_seen_at, metadata FROM worker_heartbeats ORDER BY worker_id'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn (array $row): WorkerStatus => $this->map($row, $now, $staleAfterSeconds),
            $rows,
        );
    }

    public function findStatus(string $workerId, DateTimeImmutable $now, int $staleAfterSeconds): ?WorkerStatus
    {
        $statement = $this->connection->prepare(
            'SELECT worker_id, last_seen_at, metadata FROM worker_heartbeats WHERE worker_id = :worker_id'
        );
        $statement->execute(['worker_id' => $workerId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row, $now, $staleAfterSeconds);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row, DateTimeImmutable $now, int $staleAfterSeconds): WorkerStatus
    {
        $lastSeenAt = new DateTimeImmutable((string) $row['last_seen_at']);
        $metadata = json_decode((string) $row['metadata'], true);
        $metadata = is_array($metadata) ? $metadata : [];
        $stale = ($now->getTimestamp() - $lastSeenAt->getTimestamp()) > $staleAfterSeconds;
        $status = (string) ($metadata['status'] ?? 'unknown');

        return new WorkerStatus(
            workerId: (string) $row['worker_id'],
            lastSeenAt: $lastSeenAt,
            live: !$stale,
            ready: !$stale && $status === 'running',
            stale: $stale,
            metadata: $metadata,
        );
    }
}
