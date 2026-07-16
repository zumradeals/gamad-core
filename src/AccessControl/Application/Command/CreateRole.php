<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

final readonly class CreateRole
{
    public function __construct(
        public string $name,
        public string $scope,
    ) {
    }
}
