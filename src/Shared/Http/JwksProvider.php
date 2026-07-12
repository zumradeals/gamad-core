<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

interface JwksProvider
{
    /** @return array<string, array<string, mixed>> keyed by kid */
    public function keys(): array;
}
