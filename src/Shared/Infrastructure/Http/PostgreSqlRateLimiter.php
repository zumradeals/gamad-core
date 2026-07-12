<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Http;

use Gamad\Core\Shared\Http\RateLimiter;
use PDO;

final readonly class PostgreSqlRateLimiter implements RateLimiter
{
    public function __construct(private PDO $connection)
    {
    }

    public function allow(string $key, int $limit, int $windowSeconds): bool
    {
        $windowStartedAt = intdiv(time(), $windowSeconds) * $windowSeconds;
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO rate_limit_buckets (bucket_key, window_started_at, hits, expires_at)
            VALUES (:bucket_key, to_timestamp(:window_started_at), 1, to_timestamp(:expires_at))
            ON CONFLICT (bucket_key, window_started_at) DO UPDATE SET
                hits = rate_limit_buckets.hits + 1
            RETURNING hits
            SQL
        );
        $statement->execute([
            'bucket_key' => hash('sha256', $key),
            'window_started_at' => $windowStartedAt,
            'expires_at' => $windowStartedAt + $windowSeconds,
        ]);

        return (int) $statement->fetchColumn() <= $limit;
    }
}
