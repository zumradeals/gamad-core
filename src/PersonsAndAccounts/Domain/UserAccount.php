<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\PersonsAndAccounts\Domain\Event\AuthenticationMethodAdded;
use Gamad\Core\PersonsAndAccounts\Domain\Event\UserAccountCreated;
use Gamad\Core\Shared\Domain\DomainEvent;
use Gamad\Core\Shared\Domain\RecordsDomainEvents;

final class UserAccount implements RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @var list<AuthenticationMethod> */
    private array $authenticationMethods = [];

    private function __construct(
        private readonly UserAccountId $id,
        private readonly PersonId $personId,
        private UserAccountStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        UserAccountId $id,
        PersonId $personId,
        ?DateTimeImmutable $createdAt = null,
    ): self {
        $createdAt ??= new DateTimeImmutable();
        $account = new self($id, $personId, UserAccountStatus::Active, $createdAt);
        $account->recordedEvents[] = new UserAccountCreated($id, $personId, $createdAt);

        return $account;
    }

    /** @param list<AuthenticationMethod> $authenticationMethods */
    public static function reconstitute(
        UserAccountId $id,
        PersonId $personId,
        UserAccountStatus $status,
        DateTimeImmutable $createdAt,
        array $authenticationMethods = [],
    ): self {
        $account = new self($id, $personId, $status, $createdAt);
        $account->authenticationMethods = $authenticationMethods;

        return $account;
    }

    public function id(): UserAccountId
    {
        return $this->id;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function status(): UserAccountStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<AuthenticationMethod> */
    public function authenticationMethods(): array
    {
        return $this->authenticationMethods;
    }

    /**
     * Every call appends a new method rather than mutating an existing one —
     * an append-only history, consistent with this codebase's audit-chain
     * philosophy elsewhere. The most recently added method of a given type
     * is the one in effect (see currentPasswordMethod()).
     */
    public function addAuthenticationMethod(
        AuthenticationMethodId $id,
        AuthenticationMethodType $type,
        string $credentialRef,
        ?DateTimeImmutable $addedAt = null,
    ): void {
        if ($this->status !== UserAccountStatus::Active) {
            throw new DomainException('Cannot add an authentication method to a non-active account.');
        }

        $addedAt ??= new DateTimeImmutable();
        $this->authenticationMethods[] = new AuthenticationMethod($id, $type, $credentialRef, $addedAt);
        $this->recordedEvents[] = new AuthenticationMethodAdded($this->id, $id, $type, $addedAt);
    }

    /** The password method currently in effect, if any — the most recently added one. */
    public function currentPasswordMethod(): ?AuthenticationMethod
    {
        $current = null;
        foreach ($this->authenticationMethods as $method) {
            if ($method->type() !== AuthenticationMethodType::Password) {
                continue;
            }
            if ($current === null || $method->addedAt() >= $current->addedAt()) {
                $current = $method;
            }
        }

        return $current;
    }

    public function transitionTo(UserAccountStatus $target): void
    {
        $allowed = match ($this->status) {
            UserAccountStatus::Active => [UserAccountStatus::Suspended, UserAccountStatus::Disabled],
            UserAccountStatus::Suspended => [UserAccountStatus::Active, UserAccountStatus::Disabled],
            UserAccountStatus::Disabled => [],
        };

        if (!in_array($target, $allowed, true)) {
            throw new DomainException(sprintf('Transition from %s to %s is not allowed.', $this->status->value, $target->value));
        }

        $this->status = $target;
    }

    public function suspend(): void
    {
        $this->transitionTo(UserAccountStatus::Suspended);
    }

    public function activate(): void
    {
        $this->transitionTo(UserAccountStatus::Active);
    }

    public function disable(): void
    {
        $this->transitionTo(UserAccountStatus::Disabled);
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
