<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Domain;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;

    public function eventName(): string;

    public function aggregateId(): string;

    /** @return array<string, bool|float|int|string|null> */
    public function payload(): array;
}
