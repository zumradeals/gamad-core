<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Domain;

interface RecordsDomainEvents
{
    /** @return list<DomainEvent> */
    public function recordedEvents(): array;

    public function clearRecordedEvents(): void;
}
