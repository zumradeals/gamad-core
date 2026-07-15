<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicOrganizationPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateOrganization;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateOrganizationHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\IdentityNotEligibleForOrganization;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationAlreadyExists;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\ParentOrganizationNotEligible;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationStatus;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\InMemoryIdentityLookup;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\InMemoryOrganizationRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class CreateOrganizationHandlerTest extends TestCase
{
    public function test_it_creates_an_organization_from_an_active_identity(): void
    {
        [$handler, $identities, $organizations, $outbox] = $this->handler();
        $identities->register('GAM-GAT-ORG-000001', 'organization', 'active');

        $organization = $handler(new CreateOrganization('GAM-GAT-ORG-000001', 'GAMAD SAS'));

        self::assertSame(OrganizationStatus::Active, $organization->status());
        self::assertTrue($organizations->exists(new OrganizationId('GAM-GAT-ORG-000001')));
        self::assertCount(1, $outbox->messages);
    }

    public function test_it_rejects_an_identity_that_does_not_exist(): void
    {
        [$handler] = $this->handler();

        $this->expectException(IdentityNotEligibleForOrganization::class);

        $handler(new CreateOrganization('GAM-GAT-ORG-999999', 'Nobody'));
    }

    public function test_it_rejects_an_identity_that_is_not_of_type_organization(): void
    {
        [$handler, $identities] = $this->handler();
        $identities->register('GAM-GAT-PER-000001', 'person', 'active');

        $this->expectException(IdentityNotEligibleForOrganization::class);

        $handler(new CreateOrganization('GAM-GAT-PER-000001', 'Not an organization'));
    }

    public function test_it_rejects_an_inactive_identity(): void
    {
        [$handler, $identities] = $this->handler();
        $identities->register('GAM-GAT-ORG-000001', 'organization', 'suspended');

        $this->expectException(IdentityNotEligibleForOrganization::class);

        $handler(new CreateOrganization('GAM-GAT-ORG-000001', 'GAMAD SAS'));
    }

    public function test_it_rejects_creating_the_same_organization_twice(): void
    {
        [$handler, $identities] = $this->handler();
        $identities->register('GAM-GAT-ORG-000001', 'organization', 'active');
        $handler(new CreateOrganization('GAM-GAT-ORG-000001', 'GAMAD SAS'));

        $this->expectException(OrganizationAlreadyExists::class);

        $handler(new CreateOrganization('GAM-GAT-ORG-000001', 'GAMAD SAS'));
    }

    public function test_it_creates_a_child_organization_under_an_active_parent(): void
    {
        [$handler, $identities] = $this->handler();
        $identities->register('GAM-GAT-ORG-000001', 'organization', 'active');
        $identities->register('GAM-GAT-ORG-000002', 'organization', 'active');
        $handler(new CreateOrganization('GAM-GAT-ORG-000001', 'GAMAD SAS'));

        $child = $handler(new CreateOrganization('GAM-GAT-ORG-000002', 'GAMAD Technologie', 'GAM-GAT-ORG-000001'));

        self::assertTrue($child->parentId()?->equals(new OrganizationId('GAM-GAT-ORG-000001')));
    }

    public function test_it_rejects_a_parent_that_does_not_exist(): void
    {
        [$handler, $identities] = $this->handler();
        $identities->register('GAM-GAT-ORG-000002', 'organization', 'active');

        $this->expectException(ParentOrganizationNotEligible::class);

        $handler(new CreateOrganization('GAM-GAT-ORG-000002', 'GAMAD Technologie', 'GAM-GAT-ORG-000001'));
    }

    public function test_it_rejects_a_parent_that_is_not_active(): void
    {
        [$handler, $identities] = $this->handler();
        $identities->register('GAM-GAT-ORG-000001', 'organization', 'active');
        $identities->register('GAM-GAT-ORG-000002', 'organization', 'active');
        $parent = $handler(new CreateOrganization('GAM-GAT-ORG-000001', 'GAMAD SAS'));
        $parent->suspend();

        $this->expectException(ParentOrganizationNotEligible::class);

        $handler(new CreateOrganization('GAM-GAT-ORG-000002', 'GAMAD Technologie', 'GAM-GAT-ORG-000001'));
    }

    /** @return array{0: CreateOrganizationHandler, 1: InMemoryIdentityLookup, 2: InMemoryOrganizationRepository, 3: InMemoryOutboxRepository} */
    private function handler(): array
    {
        $identities = new InMemoryIdentityLookup();
        $organizations = new InMemoryOrganizationRepository();
        $outbox = new InMemoryOutboxRepository();

        $handler = new CreateOrganizationHandler(
            identities: $identities,
            organizations: $organizations,
            persister: new AtomicOrganizationPersister($organizations, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        return [$handler, $identities, $organizations, $outbox];
    }
}
