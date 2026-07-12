<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Observability;

interface StructuredLogger
{
    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void;
}
