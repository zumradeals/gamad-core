<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\Shared\Domain\DomainEvent;

final readonly class UserAccountCreated implements DomainEvent
{
    public function __construct(
        public UserAccountId $userAccountId,
        public PersonId $personId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'user_account.created.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->userAccountId;
    }

    public function payload(): array
    {
        return [
            'user_account_id' => (string) $this->userAccountId,
            'person_id' => (string) $this->personId,
        ];
    }
}
