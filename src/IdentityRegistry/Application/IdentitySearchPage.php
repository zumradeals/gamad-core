<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application;

use Gamad\Core\IdentityRegistry\Domain\Identity;

final readonly class IdentitySearchPage
{
    /** @param list<Identity> $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {}
}
