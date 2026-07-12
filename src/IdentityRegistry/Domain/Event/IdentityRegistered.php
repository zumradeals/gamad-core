<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class IdentityRegistered implements DomainEvent
{
    public function __construct(
        public IdentityId $identityId,
        public IdentityType $identityType,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'identity.registered.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->identityId;
    }

    public function payload(): array
    {
        return [
            'identity_id' => (string) $this->identityId,
            'identity_type' => $this->identityType->value,
        ];
    }
}
