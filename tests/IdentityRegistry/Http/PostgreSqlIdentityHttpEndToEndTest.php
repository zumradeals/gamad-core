<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Http;

use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentityHandler;
use Gamad\Core\IdentityRegistry\Application\IdentityLifecycleService;
use Gamad\Core\IdentityRegistry\Http\IdentityHttpController;
use Gamad\Core\IdentityRegistry\Http\IdentityRoutes;
use Gamad\Core\IdentityRegistry\Infrastructure\Http\PostgreSqlIdempotencyRepository;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\PostgreSqlIdentityRepository;
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

        foreach (['identity_idempotency', 'administrative_audit', 'rate_limit_buckets', 'outbox_dead_letters', 'outbox_messages', 'identities'] as $table) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table . ' CASCADE');
        }

        foreach ([1, 2, 3, 6, 7, 8, 9] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    public function test_registration_is_idempotent_queryable_audited_and_lifecycle_managed(): void
    {
        $kernel = $this->kernel();
        $body = json_encode([
            'identity_id' => 'GAM-PER-700001',
            'identity_type' => 'person',
        ], JSON_THROW_ON_ERROR);
        $headers = ['Idempotency-Key' => 'register-person-700001'];

        $created = $kernel->handle(new Request('POST', '/identities', $headers, body: $body));
        $replayed = $kernel->handle(new Request('POST', '/identities', $headers, body: $body));
        $read = $kernel->handle(new Request('GET', '/identities/GAM-PER-700001'));
        $suspended = $kernel->handle(new Request('POST', '/identities/GAM-PER-700001/suspend'));

        self::assertSame(201, $created->status);
        self::assertSame($created->body, $replayed->body);
        self::assertSame(200, $read->status);
        self::assertSame('suspended', json_decode($suspended->body, true, flags: JSON_THROW_ON_ERROR)['status']);
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM identities')->fetchColumn());
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM identity_idempotency')->fetchColumn());
        self::assertSame(2, (int) $this->connection->query('SELECT COUNT(*) FROM outbox_messages')->fetchColumn());
        self::assertSame(4, (int) $this->connection->query('SELECT COUNT(*) FROM administrative_audit')->fetchColumn());
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
            new RegisterIdentityHandler($repository, $persister),
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
