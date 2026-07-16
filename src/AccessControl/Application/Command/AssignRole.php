<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

final readonly class AssignRole
{
    public function __construct(
        public string $roleId,
        public string $personId,
        public string $organizationId,
        public string $actorId,
        public bool $isBootstrap = false,
    ) {
    }
}
