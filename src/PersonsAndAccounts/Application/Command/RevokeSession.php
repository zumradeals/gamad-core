<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

final readonly class RevokeSession
{
    public function __construct(
        public string $sessionId,
        public string $reason = 'manual_revoke',
        public ?string $actorId = null,
    ) {
    }
}
