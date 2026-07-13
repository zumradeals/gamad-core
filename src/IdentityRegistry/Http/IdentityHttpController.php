<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Http;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentity;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentityHandler;
use Gamad\Core\IdentityRegistry\Application\IdentityLifecycleService;
use Gamad\Core\IdentityRegistry\Application\IdentitySearchRepository;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityRepository;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use Gamad\Core\Shared\Application\TransactionManager;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\Response;
use InvalidArgumentException;
use ValueError;

final readonly class IdentityHttpController
{
    public function __construct(
        private RegisterIdentityHandler $register,
        private IdentityRepository $identities,
        private IdentitySearchRepository $search,
        private IdentityLifecycleService $lifecycle,
        private IdempotencyRepository $idempotency,
        private TransactionManager $transactions,
    ) {}

    public function register(Request $request): Response
    {
        $dto = RegisterIdentityRequest::from($request->body, $request->header('Idempotency-Key'));
        return $this->idempotent($request, $dto->idempotencyKey, function () use ($dto, $request): Response {
            $identity = ($this->register)(new RegisterIdentity(
                identityType: $dto->identityType,
                actorId: $request->actor?->actorId ?? '',
                registeredAt: new DateTimeImmutable(),
            ));

            return Response::json(201, $this->serialize($identity));
        });
    }

    public function bulkRegister(Request $request): Response
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || strlen($key) < 8 || strlen($key) > 128) {
            throw new InvalidArgumentException('Idempotency-Key must contain between 8 and 128 characters.');
        }

        $payload = json_decode($request->body, true, flags: JSON_THROW_ON_ERROR);
        $items = is_array($payload) ? ($payload['items'] ?? null) : null;
        if (!is_array($items) || $items === [] || count($items) > 100) {
            throw new InvalidArgumentException('items must contain between 1 and 100 registrations.');
        }

        return $this->idempotent($request, $key, function () use ($items, $request): Response {
            $created = [];
            foreach ($items as $item) {
                if (!is_array($item) || array_keys($item) !== ['identity_type'] || !is_string($item['identity_type'])) {
                    throw new InvalidArgumentException('Each bulk item must contain only identity_type.');
                }
                try {
                    $type = IdentityType::from($item['identity_type']);
                } catch (ValueError) {
                    throw new InvalidArgumentException('A bulk identity_type is not supported.');
                }
                $created[] = $this->serialize(($this->register)(new RegisterIdentity(
                    identityType: $type,
                    actorId: $request->actor?->actorId ?? '',
                    registeredAt: new DateTimeImmutable(),
                )));
            }

            return Response::json(201, ['items' => $created, 'count' => count($created)]);
        });
    }

    public function search(Request $request): Response
    {
        try {
            $type = isset($request->query['type']) ? IdentityType::from($request->query['type']) : null;
            $status = isset($request->query['status']) ? IdentityStatus::from($request->query['status']) : null;
        } catch (ValueError) {
            throw new InvalidArgumentException('Invalid identity search filter.');
        }
        $limit = isset($request->query['limit']) ? (int) $request->query['limit'] : 50;
        if ($limit < 1 || $limit > 200) {
            throw new InvalidArgumentException('limit must be between 1 and 200.');
        }
        $page = $this->search->search($type, $status, $limit, $request->query['cursor'] ?? null);

        return Response::json(200, [
            'items' => array_map(fn (Identity $identity): array => $this->serialize($identity), $page->items),
            'next_cursor' => $page->nextCursor,
        ]);
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

    private function idempotent(Request $request, string $key, callable $operation): Response
    {
        $actorId = $request->actor?->actorId ?? '';
        $requestHash = hash('sha256', $request->body);

        return $this->transactions->transactional(function () use ($actorId, $key, $requestHash, $operation): Response {
            $stored = $this->idempotency->find($actorId, $key);
            if ($stored !== null) {
                return hash_equals($stored['request_hash'], $requestHash)
                    ? new Response($stored['status'], $stored['response'])
                    : Response::json(409, ['error' => 'idempotency_key_reused_with_different_request']);
            }

            $response = $operation();
            if ($response->status < 400) {
                $this->idempotency->store($actorId, $key, $requestHash, $response->status, $response->body);
            }
            return $response;
        });
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
