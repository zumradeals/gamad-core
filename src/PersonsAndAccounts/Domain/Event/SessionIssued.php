<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain\Event;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\Shared\Domain\DomainEvent;

/** Never carries the bearer token — only its non-secret database identifier and hash never leave this event. */
final readonly class SessionIssued implements DomainEvent
{
    public function __construct(
        public SessionId $sessionId,
        public UserAccountId $userAccountId,
        public AuthenticationMethodId $authenticationMethodId,
        public DateTimeImmutable $expiresAt,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'session.issued.v1';
    }

    public function aggregateId(): string
    {
        return (string) $this->sessionId;
    }

    public function payload(): array
    {
        return [
            'session_id' => (string) $this->sessionId,
            'user_account_id' => (string) $this->userAccountId,
            'authentication_method_id' => (string) $this->authenticationMethodId,
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
        ];
    }
}
