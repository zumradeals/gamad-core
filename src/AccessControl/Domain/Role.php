<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\AccessControl\Domain\Event\PermissionAddedToRole;
use Gamad\Core\AccessControl\Domain\Event\RoleCreated;
use Gamad\Core\AccessControl\Domain\Event\RoleDeprecated;
use Gamad\Core\Shared\Domain\DomainEvent;
use Gamad\Core\Shared\Domain\RecordsDomainEvents;

/**
 * GENESIS-014 §B — a Role changes rarely (a permission added or removed),
 * unlike RoleAssignment which changes often; keeping them as separate
 * aggregates avoids locking the whole role on every assignment/revocation.
 * Holds PermissionId references only, never full Permission objects — a
 * permission's name/description is looked up separately when needed.
 */
final class Role implements RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @var list<PermissionId> */
    private array $permissionIds = [];

    private function __construct(
        private readonly RoleId $id,
        private readonly string $name,
        private readonly RoleScope $scope,
        private RoleStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        RoleId $id,
        string $name,
        RoleScope $scope,
        ?DateTimeImmutable $createdAt = null,
    ): self {
        $createdAt ??= new DateTimeImmutable();
        $role = new self($id, $name, $scope, RoleStatus::Active, $createdAt);
        $role->recordedEvents[] = new RoleCreated($id, $name, $scope, $createdAt);

        return $role;
    }

    /** @param list<PermissionId> $permissionIds */
    public static function reconstitute(
        RoleId $id,
        string $name,
        RoleScope $scope,
        RoleStatus $status,
        DateTimeImmutable $createdAt,
        array $permissionIds = [],
    ): self {
        $role = new self($id, $name, $scope, $status, $createdAt);
        $role->permissionIds = $permissionIds;

        return $role;
    }

    public function id(): RoleId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function scope(): RoleScope
    {
        return $this->scope;
    }

    public function status(): RoleStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<PermissionId> */
    public function permissionIds(): array
    {
        return $this->permissionIds;
    }

    public function addPermission(PermissionId $permissionId, ?DateTimeImmutable $at = null): void
    {
        if ($this->status !== RoleStatus::Active) {
            throw new DomainException('Cannot add a permission to a deprecated role.');
        }

        foreach ($this->permissionIds as $existing) {
            if ($existing->equals($permissionId)) {
                return;
            }
        }

        $this->permissionIds[] = $permissionId;
        $this->recordedEvents[] = new PermissionAddedToRole($this->id, $permissionId, $at ?? new DateTimeImmutable());
    }

    public function deprecate(?DateTimeImmutable $at = null): void
    {
        if ($this->status === RoleStatus::Deprecated) {
            throw new DomainException('Role is already deprecated.');
        }

        $this->status = RoleStatus::Deprecated;
        $this->recordedEvents[] = new RoleDeprecated($this->id, $at ?? new DateTimeImmutable());
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
