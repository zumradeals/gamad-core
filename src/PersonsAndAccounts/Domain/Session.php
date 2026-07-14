<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\Event\SessionIssued;
use Gamad\Core\PersonsAndAccounts\Domain\Event\SessionRevoked;
use Gamad\Core\Shared\Domain\DomainEvent;
use Gamad\Core\Shared\Domain\RecordsDomainEvents;

/**
 * A separate aggregate from UserAccount by design (GENESIS-010 §B): far
 * higher write volume, shorter lifecycle, and independently revocable
 * without ever touching the account's own configuration.
 *
 * `tokenHash` is the only credential ever stored — the raw bearer token is
 * generated in Application/Infrastructure, handed to the caller once, and
 * never persisted or placed in an event payload, same principle as password
 * hashing (ADR-0018 §1).
 */
final class Session implements RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly SessionId $id,
        private readonly UserAccountId $userAccountId,
        private readonly AuthenticationMethodId $authenticationMethodId,
        private readonly string $tokenHash,
        private readonly DateTimeImmutable $issuedAt,
        private readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $revokedAt,
    ) {
    }

    public static function issue(
        SessionId $id,
        UserAccountId $userAccountId,
        AuthenticationMethodId $authenticationMethodId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $issuedAt = null,
    ): self {
        $issuedAt ??= new DateTimeImmutable();
        $session = new self($id, $userAccountId, $authenticationMethodId, $tokenHash, $issuedAt, $expiresAt, null);
        $session->recordedEvents[] = new SessionIssued($id, $userAccountId, $authenticationMethodId, $expiresAt, $issuedAt);

        return $session;
    }

    public static function reconstitute(
        SessionId $id,
        UserAccountId $userAccountId,
        AuthenticationMethodId $authenticationMethodId,
        string $tokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $revokedAt,
    ): self {
        return new self($id, $userAccountId, $authenticationMethodId, $tokenHash, $issuedAt, $expiresAt, $revokedAt);
    }

    public function id(): SessionId
    {
        return $this->id;
    }

    public function userAccountId(): UserAccountId
    {
        return $this->userAccountId;
    }

    public function authenticationMethodId(): AuthenticationMethodId
    {
        return $this->authenticationMethodId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt <= ($now ?? new DateTimeImmutable());
    }

    public function isActive(?DateTimeImmutable $now = null): bool
    {
        return !$this->isRevoked() && !$this->isExpired($now);
    }

    /** Idempotent: revoking an already-revoked session is a silent no-op, no event recorded. */
    public function revoke(string $reason, ?DateTimeImmutable $at = null): void
    {
        if ($this->isRevoked()) {
            return;
        }

        $this->revokedAt = $at ?? new DateTimeImmutable();
        $this->recordedEvents[] = new SessionRevoked($this->id, $reason, $this->revokedAt);
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
