<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

enum RoleAssignmentStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
