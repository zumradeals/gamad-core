<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

final readonly class CreatePermission
{
    public function __construct(
        public string $name,
        public string $description,
    ) {
    }
}
