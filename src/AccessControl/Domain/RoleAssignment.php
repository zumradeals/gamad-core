<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\AccessControl\Domain\Event\RoleAssigned;
use Gamad\Core\AccessControl\Domain\Event\RoleRevoked;
use Gamad\Core\Shared\Domain\DomainEvent;
use Gamad\Core\Shared\Domain\RecordsDomainEvents;

/**
 * personId and organizationId are kept as plain strings, not
 * PersonsAndAccounts\Domain\PersonId or OrganizationsAndMemberships\Domain\
 * OrganizationId: this context never imports either namespace (ADR-0013 /
 * GENESIS-014 §D). Existence is verified applicatively in
 * AssignRoleHandler, never here.
 */
final class RoleAssignment implements RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly RoleAssignmentId $id,
        private readonly RoleId $roleId,
        private readonly string $personId,
        private readonly string $organizationId,
        private RoleAssignmentStatus $status,
        private readonly DateTimeImmutable $assignedAt,
        private ?DateTimeImmutable $revokedAt,
    ) {
    }

    public static function create(
        RoleAssignmentId $id,
        RoleId $roleId,
        string $personId,
        string $organizationId,
        ?DateTimeImmutable $assignedAt = null,
    ): self {
        $assignedAt ??= new DateTimeImmutable();
        $assignment = new self($id, $roleId, $personId, $organizationId, RoleAssignmentStatus::Active, $assignedAt, null);
        $assignment->recordedEvents[] = new RoleAssigned($id, $roleId, $personId, $organizationId, $assignedAt);

        return $assignment;
    }

    public static function reconstitute(
        RoleAssignmentId $id,
        RoleId $roleId,
        string $personId,
        string $organizationId,
        RoleAssignmentStatus $status,
        DateTimeImmutable $assignedAt,
        ?DateTimeImmutable $revokedAt = null,
    ): self {
        return new self($id, $roleId, $personId, $organizationId, $status, $assignedAt, $revokedAt);
    }

    public function id(): RoleAssignmentId
    {
        return $this->id;
    }

    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function status(): RoleAssignmentStatus
    {
        return $this->status;
    }

    public function assignedAt(): DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(?DateTimeImmutable $at = null): void
    {
        if ($this->status === RoleAssignmentStatus::Revoked) {
            throw new DomainException('Role assignment is already revoked.');
        }

        $at ??= new DateTimeImmutable();
        $this->status = RoleAssignmentStatus::Revoked;
        $this->revokedAt = $at;
        $this->recordedEvents[] = new RoleRevoked($this->id, $at);
    }

    public function recordedEvents(): array
    {
        return $this->recordedEvents;
    }

    public function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents();
        $this->clearRecordedEvents();

        return $events;
    }
}
