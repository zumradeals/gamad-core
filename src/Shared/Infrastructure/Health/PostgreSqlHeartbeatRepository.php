<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Health;

use DateTimeImmutable;
use Gamad\Core\Shared\Health\HeartbeatRepository;
use JsonException;
use PDO;

final readonly class PostgreSqlHeartbeatRepository implements HeartbeatRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @throws JsonException */
    public function beat(string $workerId, DateTimeImmutable $at, array $metadata = []): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO worker_heartbeats (worker_id, last_seen_at, metadata)
            VALUES (:worker_id, :last_seen_at, CAST(:metadata AS JSONB))
            ON CONFLICT (worker_id) DO UPDATE SET
                last_seen_at = EXCLUDED.last_seen_at,
                metadata = EXCLUDED.metadata
            SQL
        );

        $statement->execute([
            'worker_id' => $workerId,
            'last_seen_at' => $at->format(DATE_ATOM),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
