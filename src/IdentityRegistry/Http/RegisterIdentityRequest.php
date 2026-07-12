<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Http;

use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use InvalidArgumentException;

final readonly class RegisterIdentityRequest
{
    public function __construct(
        public IdentityId $identityId,
        public IdentityType $identityType,
        public string $idempotencyKey,
    ) {}

    public static function from(string $body, ?string $idempotencyKey): self
    {
        if ($idempotencyKey === null || strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Idempotency-Key must contain between 8 and 128 characters.');
        }

        $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($payload) || array_diff(array_keys($payload), ['identity_id', 'identity_type']) !== []) {
            throw new InvalidArgumentException('Request body contains unsupported fields.');
        }
        if (!isset($payload['identity_id'], $payload['identity_type']) || !is_string($payload['identity_id']) || !is_string($payload['identity_type'])) {
            throw new InvalidArgumentException('identity_id and identity_type are required strings.');
        }

        return new self(
            new IdentityId($payload['identity_id']),
            IdentityType::from($payload['identity_type']),
            $idempotencyKey,
        );
    }
}
