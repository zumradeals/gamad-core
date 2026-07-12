<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Domain;

enum IdentityStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
    case Revoked = 'revoked';
}
