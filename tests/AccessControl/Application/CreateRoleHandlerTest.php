<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Application;

use Gamad\Core\AccessControl\Application\AtomicRolePersister;
use Gamad\Core\AccessControl\Application\Command\CreateRole;
use Gamad\Core\AccessControl\Application\Command\CreateRoleHandler;
use Gamad\Core\AccessControl\Application\Exception\RoleAlreadyExists;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Domain\RoleStatus;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryRoleRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class CreateRoleHandlerTest extends TestCase
{
    public function test_it_creates_an_active_role_and_emits_an_event(): void
    {
        $roles = new InMemoryRoleRepository();
        $outbox = new InMemoryOutboxRepository();
        $handler = new CreateRoleHandler(
            roles: $roles,
            persister: new AtomicRolePersister($roles, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $role = $handler(new CreateRole('org_admin', 'organization'));

        self::assertSame('org_admin', $role->name());
        self::assertSame(RoleScope::Organization, $role->scope());
        self::assertSame(RoleStatus::Active, $role->status());
        self::assertCount(1, $outbox->messages);
    }

    public function test_it_rejects_a_duplicate_role_name(): void
    {
        $roles = new InMemoryRoleRepository();
        $outbox = new InMemoryOutboxRepository();
        $handler = new CreateRoleHandler(
            roles: $roles,
            persister: new AtomicRolePersister($roles, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );
        $handler(new CreateRole('org_admin', 'organization'));

        $this->expectException(RoleAlreadyExists::class);

        $handler(new CreateRole('org_admin', 'organization'));
    }
}
