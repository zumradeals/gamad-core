<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain;

enum DepartmentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
