<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

final readonly class Authenticate
{
    public function __construct(
        public string $personId,
        public string $plainPassword,
    ) {
    }
}
