<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

interface RateLimiter
{
    public function allow(string $key, int $limit, int $windowSeconds): bool;
}
