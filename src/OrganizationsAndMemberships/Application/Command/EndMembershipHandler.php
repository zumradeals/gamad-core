<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicMembershipPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\MembershipNotFound;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipRepository;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Shared\Contract\IdentityId as ContractIdentityId;
use InvalidArgumentException;

final readonly class EndMembershipHandler
{
    public function __construct(
        private MembershipRepository $memberships,
        private AtomicMembershipPersister $persister,
        private AccessControlGateway $accessControl,
    ) {
    }

    public function __invoke(EndMembership $command): Membership
    {
        $membership = $this->memberships->findById(new MembershipId($command->membershipId));
        if ($membership === null) {
            throw MembershipNotFound::withId($command->membershipId);
        }

        try {
            $actor = new ContractIdentityId($command->actorId ?? $membership->personId());
            $context = new ContractIdentityId((string) $membership->organizationId());
            $decision = $this->accessControl->can($actor, 'membership:status:change', $context);
            if (!$decision->allowed) {
                throw AccessDenied::forDecision('membership:status:change', $decision->reason);
            }
        } catch (InvalidArgumentException) {
        }

        $membership->end();
        $this->persister->persist($membership);

        return $membership;
    }
}
