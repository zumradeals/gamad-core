<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

interface TokenVerifier
{
    public function verify(string $accessToken): ?AuthenticatedActor;
}
