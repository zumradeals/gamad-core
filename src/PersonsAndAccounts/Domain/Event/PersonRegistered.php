<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class PersonRegistered implements DomainEvent
{
    public function __construct(
        public PersonId $personId,
        public string $declaredName,
        private DateTimeImmutable $occurredAt,
        public ?string $contact = null,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'person.registered.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->personId;
    }

    public function payload(): array
    {
        return [
            'person_id' => (string) $this->personId,
            'declared_name' => $this->declaredName,
            'contact' => $this->contact,
        ];
    }
}
