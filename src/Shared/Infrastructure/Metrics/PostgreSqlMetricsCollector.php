<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Metrics;

use Gamad\Core\Shared\Metrics\MetricsCollector;
use PDO;

final readonly class PostgreSqlMetricsCollector implements MetricsCollector
{
    public function __construct(private PDO $connection)
    {
    }

    public function increment(string $name, int $value = 1, array $labels = []): void
    {
        $this->upsert($name, 'counter', (float) $value, $labels, true);
    }

    public function gauge(string $name, float|int $value, array $labels = []): void
    {
        $this->upsert($name, 'gauge', (float) $value, $labels, false);
    }

    /** @param array<string, string> $labels */
    private function upsert(string $name, string $type, float $value, array $labels, bool $increment): void
    {
        ksort($labels);
        $statement = $this->connection->prepare(
            $increment
                ? <<<'SQL'
                  INSERT INTO operational_metrics (name, metric_type, labels, value, updated_at)
                  VALUES (:name, :type, CAST(:labels AS JSONB), :value, NOW())
                  ON CONFLICT (name, metric_type, labels) DO UPDATE SET
                      value = operational_metrics.value + EXCLUDED.value,
                      updated_at = NOW()
                  SQL
                : <<<'SQL'
                  INSERT INTO operational_metrics (name, metric_type, labels, value, updated_at)
                  VALUES (:name, :type, CAST(:labels AS JSONB), :value, NOW())
                  ON CONFLICT (name, metric_type, labels) DO UPDATE SET
                      value = EXCLUDED.value,
                      updated_at = NOW()
                  SQL
        );

        $statement->execute([
            'name' => $name,
            'type' => $type,
            'labels' => json_encode($labels, JSON_THROW_ON_ERROR),
            'value' => $value,
        ]);
    }
}
