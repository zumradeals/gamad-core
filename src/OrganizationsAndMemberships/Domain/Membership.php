<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\MembershipCreated;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\MembershipEnded;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\MembershipResumed;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\MembershipSuspended;
use Gamad\Core\Shared\Domain\DomainEvent;
use Gamad\Core\Shared\Domain\RecordsDomainEvents;

/**
 * A separate aggregate from Organization (GENESIS-012 §B) — its own
 * lifecycle, its own events, no lock on the owning organization. Constructed
 * only after Application has verified the referenced Person and Organization
 * exist (GENESIS-011 §4 invariant 4) — never here. personId is kept as a
 * plain string, not PersonsAndAccounts\Domain\PersonId: this context never
 * imports that namespace (ADR-0013 boundary).
 */
final class Membership implements RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly MembershipId $id,
        private readonly string $personId,
        private readonly OrganizationId $organizationId,
        private readonly ?DepartmentId $departmentId,
        private readonly MembershipType $membershipType,
        private MembershipStatus $status,
        private readonly DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $endedAt,
    ) {
    }

    public static function create(
        MembershipId $id,
        string $personId,
        OrganizationId $organizationId,
        MembershipType $membershipType,
        ?DepartmentId $departmentId = null,
        ?DateTimeImmutable $startedAt = null,
    ): self {
        $startedAt ??= new DateTimeImmutable();
        $membership = new self($id, $personId, $organizationId, $departmentId, $membershipType, MembershipStatus::Active, $startedAt, null);
        $membership->recordedEvents[] = new MembershipCreated($id, $personId, $organizationId, $membershipType, $startedAt);

        return $membership;
    }

    public static function reconstitute(
        MembershipId $id,
        string $personId,
        OrganizationId $organizationId,
        MembershipType $membershipType,
        MembershipStatus $status,
        DateTimeImmutable $startedAt,
        ?DepartmentId $departmentId = null,
        ?DateTimeImmutable $endedAt = null,
    ): self {
        return new self($id, $personId, $organizationId, $departmentId, $membershipType, $status, $startedAt, $endedAt);
    }

    public function id(): MembershipId
    {
        return $this->id;
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function departmentId(): ?DepartmentId
    {
        return $this->departmentId;
    }

    public function membershipType(): MembershipType
    {
        return $this->membershipType;
    }

    public function status(): MembershipStatus
    {
        return $this->status;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    private function transitionTo(MembershipStatus $target, ?DateTimeImmutable $at = null): void
    {
        $allowed = match ($this->status) {
            MembershipStatus::Active => [MembershipStatus::Suspended, MembershipStatus::Ended],
            MembershipStatus::Suspended => [MembershipStatus::Active, MembershipStatus::Ended],
            MembershipStatus::Ended => [],
        };

        if (!in_array($target, $allowed, true)) {
            throw new DomainException(sprintf('Transition from %s to %s is not allowed.', $this->status->value, $target->value));
        }

        $at ??= new DateTimeImmutable();
        $this->status = $target;

        if ($target === MembershipStatus::Ended) {
            $this->endedAt = $at;
        }

        $this->recordedEvents[] = match ($target) {
            MembershipStatus::Suspended => new MembershipSuspended($this->id, $at),
            MembershipStatus::Active => new MembershipResumed($this->id, $at),
            MembershipStatus::Ended => new MembershipEnded($this->id, $at),
        };
    }

    public function suspend(?DateTimeImmutable $at = null): void
    {
        $this->transitionTo(MembershipStatus::Suspended, $at);
    }

    public function resume(?DateTimeImmutable $at = null): void
    {
        $this->transitionTo(MembershipStatus::Active, $at);
    }

    public function end(?DateTimeImmutable $at = null): void
    {
        $this->transitionTo(MembershipStatus::Ended, $at);
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
