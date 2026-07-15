<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class MembershipSuspended implements DomainEvent
{
    public function __construct(
        public MembershipId $membershipId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'membership.suspended.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->membershipId;
    }

    public function payload(): array
    {
        return [
            'membership_id' => (string) $this->membershipId,
        ];
    }
}
