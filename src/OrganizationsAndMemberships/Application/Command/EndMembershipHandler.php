<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicMembershipPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\MembershipNotFound;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipRepository;

final readonly class EndMembershipHandler
{
    public function __construct(
        private MembershipRepository $memberships,
        private AtomicMembershipPersister $persister,
    ) {
    }

    public function __invoke(EndMembership $command): Membership
    {
        $membership = $this->memberships->findById(new MembershipId($command->membershipId));
        if ($membership === null) {
            throw MembershipNotFound::withId($command->membershipId);
        }

        $membership->end();
        $this->persister->persist($membership);

        return $membership;
    }
}
