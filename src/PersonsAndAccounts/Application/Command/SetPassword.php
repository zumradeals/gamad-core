<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

final readonly class SetPassword
{
    public function __construct(
        public string $accountId,
        public string $plainPassword,
    ) {
    }
}
