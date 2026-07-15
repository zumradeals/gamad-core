<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain;

interface MembershipRepository
{
    public function save(Membership $membership): void;

    public function findById(MembershipId $membershipId): ?Membership;

    public function findActiveByPersonAndOrganization(string $personId, OrganizationId $organizationId): ?Membership;

    /** @return list<Membership> */
    public function findActiveByOrganization(OrganizationId $organizationId): array;

    /** @return list<Membership> */
    public function findActiveByPerson(string $personId): array;
}
