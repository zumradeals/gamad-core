<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application\Command;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;

final readonly class RegisterIdentity
{
    public function __construct(
        public IdentityType $identityType,
        public string $actorId,
        public DateTimeImmutable $registeredAt,
    ) {}
}
