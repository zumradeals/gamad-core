<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\Shared\Domain\DomainEvent;

/**
 * ADR-0022 — published for every evaluation, ALLOW or DENY, to the
 * dedicated `access_decisions` Outbox queue, never `outbox_messages`
 * (isolated from business-domain events by volume).
 */
final readonly class AccessDecisionMade implements DomainEvent
{
    public function __construct(
        public string $actorId,
        public string $action,
        public string $contextId,
        public bool $allowed,
        public string $reason,
        private DateTimeImmutable $evaluatedAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->evaluatedAt;
    }

    public function eventName(): string
    {
        return 'access_control.access_decision_made.v1';
    }

    public function aggregateId(): string
    {
        return $this->actorId;
    }

    public function payload(): array
    {
        return [
            'actor_id' => $this->actorId,
            'action' => $this->action,
            'context_id' => $this->contextId,
            'decision' => $this->allowed ? 'ALLOW' : 'DENY',
            'reason' => $this->reason,
            'evaluated_at' => $this->evaluatedAt->format(DATE_ATOM),
        ];
    }
}
