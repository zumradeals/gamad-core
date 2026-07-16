<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

final readonly class AddPermissionToRole
{
    public function __construct(
        public string $roleId,
        public string $permissionId,
    ) {
    }
}
