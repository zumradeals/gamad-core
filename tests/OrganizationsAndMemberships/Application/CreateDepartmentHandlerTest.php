<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicOrganizationPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateDepartment;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateDepartmentHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationNotFound;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\InMemoryOrganizationRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class CreateDepartmentHandlerTest extends TestCase
{
    public function test_it_attaches_an_active_department_to_an_active_organization(): void
    {
        [$handler, $organizations] = $this->handler();
        $organizations->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS'));

        $department = $handler(new CreateDepartment('GAM-GAT-ORG-000001', 'Direction Générale'));

        self::assertSame('Direction Générale', $department->name());
        self::assertSame(DepartmentStatus::Active, $department->status());
        $organization = $organizations->findById(new OrganizationId('GAM-GAT-ORG-000001'));
        self::assertCount(1, $organization->departments());
    }

    public function test_it_rejects_an_organization_that_does_not_exist(): void
    {
        [$handler] = $this->handler();

        $this->expectException(OrganizationNotFound::class);

        $handler(new CreateDepartment('GAM-GAT-ORG-999999', 'Direction Générale'));
    }

    /** @return array{0: CreateDepartmentHandler, 1: InMemoryOrganizationRepository} */
    private function handler(): array
    {
        $organizations = new InMemoryOrganizationRepository();
        $outbox = new InMemoryOutboxRepository();

        $handler = new CreateDepartmentHandler(
            organizations: $organizations,
            persister: new AtomicOrganizationPersister($organizations, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        return [$handler, $organizations];
    }
}
