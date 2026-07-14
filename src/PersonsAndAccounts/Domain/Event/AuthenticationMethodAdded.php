<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\Shared\Domain\DomainEvent;

/**
 * Never carries the credential itself (ADR-0018 §1, Task 4 — no secret in
 * logs or audit trail). Only the fact that a method of a given type was
 * attached is published.
 */
final readonly class AuthenticationMethodAdded implements DomainEvent
{
    public function __construct(
        public UserAccountId $userAccountId,
        public AuthenticationMethodId $authenticationMethodId,
        public AuthenticationMethodType $methodType,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'authentication_method.added.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->userAccountId;
    }

    public function payload(): array
    {
        return [
            'user_account_id' => (string) $this->userAccountId,
            'authentication_method_id' => (string) $this->authenticationMethodId,
            'method_type' => $this->methodType->value,
        ];
    }
}
