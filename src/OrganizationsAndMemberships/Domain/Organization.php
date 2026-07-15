<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\OrganizationCreated;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\OrganizationReactivated;
use Gamad\Core\OrganizationsAndMemberships\Domain\Event\OrganizationSuspended;
use Gamad\Core\Shared\Domain\DomainEvent;
use Gamad\Core\Shared\Domain\RecordsDomainEvents;

/**
 * Constructed only from an IdentityId already active in the Identity
 * Registry of this realm, of type "organization" — that verification
 * happens in Application (CreateOrganizationHandler), never here
 * (GENESIS-011 §4 invariant 2, GENESIS-012 §D).
 */
final class Organization implements RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @var list<Department> */
    private array $departments = [];

    private function __construct(
        private readonly OrganizationId $id,
        private readonly ?OrganizationId $parentId,
        private string $name,
        private OrganizationStatus $status,
        private readonly DateTimeImmutable $foundedAt,
    ) {
    }

    public static function create(
        OrganizationId $id,
        ?OrganizationId $parentId,
        string $name,
        ?DateTimeImmutable $foundedAt = null,
    ): self {
        $foundedAt ??= new DateTimeImmutable();
        $organization = new self($id, $parentId, $name, OrganizationStatus::Active, $foundedAt);
        $organization->recordedEvents[] = new OrganizationCreated($id, $parentId, $name, $foundedAt);

        return $organization;
    }

    /** @param list<Department> $departments */
    public static function reconstitute(
        OrganizationId $id,
        ?OrganizationId $parentId,
        string $name,
        OrganizationStatus $status,
        DateTimeImmutable $foundedAt,
        array $departments = [],
    ): self {
        $organization = new self($id, $parentId, $name, $status, $foundedAt);
        $organization->departments = $departments;

        return $organization;
    }

    public function id(): OrganizationId
    {
        return $this->id;
    }

    public function parentId(): ?OrganizationId
    {
        return $this->parentId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): OrganizationStatus
    {
        return $this->status;
    }

    public function foundedAt(): DateTimeImmutable
    {
        return $this->foundedAt;
    }

    /** @return list<Department> */
    public function departments(): array
    {
        return $this->departments;
    }

    public function addDepartment(DepartmentId $id, string $name): void
    {
        if ($this->status !== OrganizationStatus::Active) {
            throw new DomainException('Cannot add a department to a non-active organization.');
        }

        $this->departments[] = Department::create($id, $name);
    }

    public function transitionTo(OrganizationStatus $target, ?DateTimeImmutable $at = null): void
    {
        $allowed = match ($this->status) {
            OrganizationStatus::Active => [OrganizationStatus::Inactive],
            OrganizationStatus::Inactive => [OrganizationStatus::Active],
        };

        if (!in_array($target, $allowed, true)) {
            throw new DomainException(sprintf('Transition from %s to %s is not allowed.', $this->status->value, $target->value));
        }

        $at ??= new DateTimeImmutable();
        $this->status = $target;
        $this->recordedEvents[] = match ($target) {
            OrganizationStatus::Inactive => new OrganizationSuspended($this->id, $at),
            OrganizationStatus::Active => new OrganizationReactivated($this->id, $at),
        };
    }

    public function suspend(): void
    {
        $this->transitionTo(OrganizationStatus::Inactive);
    }

    public function reactivate(): void
    {
        $this->transitionTo(OrganizationStatus::Active);
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
