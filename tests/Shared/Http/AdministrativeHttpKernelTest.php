<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Http;

use DateTimeImmutable;
use Gamad\Core\Shared\Audit\AdministrativeAuditRepository;
use Gamad\Core\Shared\Http\AdministrativeHttpKernel;
use Gamad\Core\Shared\Http\AuthenticatedActor;
use Gamad\Core\Shared\Http\AuthenticationAdapter;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\OpenApiResponseValidator;
use Gamad\Core\Shared\Http\RateLimiter;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\Response;
use Gamad\Core\Shared\Http\RouteDefinition;
use Gamad\Core\Shared\Http\ScopeAuthorizationMiddleware;
use PHPUnit\Framework\TestCase;

final class AdministrativeHttpKernelTest extends TestCase
{
    public function test_it_rejects_unauthenticated_requests_with_problem_details(): void
    {
        $response = $this->kernel(null, [])->handle(new Request('GET', '/admin/runtime/health'));

        self::assertSame(401, $response->status);
        self::assertArrayHasKey('X-Request-ID', $response->headers);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame(401, json_decode($response->body, true, flags: JSON_THROW_ON_ERROR)['status']);
    }

    public function test_it_rejects_missing_scope_and_records_audit(): void
    {
        $audit = new InMemoryAdministrativeAuditRepository();
        $response = $this->kernel(new AuthenticatedActor('GAM-PER-000001', []), [], $audit)
            ->handle(new Request('GET', '/admin/runtime/health'));

        self::assertSame(403, $response->status);
        self::assertCount(1, $audit->records);
        self::assertSame('getRuntimeHealthSummary', $audit->records[0]['action']);
    }

    public function test_it_serves_authorized_route_validates_response_and_propagates_ids(): void
    {
        $scope = 'core.runtime.health.read';
        $response = $this->kernel(new AuthenticatedActor('GAM-PER-000001', [$scope]), [$scope])
            ->handle(new Request('GET', '/admin/runtime/health', [
                'X-Request-ID' => 'request-123',
                'X-Correlation-ID' => 'correlation-456',
            ]));

        self::assertSame(200, $response->status);
        self::assertSame('request-123', $response->headers['X-Request-ID']);
        self::assertSame('correlation-456', $response->headers['X-Correlation-ID']);
    }

    public function test_it_applies_rate_limiting(): void
    {
        $scope = 'core.runtime.health.read';
        $kernel = $this->kernel(new AuthenticatedActor('GAM-PER-000001', [$scope]), [$scope], rateLimiter: new DenyRateLimiter());

        self::assertSame(429, $kernel->handle(new Request('GET', '/admin/runtime/health'))->status);
    }

    /** @param list<string> $routeScopes */
    private function kernel(
        ?AuthenticatedActor $actor,
        array $routeScopes,
        ?InMemoryAdministrativeAuditRepository $audit = null,
        ?RateLimiter $rateLimiter = null,
    ): AdministrativeHttpKernel {
        $route = new RouteDefinition(
            'GET',
            '/admin/runtime/health',
            $routeScopes,
            static fn (Request $request): Response => Response::json(200, [
                'healthy' => true,
                'live_workers' => 1,
                'ready_workers' => 1,
                'stale_workers' => 0,
                'workers' => [],
            ]),
            'getRuntimeHealthSummary',
        );

        return new AdministrativeHttpKernel(
            new OpenApiRequestValidator([$route]),
            new OpenApiResponseValidator(),
            new StaticAuthenticationAdapter($actor),
            new ScopeAuthorizationMiddleware(),
            $rateLimiter ?? new AllowRateLimiter(),
            $audit ?? new InMemoryAdministrativeAuditRepository(),
        );
    }
}

final readonly class StaticAuthenticationAdapter implements AuthenticationAdapter
{
    public function __construct(private ?AuthenticatedActor $actor) {}
    public function authenticate(Request $request): ?AuthenticatedActor { return $this->actor; }
}

final readonly class AllowRateLimiter implements RateLimiter
{
    public function allow(string $key, int $limit, int $windowSeconds): bool { return true; }
}

final readonly class DenyRateLimiter implements RateLimiter
{
    public function allow(string $key, int $limit, int $windowSeconds): bool { return false; }
}

final class InMemoryAdministrativeAuditRepository implements AdministrativeAuditRepository
{
    /** @var list<array{actor:string,action:string,target:string,status:int}> */
    public array $records = [];

    public function record(string $actorId, string $action, string $target, int $statusCode, DateTimeImmutable $occurredAt, array $context = []): void
    {
        $this->records[] = ['actor' => $actorId, 'action' => $action, 'target' => $target, 'status' => $statusCode];
    }
}
