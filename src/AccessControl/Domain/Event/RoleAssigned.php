<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class RoleAssigned implements DomainEvent
{
    public function __construct(
        public RoleAssignmentId $assignmentId,
        public RoleId $roleId,
        public string $personId,
        public string $organizationId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'access_control.role.assigned.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->assignmentId;
    }

    public function payload(): array
    {
        return [
            'assignment_id' => (string) $this->assignmentId,
            'role_id' => (string) $this->roleId,
            'person_id' => $this->personId,
            'organization_id' => $this->organizationId,
        ];
    }
}
