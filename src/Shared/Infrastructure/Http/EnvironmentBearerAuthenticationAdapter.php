<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Http;

use Gamad\Core\Shared\Http\AuthenticatedActor;
use Gamad\Core\Shared\Http\AuthenticationAdapter;
use Gamad\Core\Shared\Http\Request;

final readonly class EnvironmentBearerAuthenticationAdapter implements AuthenticationAdapter
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

    public function authenticate(Request $request): ?AuthenticatedActor
    {
        $authorization = $request->header('Authorization');
        if ($authorization === null || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($authorization, 7));
        $identity = $this->tokens[$token] ?? null;
        if (!is_array($identity) || !isset($identity['actor_id'], $identity['scopes']) || !is_array($identity['scopes'])) {
            return null;
        }

        return new AuthenticatedActor(
            actorId: (string) $identity['actor_id'],
            scopes: array_values(array_map('strval', $identity['scopes'])),
        );
    }
}
