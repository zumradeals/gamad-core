<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Outbox;

use Gamad\Core\Shared\Outbox\OutboxDashboard;
use Gamad\Core\Shared\Outbox\OutboxDashboardRepository;
use PDO;

final readonly class PostgreSqlOutboxDashboardRepository implements OutboxDashboardRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function snapshot(): OutboxDashboard
    {
        $row = $this->connection->query(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE published_at IS NULL) AS pending,
                COUNT(*) FILTER (WHERE published_at IS NULL AND locked_until > NOW()) AS locked,
                COUNT(*) FILTER (WHERE published_at IS NOT NULL) AS published,
                MIN(recorded_at) FILTER (WHERE published_at IS NULL) AS oldest_pending_at,
                MAX(published_at) FILTER (WHERE published_at IS NOT NULL) AS last_published_at
            FROM outbox_messages
            SQL
        )->fetch(PDO::FETCH_ASSOC);

        $deadLetters = (int) $this->connection->query(
            'SELECT COUNT(*) FROM outbox_dead_letters'
        )->fetchColumn();

        return new OutboxDashboard(
            pending: (int) $row['pending'],
            locked: (int) $row['locked'],
            published: (int) $row['published'],
            deadLetters: $deadLetters,
            oldestPendingAt: $row['oldest_pending_at'] !== null ? (string) $row['oldest_pending_at'] : null,
            lastPublishedAt: $row['last_published_at'] !== null ? (string) $row['last_published_at'] : null,
        );
    }
}
