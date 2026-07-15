<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application;

/**
 * The single read-only port this context uses to consult the Identity
 * Registry (GENESIS-012 §C — "lit", never the reverse). Application-layer,
 * not Domain: Organization is built without knowing anything about how
 * identities are looked up (Task 3).
 *
 * A found identity is, by construction, always of this Core's own realm —
 * this instance's Identity Registry never stores another realm's
 * identities, so "not found" already covers "belongs to a different realm"
 * (same convention as PersonsAndAccounts\Application\IdentityLookup).
 */
interface IdentityLookup
{
    public function find(string $identityId): ?IdentityLookupResult;
}
