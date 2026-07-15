<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationRepository;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationStatus;

/**
 * DIRECTIVE-006 Task 8 — a suspended or revoked Identity must suspend
 * whatever in this context is attached to it: the Organization itself, if
 * the identity is one (its cascade to memberships then follows separately,
 * via ReactToOrganizationSuspended reacting to the OrganizationSuspended
 * event this emits — never suspended directly here), or every active
 * Membership of that person, if it is a person identity. Reacts to the
 * already-published `identity.status_changed.v1` event — never by reading
 * Identity Registry tables directly (GENESIS-011 §4, ADR-0013 boundary).
 *
 * A no-op for any identity that is neither an Organization nor a person
 * with active memberships in this context, and for any status other than
 * suspended/revoked.
 */
final readonly class ReactToIdentityStatusChangedForOrganizations
{
    private const array TRIGGERING_STATUSES = ['suspended', 'revoked'];

    public function __construct(
        private OrganizationRepository $organizations,
        private MembershipRepository $memberships,
        private AtomicOrganizationPersister $organizationPersister,
        private AtomicMembershipPersister $membershipPersister,
    ) {
    }

    public function handle(string $identityId, string $newStatus): void
    {
        if (!in_array($newStatus, self::TRIGGERING_STATUSES, true)) {
            return;
        }

        $organization = $this->organizations->findById(new OrganizationId($identityId));
        if ($organization !== null) {
            if ($organization->status() === OrganizationStatus::Active) {
                $organization->suspend();
                $this->organizationPersister->persist($organization);
            }

            return;
        }

        foreach ($this->memberships->findActiveByPerson($identityId) as $membership) {
            $membership->suspend();
            $this->membershipPersister->persist($membership);
        }
    }
}
