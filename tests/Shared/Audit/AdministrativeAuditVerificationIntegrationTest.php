<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Audit;

use DateTimeImmutable;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAdministrativeAuditRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class AdministrativeAuditVerificationIntegrationTest extends TestCase
{
    public function test_persisted_chain_can_be_recomputed_without_mismatch(): void
    {
        $dsn = getenv('GAMAD_TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set GAMAD_TEST_PG_DSN to run PostgreSQL integration tests.');
        }

        $connection = new PDO(
            $dsn,
            getenv('GAMAD_TEST_PG_USER') ?: null,
            getenv('GAMAD_TEST_PG_PASSWORD') ?: null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $connection->exec('TRUNCATE administrative_audit RESTART IDENTITY');
        $repository = new PostgreSqlAdministrativeAuditRepository($connection);

        $repository->record('GAM-PER-000001', 'health.read', '/admin/runtime/health', 200, new DateTimeImmutable('2026-07-12T14:00:00+00:00'), ['request_id' => 'r1']);
        $repository->record('GAM-PER-000001', 'outbox.read', '/admin/runtime/outbox', 200, new DateTimeImmutable('2026-07-12T14:01:00+00:00'), ['request_id' => 'r2']);

        $rows = $connection->query(
            'SELECT actor_id, action, target, status_code, occurred_at, context, previous_hash, record_hash FROM administrative_audit ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $previousHash = str_repeat('0', 64);

        foreach ($rows as $row) {
            $context = json_decode((string) $row['context'], true, flags: JSON_THROW_ON_ERROR);
            $canonical = implode('|', [
                $previousHash,
                (string) $row['actor_id'],
                (string) $row['action'],
                (string) $row['target'],
                (string) $row['status_code'],
                (new DateTimeImmutable((string) $row['occurred_at']))->format(DATE_ATOM),
                json_encode($context, JSON_THROW_ON_ERROR),
            ]);
            self::assertSame($previousHash, $row['previous_hash']);
            self::assertSame(hash('sha256', $canonical), $row['record_hash']);
            $previousHash = (string) $row['record_hash'];
        }

        self::assertCount(2, $rows);
    }
}
