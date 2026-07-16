<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

final readonly class EvaluateAccess
{
    public function __construct(
        public string $actorId,
        public string $action,
        public string $contextId,
    ) {
    }
}
