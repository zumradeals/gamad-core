<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentId;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\MembershipCreated;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\MembershipEnded;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\MembershipResumed;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\MembershipSuspended;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipType;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MembershipTest extends TestCase
{
    private function createMembership(): Membership
    {
        return Membership::create(
            MembershipId::generate(),
            'GAM-GAT-PER-000001',
            new OrganizationId('GAM-GAT-ORG-000001'),
            MembershipType::GamadCitizen,
            startedAt: new DateTimeImmutable('2026-07-14T00:00:00+00:00'),
        );
    }

    public function test_it_creates_an_active_membership(): void
    {
        $id = MembershipId::generate();
        $organizationId = new OrganizationId('GAM-GAT-ORG-000001');

        $membership = Membership::create($id, 'GAM-GAT-PER-000001', $organizationId, MembershipType::GamadCitizen);

        self::assertTrue($membership->id()->equals($id));
        self::assertSame('GAM-GAT-PER-000001', $membership->personId());
        self::assertTrue($membership->organizationId()->equals($organizationId));
        self::assertSame(MembershipType::GamadCitizen, $membership->membershipType());
        self::assertSame(MembershipStatus::Active, $membership->status());
        self::assertNull($membership->departmentId());
        self::assertNull($membership->endedAt());
        self::assertInstanceOf(MembershipCreated::class, $membership->releaseEvents()[0]);
    }

    public function test_it_records_an_optional_department(): void
    {
        $departmentId = DepartmentId::generate();

        $membership = Membership::create(
            MembershipId::generate(),
            'GAM-GAT-PER-000001',
            new OrganizationId('GAM-GAT-ORG-000001'),
            MembershipType::GamadCitizen,
            departmentId: $departmentId,
        );

        self::assertTrue($membership->departmentId()?->equals($departmentId));
    }

    public function test_membership_id_rejects_a_malformed_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MembershipId('not-a-uuid');
    }

    public function test_it_allows_active_to_suspended_transition(): void
    {
        $membership = $this->createMembership();
        $membership->releaseEvents();

        $membership->suspend();

        self::assertSame(MembershipStatus::Suspended, $membership->status());
        self::assertInstanceOf(MembershipSuspended::class, $membership->releaseEvents()[0]);
    }

    public function test_it_allows_suspended_back_to_active_transition(): void
    {
        $membership = $this->createMembership();
        $membership->suspend();
        $membership->releaseEvents();

        $membership->resume();

        self::assertSame(MembershipStatus::Active, $membership->status());
        self::assertInstanceOf(MembershipResumed::class, $membership->releaseEvents()[0]);
    }

    public function test_it_allows_active_to_ended_transition_and_records_the_end_date(): void
    {
        $membership = $this->createMembership();
        $membership->releaseEvents();
        $endedAt = new DateTimeImmutable('2026-08-01T00:00:00+00:00');

        $membership->end($endedAt);

        self::assertSame(MembershipStatus::Ended, $membership->status());
        self::assertSame($endedAt, $membership->endedAt());
        self::assertInstanceOf(MembershipEnded::class, $membership->releaseEvents()[0]);
    }

    public function test_it_allows_suspended_to_ended_transition(): void
    {
        $membership = $this->createMembership();
        $membership->suspend();

        $membership->end();

        self::assertSame(MembershipStatus::Ended, $membership->status());
    }

    public function test_ended_is_a_terminal_status(): void
    {
        $membership = $this->createMembership();
        $membership->end();

        $this->expectException(DomainException::class);

        $membership->resume();
    }

    public function test_it_rejects_resuming_an_already_active_membership(): void
    {
        $membership = $this->createMembership();

        $this->expectException(DomainException::class);

        $membership->resume();
    }
}
