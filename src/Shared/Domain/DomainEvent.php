<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Domain;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;

    public function eventName(): string;
}
