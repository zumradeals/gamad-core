<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

interface OutboxRepository
{
    public function append(OutboxMessage $message): void;
}
