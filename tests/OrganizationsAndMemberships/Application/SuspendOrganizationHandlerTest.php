<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicOrganizationPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\SuspendOrganization;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\SuspendOrganizationHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationNotFound;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationStatus;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\InMemoryOrganizationRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Infrastructure\AccessControl\PermissiveAccessControlGateway;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class SuspendOrganizationHandlerTest extends TestCase
{
    public function test_it_suspends_an_active_organization_and_emits_the_event(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $organizations->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS'));
        $outbox = new InMemoryOutboxRepository();
        $handler = new SuspendOrganizationHandler(
            organizations: $organizations,
            persister: new AtomicOrganizationPersister($organizations, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
            accessControl: new PermissiveAccessControlGateway(),
        );

        $organization = $handler(new SuspendOrganization('GAM-GAT-ORG-000001'));

        self::assertSame(OrganizationStatus::Inactive, $organization->status());
        self::assertCount(2, $outbox->messages); // organization.created.v1 + organization.suspended.v1
        self::assertSame('organization.suspended.v1', $outbox->messages[array_key_last($outbox->messages)]->eventName);
    }

    public function test_it_rejects_an_organization_that_does_not_exist(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $outbox = new InMemoryOutboxRepository();
        $handler = new SuspendOrganizationHandler(
            organizations: $organizations,
            persister: new AtomicOrganizationPersister($organizations, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
            accessControl: new PermissiveAccessControlGateway(),
        );

        $this->expectException(OrganizationNotFound::class);

        $handler(new SuspendOrganization('GAM-GAT-ORG-999999'));
    }
}
