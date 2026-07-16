<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

enum RoleStatus: string
{
    case Active = 'active';
    case Deprecated = 'deprecated';
}
