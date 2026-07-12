<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Http;

use Gamad\Core\Shared\Infrastructure\Http\PostgreSqlRateLimiter;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlRateLimiterConcurrencyTest extends TestCase
{
    public function test_multiple_connections_share_the_same_limit_bucket(): void
    {
        $dsn = getenv('GAMAD_TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set GAMAD_TEST_PG_DSN to run PostgreSQL integration tests.');
        }

        $connectionA = $this->connection($dsn);
        $connectionB = $this->connection($dsn);
        $connectionA->exec('DROP TABLE IF EXISTS rate_limit_buckets');
        $connectionA->exec((string) file_get_contents(__DIR__ . '/../../../database/migrations/008_create_rate_limit_buckets.sql'));

        $limiterA = new PostgreSqlRateLimiter($connectionA);
        $limiterB = new PostgreSqlRateLimiter($connectionB);

        self::assertTrue($limiterA->allow('actor:path', 2, 60));
        self::assertTrue($limiterB->allow('actor:path', 2, 60));
        self::assertFalse($limiterA->allow('actor:path', 2, 60));
        self::assertSame(3, (int) $connectionA->query('SELECT hits FROM rate_limit_buckets')->fetchColumn());
    }

    private function connection(string $dsn): PDO
    {
        return new PDO(
            $dsn,
            getenv('GAMAD_TEST_PG_USER') ?: null,
            getenv('GAMAD_TEST_PG_PASSWORD') ?: null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
