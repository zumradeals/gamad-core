<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\IdentityRegistry\Domain\Event\IdentityRegistered;
use Gamad\Core\Shared\Domain\DomainEvent;

final class Identity
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly IdentityId $id,
        private readonly IdentityType $type,
        private IdentityStatus $status,
        private readonly DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(
        IdentityId $id,
        IdentityType $type,
        ?DateTimeImmutable $registeredAt = null,
    ): self {
        $registeredAt ??= new DateTimeImmutable();

        $identity = new self(
            id: $id,
            type: $type,
            status: IdentityStatus::Active,
            registeredAt: $registeredAt,
        );

        $identity->recordedEvents[] = new IdentityRegistered(
            identityId: $id,
            identityType: $type,
            occurredAt: $registeredAt,
        );

        return $identity;
    }

    public static function reconstitute(
        IdentityId $id,
        IdentityType $type,
        IdentityStatus $status,
        DateTimeImmutable $registeredAt,
    ): self {
        return new self(
            id: $id,
            type: $type,
            status: $status,
            registeredAt: $registeredAt,
        );
    }

    public function id(): IdentityId
    {
        return $this->id;
    }

    public function type(): IdentityType
    {
        return $this->type;
    }

    public function status(): IdentityStatus
    {
        return $this->status;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function suspend(): void
    {
        if ($this->status !== IdentityStatus::Active) {
            throw new DomainException('Only an active identity can be suspended.');
        }

        $this->status = IdentityStatus::Suspended;
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
