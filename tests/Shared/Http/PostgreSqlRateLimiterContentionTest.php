<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Http;

use Gamad\Core\Shared\Infrastructure\Http\PostgreSqlRateLimiter;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlRateLimiterContentionTest extends TestCase
{
    public function test_two_instances_share_one_atomic_limit_bucket(): void
    {
        $dsn = getenv('GAMAD_TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set GAMAD_TEST_PG_DSN to run PostgreSQL integration tests.');
        }

        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        $firstConnection = new PDO($dsn, getenv('GAMAD_TEST_PG_USER') ?: null, getenv('GAMAD_TEST_PG_PASSWORD') ?: null, $options);
        $secondConnection = new PDO($dsn, getenv('GAMAD_TEST_PG_USER') ?: null, getenv('GAMAD_TEST_PG_PASSWORD') ?: null, $options);
        $firstConnection->exec('TRUNCATE rate_limit_buckets');

        $first = new PostgreSqlRateLimiter($firstConnection);
        $second = new PostgreSqlRateLimiter($secondConnection);
        $decisions = [];

        for ($attempt = 1; $attempt <= 20; ++$attempt) {
            $decisions[] = ($attempt % 2 === 0 ? $second : $first)->allow('GAM-GAT-PER-000001:/admin/runtime/health', 10, 60);
        }

        self::assertSame(10, count(array_filter($decisions)));
        self::assertSame(20, (int) $firstConnection->query('SELECT hits FROM rate_limit_buckets')->fetchColumn());
        self::assertSame(1, (int) $firstConnection->query('SELECT COUNT(*) FROM rate_limit_buckets')->fetchColumn());
    }
}
