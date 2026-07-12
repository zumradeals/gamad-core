<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Support;

use Gamad\Core\Shared\Messaging\EventBus;
use Gamad\Core\Shared\Outbox\PendingOutboxMessage;
use RuntimeException;

final class RecordingEventBus implements EventBus
{
    /** @var list<PendingOutboxMessage> */
    public array $published = [];

    public int $failuresRemaining = 0;

    public function publish(PendingOutboxMessage $message): void
    {
        if ($this->failuresRemaining > 0) {
            --$this->failuresRemaining;
            throw new RuntimeException('Simulated event bus failure.');
        }

        $this->published[] = $message;
    }
}
