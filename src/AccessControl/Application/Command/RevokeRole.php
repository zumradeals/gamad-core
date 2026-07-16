<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

final readonly class RevokeRole
{
    public function __construct(
        public string $assignmentId,
    ) {
    }
}
