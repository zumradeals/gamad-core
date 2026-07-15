<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicMembershipPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\ReactToOrganizationSuspended;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipType;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\InMemoryMembershipRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

/** GENESIS-011 §4 invariant 9 — cascade: organization suspended -> its active memberships suspended. */
final class ReactToOrganizationSuspendedTest extends TestCase
{
    public function test_it_suspends_every_active_membership_of_the_organization(): void
    {
        $memberships = new InMemoryMembershipRepository();
        $organizationId = new OrganizationId('GAM-GAT-ORG-000001');
        $otherOrganizationId = new OrganizationId('GAM-GAT-ORG-000002');

        $inScope = Membership::create(MembershipId::generate(), 'GAM-GAT-PER-000001', $organizationId, MembershipType::GamadCitizen);
        $alreadyEnded = Membership::create(MembershipId::generate(), 'GAM-GAT-PER-000002', $organizationId, MembershipType::GamadCitizen);
        $alreadyEnded->end();
        $outOfScope = Membership::create(MembershipId::generate(), 'GAM-GAT-PER-000003', $otherOrganizationId, MembershipType::GamadCitizen);

        $memberships->save($inScope);
        $memberships->save($alreadyEnded);
        $memberships->save($outOfScope);

        $reactor = new ReactToOrganizationSuspended(
            memberships: $memberships,
            persister: new AtomicMembershipPersister($memberships, new InMemoryOutboxRepository(), new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $reactor->handle('GAM-GAT-ORG-000001');

        self::assertSame(MembershipStatus::Suspended, $memberships->findById($inScope->id())->status());
        self::assertSame(MembershipStatus::Ended, $memberships->findById($alreadyEnded->id())->status());
        self::assertSame(MembershipStatus::Active, $memberships->findById($outOfScope->id())->status());
    }
}
