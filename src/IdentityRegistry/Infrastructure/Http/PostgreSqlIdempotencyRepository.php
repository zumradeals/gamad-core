<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Infrastructure\Http;

use Gamad\Core\IdentityRegistry\Http\IdempotencyRepository;
use PDO;

final readonly class PostgreSqlIdempotencyRepository implements IdempotencyRepository
{
    public function __construct(private PDO $connection) {}

    public function find(string $actorId, string $key): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT request_hash, status_code, response_body FROM identity_idempotency WHERE actor_id = :actor_id AND idempotency_key = :key'
        );
        $statement->execute(['actor_id' => $actorId, 'key' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : [
            'request_hash' => (string) $row['request_hash'],
            'status' => (int) $row['status_code'],
            'response' => (string) $row['response_body'],
        ];
    }

    public function store(string $actorId, string $key, string $requestHash, int $status, string $response): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO identity_idempotency (
                actor_id, idempotency_key, request_hash, status_code, response_body, created_at
            ) VALUES (
                :actor_id, :key, :request_hash, :status_code, CAST(:response_body AS JSONB), NOW()
            )
            SQL
        );
        $statement->execute([
            'actor_id' => $actorId,
            'key' => $key,
            'request_hash' => $requestHash,
            'status_code' => $status,
            'response_body' => $response,
        ]);
    }
}
