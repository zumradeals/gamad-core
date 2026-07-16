<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class RoleRevoked implements DomainEvent
{
    public function __construct(
        public RoleAssignmentId $assignmentId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'access_control.role.revoked.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->assignmentId;
    }

    public function payload(): array
    {
        return [
            'assignment_id' => (string) $this->assignmentId,
        ];
    }
}
