<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence;

use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;

final class InMemoryMembershipRepository implements MembershipRepository
{
    /** @var array<string, Membership> */
    private array $memberships = [];

    public function save(Membership $membership): void
    {
        $this->memberships[(string) $membership->id()] = $membership;
    }

    public function findById(MembershipId $membershipId): ?Membership
    {
        return $this->memberships[(string) $membershipId] ?? null;
    }

    public function findActiveByPersonAndOrganization(string $personId, OrganizationId $organizationId): ?Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->status() === MembershipStatus::Active
                && $membership->personId() === $personId
                && $membership->organizationId()->equals($organizationId)
            ) {
                return $membership;
            }
        }

        return null;
    }

    public function findActiveByOrganization(OrganizationId $organizationId): array
    {
        return array_values(array_filter(
            $this->memberships,
            static fn (Membership $candidate): bool => $candidate->status() === MembershipStatus::Active
                && $candidate->organizationId()->equals($organizationId),
        ));
    }

    public function findActiveByPerson(string $personId): array
    {
        return array_values(array_filter(
            $this->memberships,
            static fn (Membership $candidate): bool => $candidate->status() === MembershipStatus::Active
                && $candidate->personId() === $personId,
        ));
    }
}
