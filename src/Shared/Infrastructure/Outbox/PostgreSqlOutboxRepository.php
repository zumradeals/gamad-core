<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Outbox;

use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;
use PDO;

final readonly class PostgreSqlOutboxRepository implements OutboxRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function append(OutboxMessage $message): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO outbox_messages (
                id,
                aggregate_id,
                event_name,
                payload,
                occurred_at,
                recorded_at,
                published_at,
                attempts
            ) VALUES (
                :id,
                :aggregate_id,
                :event_name,
                CAST(:payload AS JSONB),
                :occurred_at,
                :recorded_at,
                NULL,
                0
            )
            SQL
        );

        $statement->execute([
            'id' => $message->id,
            'aggregate_id' => $message->aggregateId,
            'event_name' => $message->eventName,
            'payload' => $message->payloadAsJson(),
            'occurred_at' => $message->occurredAt->format(DATE_ATOM),
            'recorded_at' => $message->recordedAt->format(DATE_ATOM),
        ]);
    }
}
