<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

interface DeadLetterRepository
{
    public function replay(string $messageId): bool;
}
