<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence;

use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationRepository;

final class InMemoryOrganizationRepository implements OrganizationRepository
{
    /** @var array<string, Organization> */
    private array $organizations = [];

    public function save(Organization $organization): void
    {
        $this->organizations[(string) $organization->id()] = $organization;
    }

    public function findById(OrganizationId $organizationId): ?Organization
    {
        return $this->organizations[(string) $organizationId] ?? null;
    }

    public function exists(OrganizationId $organizationId): bool
    {
        return isset($this->organizations[(string) $organizationId]);
    }

    public function findChildren(OrganizationId $parentId): array
    {
        return array_values(array_filter(
            $this->organizations,
            static fn (Organization $candidate): bool => $candidate->parentId()?->equals($parentId) ?? false,
        ));
    }
}
