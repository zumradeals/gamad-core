<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

/**
 * GENESIS-013 §2.2 — `Realm` is reserved for the single transversal
 * exception (superadmin in GAMAD SAS); every other role is
 * `Organization`-scoped and evaluated against a specific context.
 */
enum RoleScope: string
{
    case Realm = 'realm';
    case Organization = 'organization';
}
