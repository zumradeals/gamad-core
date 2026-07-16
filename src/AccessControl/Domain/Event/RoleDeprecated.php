<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class RoleDeprecated implements DomainEvent
{
    public function __construct(
        public RoleId $roleId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'access_control.role.deprecated.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->roleId;
    }

    public function payload(): array
    {
        return [
            'role_id' => (string) $this->roleId,
        ];
    }
}
