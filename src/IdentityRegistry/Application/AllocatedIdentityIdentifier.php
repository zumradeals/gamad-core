<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application;

use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityInternalId;

final readonly class AllocatedIdentityIdentifier
{
    public function __construct(
        public IdentityInternalId $internalId,
        public IdentityId $publicId,
    ) {}
}
