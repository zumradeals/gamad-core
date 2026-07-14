<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application;

/**
 * The single read-only port this context uses to consult the Identity
 * Registry (GENESIS-010 §C — "lit", never the reverse). Deliberately
 * Application-layer, not Domain: Person is built without knowing anything
 * about how identities are looked up (Task 3 requirement).
 *
 * A found identity is, by construction, always of this Core's own realm —
 * this instance's Identity Registry never stores another realm's
 * identities. Full explicit realm-tagging on the identifier itself awaits
 * the dedicated ADR-0017 migration; until then this lookup already
 * satisfies "belongs to realm GAT" for every identity it can return.
 */
interface IdentityLookup
{
    public function find(string $identityId): ?IdentityLookupResult;
}
