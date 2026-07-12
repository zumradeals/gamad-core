<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Metrics;

use Gamad\Core\Shared\Metrics\MetricsCollector;

final class InMemoryMetricsCollector implements MetricsCollector
{
    /** @var array<string, int> */
    public array $counters = [];

    /** @var array<string, float|int> */
    public array $gauges = [];

    public function increment(string $name, int $value = 1, array $labels = []): void
    {
        $key = $this->key($name, $labels);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;
    }

    public function gauge(string $name, float|int $value, array $labels = []): void
    {
        $this->gauges[$this->key($name, $labels)] = $value;
    }

    /** @param array<string, string> $labels */
    private function key(string $name, array $labels): string
    {
        ksort($labels);

        return $name . ':' . http_build_query($labels, '', ',');
    }
}
