<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Http;

use DateTimeImmutable;
use Gamad\Core\Shared\Audit\AdministrativeAuditRepository;
use Gamad\Core\Shared\Http\AuthenticationAdapter;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\ProblemDetails;
use Gamad\Core\Shared\Http\RateLimiter;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\Response;
use InvalidArgumentException;
use Throwable;

/**
 * A distinct HTTP entry point from AdministrativeHttpKernel and from
 * PersonsAndAccountsHttpKernel (Task 6): this context never imports either
 * (ADR-0013 boundary). It authenticates with the same kind of session
 * token as Persons and User Accounts — the concrete AuthenticationAdapter
 * (SessionTokenAuthenticator) is wired in at the composition root
 * (public/index.php), never referenced by class name here. Every route in
 * this context requires a session (no public route exists), so
 * `requiredScopes` is always non-empty in OrganizationsAndMembershipsRoutes.
 */
final readonly class OrganizationsAndMembershipsHttpKernel
{
    public function __construct(
        private OpenApiRequestValidator $validator,
        private OrganizationsAndMembershipsResponseValidator $responseValidator,
        private AuthenticationAdapter $sessionAuthentication,
        private RateLimiter $rateLimiter,
        private AdministrativeAuditRepository $audit,
        private int $rateLimit = 120,
        private int $rateWindowSeconds = 60,
    ) {
    }

    public function handle(Request $request): Response
    {
        $requestId = $request->header('X-Request-ID') ?: $this->uuid();
        $correlationId = $request->header('X-Correlation-ID') ?: $requestId;
        $request = $request->withIdentifiers($requestId, $correlationId);

        try {
            $validated = $this->validator->validate($request);
        } catch (InvalidArgumentException $exception) {
            return $this->secure(ProblemDetails::response(400, 'urn:gamad:error:invalid-request', 'Invalid request', $exception->getMessage(), $requestId), $requestId, $correlationId);
        }

        $route = $validated['route'];
        $validatedRequest = $validated['request'];

        $actor = $this->sessionAuthentication->authenticate($request);
        if ($actor === null) {
            return $this->secure(ProblemDetails::response(401, 'urn:gamad:error:unauthenticated', 'Unauthenticated', 'A valid session is required.', $requestId), $requestId, $correlationId);
        }
        $validatedRequest = $validatedRequest->withActor($actor);

        $rateLimitKey = $actor->actorId . ':' . $request->path;
        if (!$this->rateLimiter->allow($rateLimitKey, $this->rateLimit, $this->rateWindowSeconds)) {
            $response = ProblemDetails::response(429, 'urn:gamad:error:rate-limit', 'Too many requests', 'The request rate limit has been exceeded.', $requestId);
            $this->record($actor->actorId, 'rate_limit_exceeded', $request->path, 429, $request);

            return $this->secure($response, $requestId, $correlationId);
        }

        try {
            $response = ($route->handler)($validatedRequest);
            $this->responseValidator->validate($route->operationId, $response);
            $this->record($actor->actorId, $route->operationId, $request->path, $response->status, $request);

            return $this->secure($response, $requestId, $correlationId);
        } catch (InvalidArgumentException $exception) {
            return $this->secure(ProblemDetails::response(400, 'urn:gamad:error:invalid-request', 'Invalid request', $exception->getMessage(), $requestId), $requestId, $correlationId);
        } catch (Throwable $exception) {
            $this->record($actor->actorId, 'unhandled_organizations_and_memberships_request', $request->path, 500, $request, ['exception' => $exception::class]);

            return $this->secure(ProblemDetails::response(500, 'urn:gamad:error:internal', 'Internal server error', 'The request could not be completed.', $requestId), $requestId, $correlationId);
        }
    }

    private function secure(Response $response, string $requestId, string $correlationId): Response
    {
        return new Response($response->status, $response->body, $response->headers + [
            'X-Request-ID' => $requestId,
            'X-Correlation-ID' => $correlationId,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
        ]);
    }

    /** @param array<string, mixed> $context */
    private function record(string $actorId, string $action, string $target, int $status, Request $request, array $context = []): void
    {
        $this->audit->record($actorId, $action, $target, $status, new DateTimeImmutable(), $context + [
            'request_id' => $request->requestId,
            'correlation_id' => $request->correlationId,
        ]);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
