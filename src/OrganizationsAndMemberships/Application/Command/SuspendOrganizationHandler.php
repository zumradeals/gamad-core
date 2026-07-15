<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicOrganizationPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationNotFound;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationRepository;

/**
 * Only suspends the Organization and emits OrganizationSuspended — it never
 * touches memberships itself. The cascade to active memberships
 * (GENESIS-011 §4 invariant 9) happens when that event is later published,
 * consumed by ReactToOrganizationSuspended (same context, never a direct
 * read of another table).
 */
final readonly class SuspendOrganizationHandler
{
    public function __construct(
        private OrganizationRepository $organizations,
        private AtomicOrganizationPersister $persister,
    ) {
    }

    public function __invoke(SuspendOrganization $command): Organization
    {
        $organization = $this->organizations->findById(new OrganizationId($command->organizationId));
        if ($organization === null) {
            throw OrganizationNotFound::withId($command->organizationId);
        }

        $organization->suspend();
        $this->persister->persist($organization);

        return $organization;
    }
}
