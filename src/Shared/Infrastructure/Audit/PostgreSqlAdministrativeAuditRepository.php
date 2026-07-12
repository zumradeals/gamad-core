<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Audit;

use DateTimeImmutable;
use Gamad\Core\Shared\Audit\AdministrativeAuditRepository;
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
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO administrative_audit (
                actor_id, action, target, status_code, occurred_at, context
            ) VALUES (
                :actor_id, :action, :target, :status_code, :occurred_at, CAST(:context AS JSONB)
            )
            SQL
        );

        $statement->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'target' => $target,
            'status_code' => $statusCode,
            'occurred_at' => $occurredAt->format(DATE_ATOM),
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
        ]);
    }
}
