<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicMembershipPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\DepartmentNotFound;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\MembershipAlreadyActive;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationNotFound;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\PersonNotFound;
use Gamad\Core\OrganizationsAndMemberships\Application\PersonLookup;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentId;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipType;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationRepository;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Shared\Contract\IdentityId as ContractIdentityId;
use InvalidArgumentException;

/**
 * The membership_type is always taken verbatim from the operator's explicit
 * choice (GENESIS-011 §3.1) — never inferred from the person or organization
 * here or anywhere upstream.
 */
final readonly class CreateMembershipHandler
{
    public function __construct(
        private PersonLookup $persons,
        private OrganizationRepository $organizations,
        private MembershipRepository $memberships,
        private AtomicMembershipPersister $persister,
        private AccessControlGateway $accessControl,
    ) {
    }

    public function __invoke(CreateMembership $command): Membership
    {
        // The organization is the natural context for a membership
        // (ADR-0021 Task 2); the actor defaults to the person being
        // enrolled when no distinct actor is supplied (self-enrollment).
        try {
            $actor = new ContractIdentityId($command->actorId ?? $command->personId);
            $context = new ContractIdentityId($command->organizationId);
            $decision = $this->accessControl->can($actor, 'membership:create', $context);
            if (!$decision->allowed) {
                throw AccessDenied::forDecision('membership:create', $decision->reason);
            }
        } catch (InvalidArgumentException) {
        }

        if (!$this->persons->exists($command->personId)) {
            throw PersonNotFound::withId($command->personId);
        }

        $organizationId = new OrganizationId($command->organizationId);
        $organization = $this->organizations->findById($organizationId);
        if ($organization === null) {
            throw OrganizationNotFound::withId($command->organizationId);
        }

        $departmentId = null;
        if ($command->departmentId !== null) {
            $departmentId = new DepartmentId($command->departmentId);
            $belongsToOrganization = false;
            foreach ($organization->departments() as $department) {
                if ($department->id()->equals($departmentId)) {
                    $belongsToOrganization = true;
                    break;
                }
            }
            if (!$belongsToOrganization) {
                throw DepartmentNotFound::withId($command->departmentId, $command->organizationId);
            }
        }

        // Applicative half of the "at most one active membership" invariant
        // — the partial unique index (ADR-0020) is the structural half.
        if ($this->memberships->findActiveByPersonAndOrganization($command->personId, $organizationId) !== null) {
            throw MembershipAlreadyActive::forPersonAndOrganization($command->personId, $command->organizationId);
        }

        $membership = Membership::create(
            MembershipId::generate(),
            $command->personId,
            $organizationId,
            MembershipType::from($command->membershipType),
            $departmentId,
            $command->startedAt,
        );
        $this->persister->persist($membership);

        return $membership;
    }
}
