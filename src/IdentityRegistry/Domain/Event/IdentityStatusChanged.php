<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class IdentityStatusChanged implements DomainEvent
{
    public function __construct(
        public IdentityId $identityId,
        public IdentityStatus $from,
        public IdentityStatus $to,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }
    public function eventName(): string { return 'identity.status_changed.v1'; }
    public function aggregateId(): string { return (string) $this->identityId; }
    public function payload(): array
    {
        return [
            'identity_id' => (string) $this->identityId,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
