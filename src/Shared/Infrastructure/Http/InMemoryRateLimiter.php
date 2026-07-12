<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Http;

use Gamad\Core\Shared\Http\RateLimiter;

final class InMemoryRateLimiter implements RateLimiter
{
    /** @var array<string, array{window:int,count:int}> */
    private array $buckets = [];

    public function allow(string $key, int $limit, int $windowSeconds): bool
    {
        $window = intdiv(time(), $windowSeconds);
        $bucket = $this->buckets[$key] ?? ['window' => $window, 'count' => 0];

        if ($bucket['window'] !== $window) {
            $bucket = ['window' => $window, 'count' => 0];
        }

        ++$bucket['count'];
        $this->buckets[$key] = $bucket;

        return $bucket['count'] <= $limit;
    }
}
