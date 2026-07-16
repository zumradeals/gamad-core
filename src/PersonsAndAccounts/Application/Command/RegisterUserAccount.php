<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

use DateTimeImmutable;

final readonly class RegisterUserAccount
{
    public function __construct(
        public string $personId,
        public ?DateTimeImmutable $createdAt = null,
        public ?string $actorId = null,
    ) {
    }
}
