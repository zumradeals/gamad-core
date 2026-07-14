<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class SessionRevoked implements DomainEvent
{
    public function __construct(
        public SessionId $sessionId,
        public string $reason,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'session.revoked.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->sessionId;
    }

    public function payload(): array
    {
        return [
            'session_id' => (string) $this->sessionId,
            'reason' => $this->reason,
        ];
    }
}
