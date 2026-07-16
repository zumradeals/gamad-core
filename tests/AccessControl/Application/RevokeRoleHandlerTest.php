<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Application;

use Gamad\Core\AccessControl\Application\AtomicRoleAssignmentPersister;
use Gamad\Core\AccessControl\Application\Command\RevokeRole;
use Gamad\Core\AccessControl\Application\Command\RevokeRoleHandler;
use Gamad\Core\AccessControl\Application\Exception\RoleAssignmentNotFound;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleAssignmentStatus;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryRoleAssignmentRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class RevokeRoleHandlerTest extends TestCase
{
    public function test_it_revokes_an_active_assignment(): void
    {
        $assignments = new InMemoryRoleAssignmentRepository();
        $assignment = RoleAssignment::create(RoleAssignmentId::generate(), RoleId::generate(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001');
        $assignments->save($assignment);
        $handler = new RevokeRoleHandler(
            assignments: $assignments,
            persister: new AtomicRoleAssignmentPersister($assignments, new InMemoryOutboxRepository(), new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $revoked = $handler(new RevokeRole((string) $assignment->id()));

        self::assertSame(RoleAssignmentStatus::Revoked, $revoked->status());
    }

    public function test_it_rejects_an_unknown_assignment(): void
    {
        $assignments = new InMemoryRoleAssignmentRepository();
        $handler = new RevokeRoleHandler(
            assignments: $assignments,
            persister: new AtomicRoleAssignmentPersister($assignments, new InMemoryOutboxRepository(), new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $this->expectException(RoleAssignmentNotFound::class);

        $handler(new RevokeRole(RoleAssignmentId::generate()->value));
    }
}
