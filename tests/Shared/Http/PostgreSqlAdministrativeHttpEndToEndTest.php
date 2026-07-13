<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Http;

use Gamad\Core\Shared\Application\HealthSummaryQueryService;
use Gamad\Core\Shared\Application\ReplayDeadLetterHandler;
use Gamad\Core\Shared\Http\AdministrativeHttpKernel;
use Gamad\Core\Shared\Http\AdministrativeRoutes;
use Gamad\Core\Shared\Http\AdministrativeRuntimeController;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\OpenApiResponseValidator;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\ScopeAuthorizationMiddleware;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAdministrativeAuditRepository;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAuditChainVerifier;
use Gamad\Core\Shared\Infrastructure\Health\PostgreSqlWorkerStatusRepository;
use Gamad\Core\Shared\Infrastructure\Http\BearerTokenAuthenticationAdapter;
use Gamad\Core\Shared\Infrastructure\Http\EnvironmentTokenVerifier;
use Gamad\Core\Shared\Infrastructure\Http\InMemoryRateLimiter;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlDeadLetterRepository;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxDashboardRepository;
use Gamad\Core\Shared\Infrastructure\Security\EnvironmentAuthorizationService;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlAdministrativeHttpEndToEndTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $dsn = getenv('GAMAD_TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set GAMAD_TEST_PG_DSN to run PostgreSQL integration tests.');
        }

        $this->connection = new PDO(
            $dsn,
            getenv('GAMAD_TEST_PG_USER') ?: null,
            getenv('GAMAD_TEST_PG_PASSWORD') ?: null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $this->connection->exec('DROP TABLE IF EXISTS administrative_audit CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS worker_heartbeats CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS operational_metrics CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_dead_letters CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_messages CASCADE');

        foreach ([2, 3, 4, 5, 6, 7] as $migration) {
            $matches = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $migration) . '_*.sql');
            self::assertNotEmpty($matches);
            $this->connection->exec((string) file_get_contents($matches[0]));
        }
    }

    public function test_authorized_health_request_is_secured_validated_and_audited(): void
    {
        $kernel = $this->kernel();
        $response = $kernel->handle(new Request('GET', '/admin/runtime/health', [
            'Authorization' => 'Bearer integration-token',
            'X-Request-ID' => 'integration-request-1',
        ]));

        self::assertSame(200, $response->status);
        self::assertSame('integration-request-1', $response->headers['X-Request-ID']);
        self::assertSame('DENY', $response->headers['X-Frame-Options']);
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM administrative_audit')->fetchColumn());
        self::assertSame(64, strlen((string) $this->connection->query('SELECT record_hash FROM administrative_audit')->fetchColumn()));
    }

    public function test_administrative_audit_cannot_be_updated_or_deleted(): void
    {
        $this->kernel()->handle(new Request('GET', '/admin/runtime/health', [
            'Authorization' => 'Bearer integration-token',
        ]));

        foreach (['UPDATE administrative_audit SET action = \'tampered\'', 'DELETE FROM administrative_audit'] as $sql) {
            try {
                $this->connection->exec($sql);
                self::fail('Append-only audit mutation should have failed.');
            } catch (PDOException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_authorized_audit_verify_request_reports_a_valid_chain(): void
    {
        $kernel = $this->kernel();
        $kernel->handle(new Request('GET', '/admin/runtime/health', [
            'Authorization' => 'Bearer integration-token',
        ]));

        $response = $kernel->handle(new Request('GET', '/admin/runtime/audit/verify', [
            'Authorization' => 'Bearer integration-token',
        ]));

        self::assertSame(200, $response->status);
        $body = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($body['valid']);
        self::assertGreaterThan(0, $body['verified_count']);
        self::assertNull($body['failed_record_id']);
    }

    public function test_audit_verify_reports_a_broken_chain(): void
    {
        $kernel = $this->kernel();
        $kernel->handle(new Request('GET', '/admin/runtime/health', [
            'Authorization' => 'Bearer integration-token',
        ]));
        $this->connection->exec('ALTER TABLE administrative_audit DISABLE TRIGGER ALL');
        $this->connection->exec("UPDATE administrative_audit SET record_hash = repeat('0', 64)");
        $this->connection->exec('ALTER TABLE administrative_audit ENABLE TRIGGER ALL');

        $response = $kernel->handle(new Request('GET', '/admin/runtime/audit/verify', [
            'Authorization' => 'Bearer integration-token',
        ]));

        self::assertSame(200, $response->status);
        $body = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($body['valid']);
        self::assertNotNull($body['failed_record_id']);
    }

    private function kernel(): AdministrativeHttpKernel
    {
        $scopes = [
            'core.runtime.health.read',
            'core.outbox.dashboard.read',
            'core.audit.verify.read',
            'core.outbox.dead_letter.read',
            'core.outbox.dead_letter.replay',
        ];
        $deadLetters = new PostgreSqlDeadLetterRepository($this->connection);
        $controller = new AdministrativeRuntimeController(
            new HealthSummaryQueryService(new PostgreSqlWorkerStatusRepository($this->connection), 45),
            new PostgreSqlOutboxDashboardRepository($this->connection),
            $deadLetters,
            new ReplayDeadLetterHandler(
                $deadLetters,
                new EnvironmentAuthorizationService(['GAM-PER-000001' => $scopes]),
            ),
            new PostgreSqlAuditChainVerifier($this->connection),
        );
        $routes = AdministrativeRoutes::forController($controller);
        $tokens = json_encode([
            'integration-token' => ['actor_id' => 'GAM-PER-000001', 'scopes' => $scopes],
        ], JSON_THROW_ON_ERROR);

        return new AdministrativeHttpKernel(
            new OpenApiRequestValidator($routes),
            new OpenApiResponseValidator(),
            new BearerTokenAuthenticationAdapter(EnvironmentTokenVerifier::fromJson($tokens)),
            new ScopeAuthorizationMiddleware(),
            new InMemoryRateLimiter(),
            new PostgreSqlAdministrativeAuditRepository($this->connection),
        );
    }
}
