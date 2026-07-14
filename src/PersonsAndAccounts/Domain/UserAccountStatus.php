<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

enum UserAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
}
