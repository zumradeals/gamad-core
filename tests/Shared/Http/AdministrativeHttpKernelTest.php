<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Http;

use DateTimeImmutable;
use Gamad\Core\Shared\Audit\AdministrativeAuditRepository;
use Gamad\Core\Shared\Http\AdministrativeHttpKernel;
use Gamad\Core\Shared\Http\AuthenticatedActor;
use Gamad\Core\Shared\Http\AuthenticationAdapter;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\Response;
use Gamad\Core\Shared\Http\RouteDefinition;
use Gamad\Core\Shared\Http\ScopeAuthorizationMiddleware;
use PHPUnit\Framework\TestCase;

final class AdministrativeHttpKernelTest extends TestCase
{
    public function test_it_rejects_unauthenticated_requests(): void
    {
        $kernel = $this->kernel(actor: null, scopes: []);

        $response = $kernel->handle(new Request('GET', '/admin/runtime/health'));

        self::assertSame(401, $response->status);
    }

    public function test_it_rejects_missing_scope_and_records_audit(): void
    {
        $audit = new InMemoryAdministrativeAuditRepository();
        $kernel = $this->kernel(new AuthenticatedActor('GAM-PER-000001', []), [], $audit);

        $response = $kernel->handle(new Request('GET', '/admin/runtime/health'));

        self::assertSame(403, $response->status);
        self::assertCount(1, $audit->records);
        self::assertSame('getRuntimeHealthSummary', $audit->records[0]['action']);
    }

    public function test_it_serves_authorized_route_and_records_success(): void
    {
        $audit = new InMemoryAdministrativeAuditRepository();
        $scope = 'core.runtime.health.read';
        $kernel = $this->kernel(new AuthenticatedActor('GAM-PER-000001', [$scope]), [$scope], $audit);

        $response = $kernel->handle(new Request('GET', '/admin/runtime/health'));

        self::assertSame(200, $response->status);
        self::assertSame('{"healthy":true}', $response->body);
        self::assertSame(200, $audit->records[0]['status']);
    }

    public function test_it_rejects_invalid_openapi_path_parameter(): void
    {
        $scope = 'core.outbox.dead_letter.read';
        $route = new RouteDefinition(
            'GET',
            '/admin/runtime/dead-letters/{messageId}',
            [$scope],
            static fn (Request $request): Response => Response::json(200, []),
            'inspectDeadLetter',
        );
        $kernel = new AdministrativeHttpKernel(
            new OpenApiRequestValidator([$route]),
            new StaticAuthenticationAdapter(new AuthenticatedActor('GAM-PER-000001', [$scope])),
            new ScopeAuthorizationMiddleware(),
            new InMemoryAdministrativeAuditRepository(),
        );

        $response = $kernel->handle(new Request('GET', '/admin/runtime/dead-letters/not-a-uuid'));

        self::assertSame(400, $response->status);
    }

    /** @param list<string> $routeScopes */
    private function kernel(
        ?AuthenticatedActor $actor,
        array $routeScopes,
        ?InMemoryAdministrativeAuditRepository $audit = null,
    ): AdministrativeHttpKernel {
        $route = new RouteDefinition(
            'GET',
            '/admin/runtime/health',
            $routeScopes,
            static fn (Request $request): Response => Response::json(200, ['healthy' => true]),
            'getRuntimeHealthSummary',
        );

        return new AdministrativeHttpKernel(
            new OpenApiRequestValidator([$route]),
            new StaticAuthenticationAdapter($actor),
            new ScopeAuthorizationMiddleware(),
            $audit ?? new InMemoryAdministrativeAuditRepository(),
        );
    }
}

final readonly class StaticAuthenticationAdapter implements AuthenticationAdapter
{
    public function __construct(private ?AuthenticatedActor $actor)
    {
    }

    public function authenticate(Request $request): ?AuthenticatedActor
    {
        return $this->actor;
    }
}

final class InMemoryAdministrativeAuditRepository implements AdministrativeAuditRepository
{
    /** @var list<array{actor:string,action:string,target:string,status:int}> */
    public array $records = [];

    public function record(
        string $actorId,
        string $action,
        string $target,
        int $statusCode,
        DateTimeImmutable $occurredAt,
        array $context = [],
    ): void {
        $this->records[] = [
            'actor' => $actorId,
            'action' => $action,
            'target' => $target,
            'status' => $statusCode,
        ];
    }
}
