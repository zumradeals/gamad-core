<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

enum PersonStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Deceased = 'deceased';
}
