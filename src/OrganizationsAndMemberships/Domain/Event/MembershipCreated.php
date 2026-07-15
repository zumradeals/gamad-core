<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipType;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class MembershipCreated implements DomainEvent
{
    public function __construct(
        public MembershipId $membershipId,
        public string $personId,
        public OrganizationId $organizationId,
        public MembershipType $membershipType,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'membership.created.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->membershipId;
    }

    public function payload(): array
    {
        return [
            'membership_id' => (string) $this->membershipId,
            'person_id' => $this->personId,
            'organization_id' => (string) $this->organizationId,
            'membership_type' => $this->membershipType->value,
        ];
    }
}
