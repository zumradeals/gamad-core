<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicMembershipPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\EndMembership;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\EndMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\ResumeMembership;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\ResumeMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\SuspendMembership;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\SuspendMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\MembershipNotFound;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipType;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\InMemoryMembershipRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Infrastructure\AccessControl\PermissiveAccessControlGateway;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class MembershipLifecycleHandlersTest extends TestCase
{
    public function test_suspend_handler_suspends_an_active_membership(): void
    {
        [$memberships, $persister] = $this->repository();
        $membership = Membership::create(MembershipId::generate(), 'GAM-GAT-PER-000001', new OrganizationId('GAM-GAT-ORG-000001'), MembershipType::GamadCitizen);
        $memberships->save($membership);
        $handler = new SuspendMembershipHandler($memberships, $persister, new PermissiveAccessControlGateway());

        $result = $handler(new SuspendMembership((string) $membership->id()));

        self::assertSame(MembershipStatus::Suspended, $result->status());
    }

    public function test_suspend_handler_rejects_an_unknown_membership(): void
    {
        [$memberships, $persister] = $this->repository();
        $handler = new SuspendMembershipHandler($memberships, $persister, new PermissiveAccessControlGateway());

        $this->expectException(MembershipNotFound::class);

        $handler(new SuspendMembership('11111111-1111-4111-8111-111111111111'));
    }

    public function test_resume_handler_resumes_a_suspended_membership(): void
    {
        [$memberships, $persister] = $this->repository();
        $membership = Membership::create(MembershipId::generate(), 'GAM-GAT-PER-000001', new OrganizationId('GAM-GAT-ORG-000001'), MembershipType::GamadCitizen);
        $membership->suspend();
        $memberships->save($membership);
        $handler = new ResumeMembershipHandler($memberships, $persister, new PermissiveAccessControlGateway());

        $result = $handler(new ResumeMembership((string) $membership->id()));

        self::assertSame(MembershipStatus::Active, $result->status());
    }

    public function test_resume_handler_rejects_an_unknown_membership(): void
    {
        [$memberships, $persister] = $this->repository();
        $handler = new ResumeMembershipHandler($memberships, $persister, new PermissiveAccessControlGateway());

        $this->expectException(MembershipNotFound::class);

        $handler(new ResumeMembership('11111111-1111-4111-8111-111111111111'));
    }

    public function test_end_handler_ends_an_active_membership(): void
    {
        [$memberships, $persister] = $this->repository();
        $membership = Membership::create(MembershipId::generate(), 'GAM-GAT-PER-000001', new OrganizationId('GAM-GAT-ORG-000001'), MembershipType::GamadCitizen);
        $memberships->save($membership);
        $handler = new EndMembershipHandler($memberships, $persister, new PermissiveAccessControlGateway());

        $result = $handler(new EndMembership((string) $membership->id()));

        self::assertSame(MembershipStatus::Ended, $result->status());
        self::assertNotNull($result->endedAt());
    }

    public function test_end_handler_rejects_an_unknown_membership(): void
    {
        [$memberships, $persister] = $this->repository();
        $handler = new EndMembershipHandler($memberships, $persister, new PermissiveAccessControlGateway());

        $this->expectException(MembershipNotFound::class);

        $handler(new EndMembership('11111111-1111-4111-8111-111111111111'));
    }

    /** @return array{0: InMemoryMembershipRepository, 1: AtomicMembershipPersister} */
    private function repository(): array
    {
        $memberships = new InMemoryMembershipRepository();
        $persister = new AtomicMembershipPersister($memberships, new InMemoryOutboxRepository(), new DomainEventCollector(), new SynchronousTransactionManager());

        return [$memberships, $persister];
    }
}
