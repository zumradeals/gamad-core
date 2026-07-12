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
        private AuthenticationAdapter $authentication,
        private ScopeAuthorizationMiddleware $authorization,
        private AdministrativeAuditRepository $audit,
    ) {
    }

    public function handle(Request $request): Response
    {
        $actor = $this->authentication->authenticate($request);
        if ($actor === null) {
            return Response::json(401, ['error' => 'unauthenticated']);
        }

        try {
            $validated = $this->validator->validate($request->withActor($actor));
            $route = $validated['route'];
            $validatedRequest = $validated['request'];

            if (!$this->authorization->authorize($actor, $route->requiredScopes)) {
                $response = Response::json(403, ['error' => 'insufficient_scope']);
                $this->record($actor->actorId, $route->operationId, $request->path, $response->status);

                return $response;
            }

            $response = ($route->handler)($validatedRequest);
            $this->record($actor->actorId, $route->operationId, $request->path, $response->status);

            return $response;
        } catch (InvalidArgumentException $exception) {
            return Response::json(400, ['error' => 'invalid_request', 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->record($actor->actorId, 'unhandled_administrative_request', $request->path, 500, [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return Response::json(500, ['error' => 'internal_error']);
        }
    }

    /** @param array<string, mixed> $context */
    private function record(
        string $actorId,
        string $action,
        string $target,
        int $status,
        array $context = [],
    ): void {
        $this->audit->record(
            actorId: $actorId,
            action: $action,
            target: $target,
            statusCode: $status,
            occurredAt: new DateTimeImmutable(),
            context: $context,
        );
    }
}
