<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\PersonsAndAccounts\Domain\Event\PersonRegistered;
use Gamad\Core\Shared\Domain\DomainEvent;
use Gamad\Core\Shared\Domain\RecordsDomainEvents;

/**
 * Constructed only from an IdentityId already active in the Identity
 * Registry of this realm — that verification happens in Application
 * (RegisterPersonHandler), never here (GENESIS-010 §B/§C).
 */
final class Person implements RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly PersonId $id,
        private string $declaredName,
        private PersonStatus $status,
        private readonly DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(
        PersonId $id,
        string $declaredName,
        ?DateTimeImmutable $registeredAt = null,
    ): self {
        $registeredAt ??= new DateTimeImmutable();
        $person = new self($id, $declaredName, PersonStatus::Active, $registeredAt);
        $person->recordedEvents[] = new PersonRegistered($id, $declaredName, $registeredAt);

        return $person;
    }

    public static function reconstitute(
        PersonId $id,
        string $declaredName,
        PersonStatus $status,
        DateTimeImmutable $registeredAt,
    ): self {
        return new self($id, $declaredName, $status, $registeredAt);
    }

    public function id(): PersonId
    {
        return $this->id;
    }

    public function declaredName(): string
    {
        return $this->declaredName;
    }

    public function status(): PersonStatus
    {
        return $this->status;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function transitionTo(PersonStatus $target): void
    {
        $allowed = match ($this->status) {
            PersonStatus::Active => [PersonStatus::Inactive, PersonStatus::Deceased],
            PersonStatus::Inactive => [PersonStatus::Active, PersonStatus::Deceased],
            PersonStatus::Deceased => [],
        };

        if (!in_array($target, $allowed, true)) {
            throw new DomainException(sprintf('Transition from %s to %s is not allowed.', $this->status->value, $target->value));
        }

        $this->status = $target;
    }

    public function recordedEvents(): array
    {
        return $this->recordedEvents;
    }

    public function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents();
        $this->clearRecordedEvents();

        return $events;
    }
}
