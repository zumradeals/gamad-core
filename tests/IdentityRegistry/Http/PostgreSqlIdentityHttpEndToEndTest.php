<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Http;

use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentityHandler;
use Gamad\Core\IdentityRegistry\Application\IdentityLifecycleService;
use Gamad\Core\IdentityRegistry\Http\IdentityHttpController;
use Gamad\Core\IdentityRegistry\Http\IdentityRoutes;
use Gamad\Core\IdentityRegistry\Infrastructure\Http\PostgreSqlIdempotencyRepository;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\PostgreSqlIdentityIdentifierAuthority;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\PostgreSqlIdentityRepository;
use Gamad\Core\IdentityRegistry\Infrastructure\Policy\AllowConfiguredIdentityTypesPolicy;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Http\AdministrativeHttpKernel;
use Gamad\Core\Shared\Http\AuthenticatedActor;
use Gamad\Core\Shared\Http\AuthenticationAdapter;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\OpenApiResponseValidator;
use Gamad\Core\Shared\Http\RateLimiter;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\ScopeAuthorizationMiddleware;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAdministrativeAuditRepository;
use Gamad\Core\Shared\Infrastructure\Metrics\PostgreSqlMetricsCollector;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxRepository;
use Gamad\Core\Shared\Infrastructure\Persistence\PdoTransactionManager;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlIdentityHttpEndToEndTest extends TestCase
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
        foreach (['identity_identifier_sequences', 'identity_idempotency', 'administrative_audit', 'operational_metrics', 'rate_limit_buckets', 'outbox_dead_letters', 'outbox_messages', 'identities'] as $table) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table . ' CASCADE');
        }
        foreach ([1, 2, 3, 5, 6, 7, 8, 9, 10] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    public function test_authoritative_registration_bulk_search_and_lifecycle(): void
    {
        $kernel = $this->kernel();
        $singleBody = json_encode(['identity_type' => 'person'], JSON_THROW_ON_ERROR);
        $singleHeaders = ['Idempotency-Key' => 'register-person-one'];

        $created = $kernel->handle(new Request('POST', '/identities', $singleHeaders, body: $singleBody));
        $replayed = $kernel->handle(new Request('POST', '/identities', $singleHeaders, body: $singleBody));
        $bulk = $kernel->handle(new Request('POST', '/identities/bulk', ['Idempotency-Key' => 'bulk-organizations'], body: json_encode([
            'items' => [['identity_type' => 'organization'], ['identity_type' => 'organization']],
        ], JSON_THROW_ON_ERROR)));
        $createdPayload = json_decode($created->body, true, flags: JSON_THROW_ON_ERROR);
        $publicId = $createdPayload['identity_id'];
        $read = $kernel->handle(new Request('GET', '/identities/' . $publicId));
        $search = $kernel->handle(new Request('GET', '/identities', query: ['type' => 'organization', 'limit' => '1']));
        $suspended = $kernel->handle(new Request('POST', '/identities/' . $publicId . '/suspend'));

        self::assertSame('GAM-PER-000001', $publicId);
        self::assertSame($created->body, $replayed->body);
        self::assertSame(201, $bulk->status);
        self::assertSame(200, $read->status);
        self::assertNotNull(json_decode($search->body, true, flags: JSON_THROW_ON_ERROR)['next_cursor']);
        self::assertSame('suspended', json_decode($suspended->body, true, flags: JSON_THROW_ON_ERROR)['status']);
        self::assertSame(3, (int) $this->connection->query('SELECT COUNT(*) FROM identities')->fetchColumn());
        self::assertSame(3, (int) $this->connection->query('SELECT COUNT(DISTINCT internal_id) FROM identities')->fetchColumn());
        self::assertSame(4, (int) $this->connection->query('SELECT COUNT(*) FROM outbox_messages')->fetchColumn());
        self::assertSame(3.0, (float) $this->connection->query("SELECT SUM(value) FROM operational_metrics WHERE name = 'gamad_identity_registered_total'")->fetchColumn());
    }

    private function kernel(): AdministrativeHttpKernel
    {
        $repository = new PostgreSqlIdentityRepository($this->connection);
        $transactions = new PdoTransactionManager($this->connection);
        $persister = new AtomicIdentityPersister(
            $repository,
            new PostgreSqlOutboxRepository($this->connection),
            new DomainEventCollector(),
            $transactions,
        );
        $controller = new IdentityHttpController(
            new RegisterIdentityHandler(
                new PostgreSqlIdentityIdentifierAuthority($this->connection),
                new AllowConfiguredIdentityTypesPolicy(),
                $persister,
                new PostgreSqlMetricsCollector($this->connection),
            ),
            $repository,
            $repository,
            new IdentityLifecycleService($repository, $persister),
            new PostgreSqlIdempotencyRepository($this->connection),
            $transactions,
        );

        return new AdministrativeHttpKernel(
            new OpenApiRequestValidator(IdentityRoutes::forController($controller)),
            new OpenApiResponseValidator(),
            new IdentityTestAuthenticationAdapter(),
            new ScopeAuthorizationMiddleware(),
            new IdentityTestRateLimiter(),
            new PostgreSqlAdministrativeAuditRepository($this->connection),
        );
    }
}

final readonly class IdentityTestAuthenticationAdapter implements AuthenticationAdapter
{
    public function authenticate(Request $request): ?AuthenticatedActor
    {
        return new AuthenticatedActor('GAM-PER-000001', [
            'core.identity.register',
            'core.identity.read',
            'core.identity.lifecycle.manage',
        ]);
    }
}

final readonly class IdentityTestRateLimiter implements RateLimiter
{
    public function allow(string $key, int $limit, int $windowSeconds): bool { return true; }
}
