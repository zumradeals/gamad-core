<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application\Command;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;

final readonly class RegisterIdentity
{
    public function __construct(
        public IdentityId $identityId,
        public IdentityType $identityType,
        public DateTimeImmutable $registeredAt,
    ) {
    }
}
