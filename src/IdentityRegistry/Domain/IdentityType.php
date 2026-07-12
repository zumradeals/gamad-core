<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Domain;

enum IdentityType: string
{
    case Person = 'person';
    case Organization = 'organization';
    case Application = 'application';
    case Service = 'service';
    case Agent = 'agent';
    case Device = 'device';
    case Resource = 'resource';
}
