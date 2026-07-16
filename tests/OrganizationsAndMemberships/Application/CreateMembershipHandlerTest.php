<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicMembershipPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateMembership;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\DepartmentNotFound;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\MembershipAlreadyActive;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationNotFound;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\PersonNotFound;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipType;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\InMemoryMembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\InMemoryOrganizationRepository;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\InMemoryPersonLookup;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Infrastructure\AccessControl\PermissiveAccessControlGateway;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class CreateMembershipHandlerTest extends TestCase
{
    public function test_it_creates_an_active_membership(): void
    {
        [$handler, $persons, $organizations] = $this->handler();
        $persons->register('GAM-GAT-PER-000001');
        $organizations->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS'));

        $membership = $handler(new CreateMembership('GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001', 'GAMAD_CITIZEN'));

        self::assertSame(MembershipStatus::Active, $membership->status());
        self::assertSame(MembershipType::GamadCitizen, $membership->membershipType());
    }

    public function test_it_rejects_a_person_that_does_not_exist(): void
    {
        [$handler, , $organizations] = $this->handler();
        $organizations->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS'));

        $this->expectException(PersonNotFound::class);

        $handler(new CreateMembership('GAM-GAT-PER-999999', 'GAM-GAT-ORG-000001', 'GAMAD_CITIZEN'));
    }

    public function test_it_rejects_an_organization_that_does_not_exist(): void
    {
        [$handler, $persons] = $this->handler();
        $persons->register('GAM-GAT-PER-000001');

        $this->expectException(OrganizationNotFound::class);

        $handler(new CreateMembership('GAM-GAT-PER-000001', 'GAM-GAT-ORG-999999', 'GAMAD_CITIZEN'));
    }

    public function test_it_rejects_a_department_that_does_not_belong_to_the_organization(): void
    {
        [$handler, $persons, $organizations] = $this->handler();
        $persons->register('GAM-GAT-PER-000001');
        $organizations->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS'));

        $this->expectException(DepartmentNotFound::class);

        $handler(new CreateMembership(
            'GAM-GAT-PER-000001',
            'GAM-GAT-ORG-000001',
            'GAMAD_CITIZEN',
            departmentId: '11111111-1111-4111-8111-111111111111',
        ));
    }

    public function test_it_rejects_a_second_active_membership_for_the_same_person_and_organization(): void
    {
        [$handler, $persons, $organizations] = $this->handler();
        $persons->register('GAM-GAT-PER-000001');
        $organizations->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS'));
        $handler(new CreateMembership('GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001', 'GAMAD_CITIZEN'));

        $this->expectException(MembershipAlreadyActive::class);

        $handler(new CreateMembership('GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001', 'ORDINARY_CITIZEN'));
    }

    public function test_it_allows_a_person_to_have_active_memberships_in_different_organizations(): void
    {
        [$handler, $persons, $organizations] = $this->handler();
        $persons->register('GAM-GAT-PER-000001');
        $organizations->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS'));
        $organizations->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000002'), new OrganizationId('GAM-GAT-ORG-000001'), 'GAMAD Technologie'));
        $handler(new CreateMembership('GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001', 'GAMAD_CITIZEN'));

        $second = $handler(new CreateMembership('GAM-GAT-PER-000001', 'GAM-GAT-ORG-000002', 'GAMAD_CITIZEN'));

        self::assertSame(MembershipStatus::Active, $second->status());
    }

    /** @return array{0: CreateMembershipHandler, 1: InMemoryPersonLookup, 2: InMemoryOrganizationRepository} */
    private function handler(): array
    {
        $persons = new InMemoryPersonLookup();
        $organizations = new InMemoryOrganizationRepository();
        $memberships = new InMemoryMembershipRepository();
        $outbox = new InMemoryOutboxRepository();

        $handler = new CreateMembershipHandler(
            persons: $persons,
            organizations: $organizations,
            memberships: $memberships,
            persister: new AtomicMembershipPersister($memberships, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
            accessControl: new PermissiveAccessControlGateway(),
        );

        return [$handler, $persons, $organizations];
    }
}
