<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class OrganizationCreated implements DomainEvent
{
    public function __construct(
        public OrganizationId $organizationId,
        public ?OrganizationId $parentId,
        public string $name,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'organization.created.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->organizationId;
    }

    public function payload(): array
    {
        return [
            'organization_id' => (string) $this->organizationId,
            'parent_id' => $this->parentId !== null ? (string) $this->parentId : null,
            'name' => $this->name,
        ];
    }
}
