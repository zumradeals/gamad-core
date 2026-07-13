<?php

declare(strict_types=1);

namespace Gamad\Console\Lib;

/**
 * Normalized result of a Core API call. `status` is 0 when the Core could
 * not be reached at all (network/timeout), distinguishing that case from a
 * real HTTP status returned by the Core.
 */
final readonly class CoreApiResponse
{
    /** @param array<string, mixed>|list<mixed>|null $data */
    public function __construct(
        public int $status,
        public array|null $data,
        public ?string $rawBody = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function unauthorized(): bool
    {
        return $this->status === 401;
    }

    public function forbidden(): bool
    {
        return $this->status === 403;
    }

    public function unreachable(): bool
    {
        return $this->status === 0;
    }
}
