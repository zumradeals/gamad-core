<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Http;

use Gamad\Core\Shared\Http\AuthenticatedActor;
use Gamad\Core\Shared\Http\AuthenticationAdapter;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\TokenVerifier;

final readonly class BearerTokenAuthenticationAdapter implements AuthenticationAdapter
{
    public function __construct(private TokenVerifier $verifier)
    {
    }

    public function authenticate(Request $request): ?AuthenticatedActor
    {
        $authorization = $request->header('Authorization');
        if ($authorization === null || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($authorization, 7));

        return $token === '' ? null : $this->verifier->verify($token);
    }
}
