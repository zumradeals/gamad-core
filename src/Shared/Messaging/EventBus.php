<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Messaging;

use Gamad\Core\Shared\Outbox\PendingOutboxMessage;

interface EventBus
{
    public function publish(PendingOutboxMessage $message): void;
}
