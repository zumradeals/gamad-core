<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application;

use Gamad\Core\IdentityRegistry\Domain\IdentityType;

interface IdentityRegistrationPolicy
{
    public function assertAllowed(string $actorId, IdentityType $type): void;
}
