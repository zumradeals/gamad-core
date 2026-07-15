<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application;

/**
 * The single read-only port this context uses to consult Persons and User
 * Accounts (GENESIS-012 §C — "lit", never the reverse). Only existence is
 * needed here: a Membership stores the person_id it was given, never a
 * Person's declared name or contact (that vocabulary stays in the other
 * context, ADR-0013 boundary).
 */
interface PersonLookup
{
    public function exists(string $personId): bool;
}
