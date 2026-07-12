<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Http;

use Gamad\Core\Shared\Http\AuthenticatedActor;
use Gamad\Core\Shared\Http\TokenVerifier;

final readonly class EnvironmentTokenVerifier implements TokenVerifier
{
    /** @param array<string, array{actor_id:string, scopes:list<string>}> $tokens */
    public function __construct(private array $tokens)
    {
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self(is_array($decoded) ? $decoded : []);
    }

    public function verify(string $accessToken): ?AuthenticatedActor
    {
        $identity = $this->tokens[$accessToken] ?? null;
        if (!is_array($identity) || !isset($identity['actor_id'], $identity['scopes']) || !is_array($identity['scopes'])) {
            return null;
        }

        return new AuthenticatedActor(
            actorId: (string) $identity['actor_id'],
            scopes: array_values(array_map('strval', $identity['scopes'])),
        );
    }
}
