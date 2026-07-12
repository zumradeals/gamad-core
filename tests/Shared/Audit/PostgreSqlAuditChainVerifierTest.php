<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Audit;

use DateTimeImmutable;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAdministrativeAuditRepository;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAuditChainVerifier;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlAuditChainVerifierTest extends TestCase
{
    public function test_it_verifies_the_chain_and_detects_tampering(): void
    {
        $dsn = getenv('GAMAD_TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set GAMAD_TEST_PG_DSN to run PostgreSQL integration tests.');
        }

        $connection = new PDO($dsn, getenv('GAMAD_TEST_PG_USER') ?: null, getenv('GAMAD_TEST_PG_PASSWORD') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $connection->exec('DROP TABLE IF EXISTS administrative_audit CASCADE');
        $connection->exec((string) file_get_contents(__DIR__ . '/../../../database/migrations/006_create_administrative_audit.sql'));
        $connection->exec((string) file_get_contents(__DIR__ . '/../../../database/migrations/007_harden_administrative_audit.sql'));

        $repository = new PostgreSqlAdministrativeAuditRepository($connection);
        $repository->record('GAM-PER-000001', 'health.read', '/admin/runtime/health', 200, new DateTimeImmutable('2026-07-12T14:00:00+00:00'));
        $repository->record('GAM-PER-000001', 'outbox.read', '/admin/runtime/outbox', 200, new DateTimeImmutable('2026-07-12T14:01:00+00:00'));

        $valid = (new PostgreSqlAuditChainVerifier($connection))->verify();
        self::assertTrue($valid->valid);
        self::assertSame(2, $valid->verifiedRecords);

        $connection->exec('DROP TRIGGER administrative_audit_no_update ON administrative_audit');
        $connection->exec("UPDATE administrative_audit SET record_hash = repeat('f', 64) WHERE id = 2");

        $invalid = (new PostgreSqlAuditChainVerifier($connection))->verify();
        self::assertFalse($invalid->valid);
        self::assertSame(2, $invalid->failedRecordId);
    }
}
