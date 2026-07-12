<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Metrics;

interface MetricsCollector
{
    /** @param array<string, string> $labels */
    public function increment(string $name, int $value = 1, array $labels = []): void;

    /** @param array<string, string> $labels */
    public function gauge(string $name, float|int $value, array $labels = []): void;
}
