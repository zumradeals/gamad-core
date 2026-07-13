<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application;

use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;

interface IdentitySearchRepository
{
    public function search(
        ?IdentityType $type,
        ?IdentityStatus $status,
        int $limit,
        ?string $cursor,
    ): IdentitySearchPage;
}
