<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Application;

use Gamad\Core\Shared\Domain\DomainEvent;
use Gamad\Core\Shared\Domain\RecordsDomainEvents;

final readonly class DomainEventCollector
{
    /** @return list<DomainEvent> */
    public function collect(RecordsDomainEvents $aggregate): array
    {
        return $aggregate->recordedEvents();
    }

    public function clear(RecordsDomainEvents $aggregate): void
    {
        $aggregate->clearRecordedEvents();
    }
}
