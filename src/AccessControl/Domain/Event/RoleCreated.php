<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class RoleCreated implements DomainEvent
{
    public function __construct(
        public RoleId $roleId,
        public string $name,
        public RoleScope $scope,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'access_control.role.created.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->roleId;
    }

    public function payload(): array
    {
        return [
            'role_id' => (string) $this->roleId,
            'name' => $this->name,
            'scope' => $this->scope->value,
        ];
    }
}
