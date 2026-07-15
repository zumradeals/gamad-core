<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain;

interface OrganizationRepository
{
    public function save(Organization $organization): void;

    public function findById(OrganizationId $organizationId): ?Organization;

    public function exists(OrganizationId $organizationId): bool;

    /** @return list<Organization> */
    public function findChildren(OrganizationId $parentId): array;
}
