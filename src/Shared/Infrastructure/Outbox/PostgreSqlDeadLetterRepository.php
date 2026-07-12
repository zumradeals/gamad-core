<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Outbox;

use DateTimeImmutable;
use Gamad\Core\Shared\Outbox\DeadLetterMessage;
use Gamad\Core\Shared\Outbox\DeadLetterRepository;
use PDO;

final readonly class PostgreSqlDeadLetterRepository implements DeadLetterRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function list(int $limit = 100, int $offset = 0): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, aggregate_id, event_name, payload, attempts, last_error, failed_at FROM outbox_dead_letters ORDER BY failed_at DESC LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): DeadLetterMessage => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(string $messageId): ?DeadLetterMessage
    {
        $statement = $this->connection->prepare(
            'SELECT id, aggregate_id, event_name, payload, attempts, last_error, failed_at FROM outbox_dead_letters WHERE id = :id'
        );
        $statement->execute(['id' => $messageId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function replay(string $messageId): bool
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            WITH restored AS (
                DELETE FROM outbox_dead_letters
                WHERE id = :id
                RETURNING id, aggregate_id, event_name, payload, occurred_at, recorded_at
            )
            INSERT INTO outbox_messages (
                id, aggregate_id, event_name, payload, occurred_at, recorded_at,
                published_at, attempts, last_error, available_at, locked_until, locked_by
            )
            SELECT id, aggregate_id, event_name, payload, occurred_at, recorded_at,
                   NULL, 0, NULL, NOW(), NULL, NULL
            FROM restored
            RETURNING id
            SQL
        );
        $statement->execute(['id' => $messageId]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): DeadLetterMessage
    {
        $payload = json_decode((string) $row['payload'], true);

        return new DeadLetterMessage(
            id: (string) $row['id'],
            aggregateId: (string) $row['aggregate_id'],
            eventName: (string) $row['event_name'],
            payload: is_array($payload) ? $payload : [],
            attempts: (int) $row['attempts'],
            lastError: (string) $row['last_error'],
            failedAt: new DateTimeImmutable((string) $row['failed_at']),
        );
    }
}
