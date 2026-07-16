<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure\Outbox;

use DateTimeImmutable;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;
use Gamad\Core\Shared\Outbox\PendingOutboxMessage;
use JsonException;
use PDO;

/**
 * ADR-0022 — the same OutboxRepository contract as
 * Shared\Infrastructure\Outbox\PostgreSqlOutboxRepository, but reading and
 * writing the dedicated `access_decisions_outbox` table instead of
 * `outbox_messages`, so AccessDecisionMade's volume never crowds out
 * business-domain events in the shared worker's queue.
 */
final readonly class PostgreSqlAccessDecisionsOutboxRepository implements OutboxRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function append(OutboxMessage $message): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO access_decisions_outbox (
                id, aggregate_id, event_name, payload, occurred_at, recorded_at,
                published_at, attempts, available_at, locked_until, locked_by
            ) VALUES (
                :id, :aggregate_id, :event_name, CAST(:payload AS JSONB),
                :occurred_at, :recorded_at, NULL, 0, :recorded_at, NULL, NULL
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

    public function claimPending(int $limit, string $workerId, DateTimeImmutable $lockedUntil): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            WITH candidates AS (
                SELECT id
                FROM access_decisions_outbox
                WHERE published_at IS NULL
                  AND available_at <= NOW()
                  AND (locked_until IS NULL OR locked_until < NOW())
                ORDER BY recorded_at, id
                FOR UPDATE SKIP LOCKED
                LIMIT :limit
            )
            UPDATE access_decisions_outbox AS message
            SET locked_until = :locked_until,
                locked_by = :worker_id
            FROM candidates
            WHERE message.id = candidates.id
            RETURNING message.id, message.aggregate_id, message.event_name,
                      message.payload, message.occurred_at, message.recorded_at,
                      message.attempts
            SQL
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('locked_until', $lockedUntil->format(DATE_ATOM));
        $statement->bindValue('worker_id', $workerId);
        $statement->execute();

        $messages = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $messages[] = new PendingOutboxMessage(
                id: (string) $row['id'],
                aggregateId: (string) $row['aggregate_id'],
                eventName: (string) $row['event_name'],
                payload: $this->decodePayload((string) $row['payload']),
                occurredAt: new DateTimeImmutable((string) $row['occurred_at']),
                recordedAt: new DateTimeImmutable((string) $row['recorded_at']),
                attempts: (int) $row['attempts'],
            );
        }

        return $messages;
    }

    public function markPublished(string $messageId, DateTimeImmutable $publishedAt): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE access_decisions_outbox
            SET published_at = :published_at,
                locked_until = NULL,
                locked_by = NULL,
                last_error = NULL
            WHERE id = :id
            SQL
        );
        $statement->execute(['id' => $messageId, 'published_at' => $publishedAt->format(DATE_ATOM)]);
    }

    public function markFailed(string $messageId, int $attempts, string $error, DateTimeImmutable $availableAt): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE access_decisions_outbox
            SET attempts = :attempts,
                last_error = :last_error,
                available_at = :available_at,
                locked_until = NULL,
                locked_by = NULL
            WHERE id = :id
            SQL
        );
        $statement->execute([
            'id' => $messageId,
            'attempts' => $attempts,
            'last_error' => $error,
            'available_at' => $availableAt->format(DATE_ATOM),
        ]);
    }

    public function moveToDeadLetter(PendingOutboxMessage $message, string $error, DateTimeImmutable $failedAt): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            WITH moved AS (
                DELETE FROM access_decisions_outbox
                WHERE id = :id
                RETURNING id, aggregate_id, event_name, payload, occurred_at,
                          recorded_at, attempts
            )
            INSERT INTO outbox_dead_letters (
                id, aggregate_id, event_name, payload, occurred_at, recorded_at,
                attempts, last_error, failed_at
            )
            SELECT id, aggregate_id, event_name, payload, occurred_at, recorded_at,
                   attempts + 1, :last_error, :failed_at
            FROM moved
            SQL
        );
        $statement->execute([
            'id' => $message->id,
            'last_error' => $error,
            'failed_at' => $failedAt->format(DATE_ATOM),
        ]);
    }

    /** @return array<string, mixed> */
    private function decodePayload(string $payload): array
    {
        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException('Invalid JSON payload stored in the access decisions outbox.', previous: $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
