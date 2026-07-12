<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Security;

interface AuthorizationService
{
    public function isAllowed(string $actorId, string $permission): bool;
}
