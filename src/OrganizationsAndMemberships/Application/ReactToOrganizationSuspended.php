<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;

/**
 * GENESIS-011 §4 invariant 9 — suspending an Organization must suspend
 * every one of its active memberships. Reacts to the already-published
 * `organization.suspended.v1` event — never by reading the organizations
 * table directly from a membership handler.
 */
final readonly class ReactToOrganizationSuspended
{
    public function __construct(
        private MembershipRepository $memberships,
        private AtomicMembershipPersister $persister,
    ) {
    }

    public function handle(string $organizationId): void
    {
        foreach ($this->memberships->findActiveByOrganization(new OrganizationId($organizationId)) as $membership) {
            $membership->suspend();
            $this->persister->persist($membership);
        }
    }
}
