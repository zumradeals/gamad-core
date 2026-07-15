<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicOrganizationPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\IdentityNotEligibleForOrganization;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationAlreadyExists;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\ParentOrganizationNotEligible;
use Gamad\Core\OrganizationsAndMemberships\Application\IdentityLookup;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationRepository;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationStatus;

/**
 * Verifies eligibility entirely here, in Application — Organization itself
 * is built without knowing anything about the Identity Registry (Task 3).
 */
final readonly class CreateOrganizationHandler
{
    public function __construct(
        private IdentityLookup $identities,
        private OrganizationRepository $organizations,
        private AtomicOrganizationPersister $persister,
    ) {
    }

    public function __invoke(CreateOrganization $command): Organization
    {
        $identity = $this->identities->find($command->identityId);
        if ($identity === null) {
            // Not found in this Core's own Identity Registry — by construction
            // (ADR-0017 §3), that also covers "belongs to a different realm".
            throw IdentityNotEligibleForOrganization::notFound($command->identityId);
        }
        if ($identity->type !== 'organization') {
            throw IdentityNotEligibleForOrganization::wrongType($command->identityId, $identity->type);
        }
        if ($identity->status !== 'active') {
            throw IdentityNotEligibleForOrganization::notActive($command->identityId, $identity->status);
        }

        $organizationId = new OrganizationId($command->identityId);
        if ($this->organizations->exists($organizationId)) {
            throw OrganizationAlreadyExists::withId($command->identityId);
        }

        $parentId = null;
        if ($command->parentId !== null) {
            $parentId = new OrganizationId($command->parentId);
            $parent = $this->organizations->findById($parentId);
            if ($parent === null) {
                throw ParentOrganizationNotEligible::notFound($command->parentId);
            }
            if ($parent->status() !== OrganizationStatus::Active) {
                throw ParentOrganizationNotEligible::notActive($command->parentId);
            }
        }

        $organization = Organization::create($organizationId, $parentId, $command->name, $command->foundedAt);
        $this->persister->persist($organization);

        return $organization;
    }
}
