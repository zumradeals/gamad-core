<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

interface AuthenticationAdapter
{
    public function authenticate(Request $request): ?AuthenticatedActor;
}
