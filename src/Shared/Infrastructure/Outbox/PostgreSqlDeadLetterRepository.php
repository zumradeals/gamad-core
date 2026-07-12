<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Outbox;

use Gamad\Core\Shared\Outbox\DeadLetterRepository;
use PDO;

final readonly class PostgreSqlDeadLetterRepository implements DeadLetterRepository
{
    public function __construct(private PDO $connection)
    {
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
}
