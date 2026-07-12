<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Health;

use DateTimeImmutable;

interface HeartbeatRepository
{
    /** @param array<string, mixed> $metadata */
    public function beat(string $workerId, DateTimeImmutable $at, array $metadata = []): void;
}
