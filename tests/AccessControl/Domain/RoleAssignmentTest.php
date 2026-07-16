<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Domain;

use DomainException;
use Gamad\Core\AccessControl\Domain\Event\RoleAssigned;
use Gamad\Core\AccessControl\Domain\Event\RoleRevoked;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleAssignmentStatus;
use Gamad\Core\AccessControl\Domain\RoleId;
use PHPUnit\Framework\TestCase;

final class RoleAssignmentTest extends TestCase
{
    public function test_it_creates_an_active_assignment(): void
    {
        $id = RoleAssignmentId::generate();
        $roleId = RoleId::generate();

        $assignment = RoleAssignment::create($id, $roleId, 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001');

        self::assertTrue($assignment->id()->equals($id));
        self::assertTrue($assignment->roleId()->equals($roleId));
        self::assertSame('GAM-GAT-PER-000001', $assignment->personId());
        self::assertSame('GAM-GAT-ORG-000001', $assignment->organizationId());
        self::assertSame(RoleAssignmentStatus::Active, $assignment->status());
        self::assertNull($assignment->revokedAt());
        self::assertInstanceOf(RoleAssigned::class, $assignment->releaseEvents()[0]);
    }

    public function test_it_revokes_an_active_assignment(): void
    {
        $assignment = RoleAssignment::create(RoleAssignmentId::generate(), RoleId::generate(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001');
        $assignment->releaseEvents();

        $assignment->revoke();

        self::assertSame(RoleAssignmentStatus::Revoked, $assignment->status());
        self::assertNotNull($assignment->revokedAt());
        self::assertInstanceOf(RoleRevoked::class, $assignment->releaseEvents()[0]);
    }

    public function test_it_rejects_revoking_an_already_revoked_assignment(): void
    {
        $assignment = RoleAssignment::create(RoleAssignmentId::generate(), RoleId::generate(), 'GAM-GAT-PER-000001', 'GAM-GAT-ORG-000001');
        $assignment->revoke();

        $this->expectException(DomainException::class);

        $assignment->revoke();
    }
}
