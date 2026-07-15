<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentId;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\OrganizationCreated;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\OrganizationReactivated;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\OrganizationSuspended;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrganizationTest extends TestCase
{
    public function test_it_creates_an_active_organization_without_a_parent(): void
    {
        $foundedAt = new DateTimeImmutable('2026-07-14T00:00:00+00:00');
        $id = new OrganizationId('GAM-GAT-ORG-000001');

        $organization = Organization::create($id, null, 'GAMAD SAS', $foundedAt);

        self::assertTrue($organization->id()->equals($id));
        self::assertNull($organization->parentId());
        self::assertSame('GAMAD SAS', $organization->name());
        self::assertSame(OrganizationStatus::Active, $organization->status());
        self::assertInstanceOf(OrganizationCreated::class, $organization->releaseEvents()[0]);
    }

    public function test_it_creates_an_organization_with_a_parent(): void
    {
        $parentId = new OrganizationId('GAM-GAT-ORG-000001');
        $id = new OrganizationId('GAM-GAT-ORG-000002');

        $organization = Organization::create($id, $parentId, 'GAMAD Technologie');

        self::assertTrue($organization->parentId()?->equals($parentId));
    }

    public function test_organization_id_rejects_a_malformed_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationId('not-an-identity');
    }

    public function test_it_allows_active_to_inactive_transition(): void
    {
        $organization = Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS');
        $organization->releaseEvents();

        $organization->suspend();

        self::assertSame(OrganizationStatus::Inactive, $organization->status());
        self::assertInstanceOf(OrganizationSuspended::class, $organization->releaseEvents()[0]);
    }

    public function test_it_allows_inactive_back_to_active_transition(): void
    {
        $organization = Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS');
        $organization->suspend();
        $organization->releaseEvents();

        $organization->reactivate();

        self::assertSame(OrganizationStatus::Active, $organization->status());
        self::assertInstanceOf(OrganizationReactivated::class, $organization->releaseEvents()[0]);
    }

    public function test_it_rejects_suspending_an_already_inactive_organization(): void
    {
        $organization = Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS');
        $organization->suspend();

        $this->expectException(DomainException::class);

        $organization->suspend();
    }

    public function test_it_adds_an_active_department_to_an_active_organization(): void
    {
        $organization = Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS');
        $departmentId = DepartmentId::generate();

        $organization->addDepartment($departmentId, 'Direction Générale');

        self::assertCount(1, $organization->departments());
        $department = $organization->departments()[0];
        self::assertTrue($department->id()->equals($departmentId));
        self::assertSame('Direction Générale', $department->name());
        self::assertSame(DepartmentStatus::Active, $department->status());
    }

    public function test_it_rejects_adding_a_department_to_a_suspended_organization(): void
    {
        $organization = Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS');
        $organization->suspend();

        $this->expectException(DomainException::class);

        $organization->addDepartment(DepartmentId::generate(), 'Direction Générale');
    }

    public function test_it_allows_a_department_active_to_inactive_transition(): void
    {
        $organization = Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS');
        $organization->addDepartment(DepartmentId::generate(), 'Direction Générale');
        $department = $organization->departments()[0];

        $department->transitionTo(DepartmentStatus::Inactive);

        self::assertSame(DepartmentStatus::Inactive, $department->status());
    }

    public function test_department_allows_inactive_back_to_active_transition(): void
    {
        $organization = Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS');
        $organization->addDepartment(DepartmentId::generate(), 'Direction Générale');
        $department = $organization->departments()[0];
        $department->transitionTo(DepartmentStatus::Inactive);

        $department->transitionTo(DepartmentStatus::Active);

        self::assertSame(DepartmentStatus::Active, $department->status());
    }
}
