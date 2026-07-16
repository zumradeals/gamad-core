<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class PermissionAddedToRole implements DomainEvent
{
    public function __construct(
        public RoleId $roleId,
        public PermissionId $permissionId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'access_control.role.permission_added.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->roleId;
    }

    public function payload(): array
    {
        return [
            'role_id' => (string) $this->roleId,
            'permission_id' => (string) $this->permissionId,
        ];
    }
}
