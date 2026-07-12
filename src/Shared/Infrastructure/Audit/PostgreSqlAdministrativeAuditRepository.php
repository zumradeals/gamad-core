<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Audit;

use DateTimeImmutable;
use Gamad\Core\Shared\Audit\AdministrativeAuditRepository;
use Gamad\Core\Shared\Audit\CanonicalJson;
use PDO;

final readonly class PostgreSqlAdministrativeAuditRepository implements AdministrativeAuditRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function record(
        string $actorId,
        string $action,
        string $target,
        int $statusCode,
        DateTimeImmutable $occurredAt,
        array $context = [],
    ): void {
        $previousHash = (string) ($this->connection->query(
            'SELECT record_hash FROM administrative_audit ORDER BY id DESC LIMIT 1'
        )->fetchColumn() ?: str_repeat('0', 64));
        $contextJson = CanonicalJson::encode($context);
        $canonical = implode('|', [
            $previousHash,
            $actorId,
            $action,
            $target,
            (string) $statusCode,
            $occurredAt->format(DATE_ATOM),
            $contextJson,
        ]);
        $recordHash = hash('sha256', $canonical);

        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO administrative_audit (
                actor_id, action, target, status_code, occurred_at, context,
                previous_hash, record_hash
            ) VALUES (
                :actor_id, :action, :target, :status_code, :occurred_at,
                CAST(:context AS JSONB), :previous_hash, :record_hash
            )
            SQL
        );

        $statement->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'target' => $target,
            'status_code' => $statusCode,
            'occurred_at' => $occurredAt->format(DATE_ATOM),
            'context' => $contextJson,
            'previous_hash' => $previousHash,
            'record_hash' => $recordHash,
        ]);
    }
}
