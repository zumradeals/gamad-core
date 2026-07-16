<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application;

/**
 * The single read-only port this context uses to consult Persons and User
 * Accounts and Organizations and Memberships (GENESIS-014 §C — "lit",
 * never the reverse, and never by importing either namespace directly,
 * ADR-0013). Only existence is needed here, exactly as
 * OrganizationsAndMemberships\Application\PersonLookup only needs existence
 * from the Identity Registry.
 */
interface AccessControlLookup
{
    public function personExists(string $personId): bool;

    public function organizationExists(string $organizationId): bool;

    /**
     * A session's AuthenticatedActor::$actorId is a UserAccountId (UUID),
     * not a person's GAM- identity (PersonsAndAccounts\Infrastructure\Http\
     * SessionTokenAuthenticator) — this context's role assignments are
     * keyed by person id, so the HTTP layer resolves one to the other
     * before evaluating or recording anything.
     */
    public function resolveAccountToPerson(string $accountId): ?string;
}
