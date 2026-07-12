<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Support;

use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;

final class InMemoryOutboxRepository implements OutboxRepository
{
    /** @var list<OutboxMessage> */
    public array $messages = [];

    public function append(OutboxMessage $message): void
    {
        $this->messages[] = $message;
    }
}
