<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

use DateTimeImmutable;

final readonly class RegisterPerson
{
    public function __construct(
        public string $identityId,
        public string $declaredName,
        public ?DateTimeImmutable $registeredAt = null,
    ) {
    }
}
