<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Http;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentity;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentityHandler;
use Gamad\Core\IdentityRegistry\Application\Exception\IdentityAlreadyExists;
use Gamad\Core\IdentityRegistry\Application\IdentityLifecycleService;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityRepository;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\Response;
use InvalidArgumentException;

final readonly class IdentityHttpController
{
    public function __construct(
        private RegisterIdentityHandler $register,
        private IdentityRepository $identities,
        private IdentityLifecycleService $lifecycle,
        private IdempotencyRepository $idempotency,
    ) {}

    public function register(Request $request): Response
    {
        $dto = RegisterIdentityRequest::from($request->body, $request->header('Idempotency-Key'));
        $actorId = $request->actor?->actorId ?? '';
        $requestHash = hash('sha256', $request->body);
        $stored = $this->idempotency->find($actorId, $dto->idempotencyKey);

        if ($stored !== null) {
            if (!hash_equals($stored['request_hash'], $requestHash)) {
                return Response::json(409, ['error' => 'idempotency_key_reused_with_different_request']);
            }

            return new Response($stored['status'], $stored['response']);
        }

        try {
            $identity = ($this->register)(new RegisterIdentity(
                identityId: $dto->identityId,
                identityType: $dto->identityType,
                registeredAt: new DateTimeImmutable(),
            ));
        } catch (IdentityAlreadyExists) {
            return Response::json(409, ['error' => 'identity_already_exists']);
        }

        $response = Response::json(201, $this->serialize($identity));
        $this->idempotency->store($actorId, $dto->idempotencyKey, $requestHash, 201, $response->body);

        return $response;
    }

    public function get(Request $request): Response
    {
        try {
            $identity = $this->identities->findById(new IdentityId($request->pathParameters['identityId']));
        } catch (InvalidArgumentException) {
            return Response::json(400, ['error' => 'invalid_identity_id']);
        }

        return $identity === null
            ? Response::json(404, ['error' => 'identity_not_found'])
            : Response::json(200, $this->serialize($identity));
    }

    public function transition(Request $request): Response
    {
        $target = match ($request->pathParameters['transition']) {
            'activate' => IdentityStatus::Active,
            'suspend' => IdentityStatus::Suspended,
            'archive' => IdentityStatus::Archived,
            'revoke' => IdentityStatus::Revoked,
            default => null,
        };

        if ($target === null) {
            return Response::json(400, ['error' => 'invalid_transition']);
        }

        try {
            $identity = $this->lifecycle->transition(new IdentityId($request->pathParameters['identityId']), $target);
        } catch (DomainException $exception) {
            return Response::json(409, ['error' => 'invalid_lifecycle_transition', 'detail' => $exception->getMessage()]);
        }

        return $identity === null
            ? Response::json(404, ['error' => 'identity_not_found'])
            : Response::json(200, $this->serialize($identity));
    }

    /** @return array<string, string> */
    private function serialize(Identity $identity): array
    {
        return [
            'identity_id' => (string) $identity->id(),
            'identity_type' => $identity->type()->value,
            'status' => $identity->status()->value,
            'registered_at' => $identity->registeredAt()->format(DATE_ATOM),
        ];
    }
}
