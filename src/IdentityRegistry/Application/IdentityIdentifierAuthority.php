<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application;

use Gamad\Core\IdentityRegistry\Domain\IdentityType;

interface IdentityIdentifierAuthority
{
    public function allocate(IdentityType $type): AllocatedIdentityIdentifier;
}
