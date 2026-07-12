<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

use DateTimeImmutable;
use Gamad\Core\Shared\Audit\AdministrativeAuditRepository;
use InvalidArgumentException;
use Throwable;

final readonly class AdministrativeHttpKernel
{
    public function __construct(
        private OpenApiRequestValidator $validator,
        private OpenApiResponseValidator $responseValidator,
        private AuthenticationAdapter $authentication,
        private ScopeAuthorizationMiddleware $authorization,
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
        $actor = $this->authentication->authenticate($request);

        if ($actor === null) {
            return $this->secure(ProblemDetails::response(401, 'urn:gamad:error:unauthenticated', 'Unauthenticated', 'A valid bearer token is required.', $requestId), $requestId, $correlationId);
        }

        if (!$this->rateLimiter->allow($actor->actorId . ':' . $request->path, $this->rateLimit, $this->rateWindowSeconds)) {
            $response = ProblemDetails::response(429, 'urn:gamad:error:rate-limit', 'Too many requests', 'The administrative request rate limit has been exceeded.', $requestId);
            $this->record($actor->actorId, 'rate_limit_exceeded', $request->path, 429, $request);
            return $this->secure($response, $requestId, $correlationId);
        }

        try {
            $validated = $this->validator->validate($request->withActor($actor));
            $route = $validated['route'];
            $validatedRequest = $validated['request'];

            if (!$this->authorization->authorize($actor, $route->requiredScopes)) {
                $response = ProblemDetails::response(403, 'urn:gamad:error:insufficient-scope', 'Forbidden', 'The authenticated actor lacks the required scope.', $requestId);
                $this->record($actor->actorId, $route->operationId, $request->path, 403, $request);
                return $this->secure($response, $requestId, $correlationId);
            }

            $response = ($route->handler)($validatedRequest);
            $this->responseValidator->validate($route->operationId, $response);
            $this->record($actor->actorId, $route->operationId, $request->path, $response->status, $request);

            return $this->secure($response, $requestId, $correlationId);
        } catch (InvalidArgumentException $exception) {
            return $this->secure(ProblemDetails::response(400, 'urn:gamad:error:invalid-request', 'Invalid request', $exception->getMessage(), $requestId), $requestId, $correlationId);
        } catch (Throwable $exception) {
            $this->record($actor->actorId, 'unhandled_administrative_request', $request->path, 500, $request, ['exception' => $exception::class]);
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
