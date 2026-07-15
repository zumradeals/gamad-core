<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\Shared\Domain\DomainEvent;

/**
 * Consumed by this same context (GENESIS-011 §4 invariant 9) to suspend every
 * active membership of this organization — never by direct read of another
 * table, always through this event.
 */
final readonly class OrganizationSuspended implements DomainEvent
{
    public function __construct(
        public OrganizationId $organizationId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'organization.suspended.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->organizationId;
    }

    public function payload(): array
    {
        return [
            'organization_id' => (string) $this->organizationId,
        ];
    }
}
