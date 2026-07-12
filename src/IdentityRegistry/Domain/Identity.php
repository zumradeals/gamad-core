<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\IdentityRegistry\Domain\Event\IdentityRegistered;
use Gamad\Core\IdentityRegistry\Domain\Event\IdentityStatusChanged;
use Gamad\Core\Shared\Domain\DomainEvent;
use Gamad\Core\Shared\Domain\RecordsDomainEvents;

final class Identity implements RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly IdentityId $id,
        private readonly IdentityType $type,
        private IdentityStatus $status,
        private readonly DateTimeImmutable $registeredAt,
    ) {}

    public static function register(IdentityId $id, IdentityType $type, ?DateTimeImmutable $registeredAt = null): self
    {
        $registeredAt ??= new DateTimeImmutable();
        $identity = new self($id, $type, IdentityStatus::Active, $registeredAt);
        $identity->recordedEvents[] = new IdentityRegistered($id, $type, $registeredAt);
        return $identity;
    }

    public static function reconstitute(IdentityId $id, IdentityType $type, IdentityStatus $status, DateTimeImmutable $registeredAt): self
    {
        return new self($id, $type, $status, $registeredAt);
    }

    public function id(): IdentityId { return $this->id; }
    public function type(): IdentityType { return $this->type; }
    public function status(): IdentityStatus { return $this->status; }
    public function registeredAt(): DateTimeImmutable { return $this->registeredAt; }

    public function transitionTo(IdentityStatus $target, ?DateTimeImmutable $at = null): void
    {
        $allowed = match ($this->status) {
            IdentityStatus::Draft => [IdentityStatus::Active, IdentityStatus::Archived],
            IdentityStatus::Active => [IdentityStatus::Suspended, IdentityStatus::Archived, IdentityStatus::Revoked],
            IdentityStatus::Suspended => [IdentityStatus::Active, IdentityStatus::Archived, IdentityStatus::Revoked],
            IdentityStatus::Archived => [],
            IdentityStatus::Revoked => [],
        };

        if (!in_array($target, $allowed, true)) {
            throw new DomainException(sprintf('Transition from %s to %s is not allowed.', $this->status->value, $target->value));
        }

        $previous = $this->status;
        $this->status = $target;
        $this->recordedEvents[] = new IdentityStatusChanged($this->id, $previous, $target, $at ?? new DateTimeImmutable());
    }

    public function suspend(): void { $this->transitionTo(IdentityStatus::Suspended); }
    public function activate(): void { $this->transitionTo(IdentityStatus::Active); }
    public function archive(): void { $this->transitionTo(IdentityStatus::Archived); }
    public function revoke(): void { $this->transitionTo(IdentityStatus::Revoked); }

    public function recordedEvents(): array { return $this->recordedEvents; }
    public function clearRecordedEvents(): void { $this->recordedEvents = []; }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents();
        $this->clearRecordedEvents();
        return $events;
    }
}
