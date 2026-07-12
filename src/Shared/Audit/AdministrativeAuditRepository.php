<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Audit;

use DateTimeImmutable;

interface AdministrativeAuditRepository
{
    /** @param array<string, mixed> $context */
    public function record(
        string $actorId,
        string $action,
        string $target,
        int $statusCode,
        DateTimeImmutable $occurredAt,
        array $context = [],
    ): void;
}
