<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Application;

use Gamad\Core\AccessControl\Application\Command\EvaluateAccess;
use Gamad\Core\AccessControl\Application\Command\EvaluateAccessHandler;
use Gamad\Core\AccessControl\Domain\AccessControlEngine;
use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryPermissionRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryRoleAssignmentRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryRoleRepository;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use PHPUnit\Framework\TestCase;

final class EvaluateAccessHandlerTest extends TestCase
{
    public function test_it_allows_and_audits_the_decision(): void
    {
        $roles = new InMemoryRoleRepository();
        $permissions = new InMemoryPermissionRepository();
        $assignments = new InMemoryRoleAssignmentRepository();
        $outbox = new InMemoryOutboxRepository();

        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $permission = new Permission(PermissionId::generate(), 'membership:create', 'Create a membership');
        $role->addPermission($permission->id);
        $roles->save($role);
        $permissions->save($permission);
        $assignments->save(RoleAssignment::create(RoleAssignmentId::generate(), $role->id(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001'));

        $handler = new EvaluateAccessHandler($assignments, $roles, $permissions, new AccessControlEngine(), $outbox);

        $decision = $handler(new EvaluateAccess('GAM-GAT-PER-000001', 'membership:create', 'GAM-GAT-ORG-000001'));

        self::assertTrue($decision->allowed);
        self::assertCount(1, $outbox->messages);
        self::assertSame('access_control.access_decision_made.v1', $outbox->messages[0]->eventName);
        self::assertSame('ALLOW', $outbox->messages[0]->payload['decision']);
    }

    public function test_it_denies_and_audits_the_decision_when_no_role_matches(): void
    {
        $outbox = new InMemoryOutboxRepository();
        $handler = new EvaluateAccessHandler(
            new InMemoryRoleAssignmentRepository(),
            new InMemoryRoleRepository(),
            new InMemoryPermissionRepository(),
            new AccessControlEngine(),
            $outbox,
        );

        $decision = $handler(new EvaluateAccess('GAM-GAT-PER-000001', 'membership:create', 'GAM-GAT-ORG-000001'));

        self::assertFalse($decision->allowed);
        self::assertCount(1, $outbox->messages);
        self::assertSame('DENY', $outbox->messages[0]->payload['decision']);
    }
}
