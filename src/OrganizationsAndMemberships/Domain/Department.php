<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Domain;

use DomainException;

/**
 * Sub-entity of Organization (GENESIS-012 §B) — never an independent
 * aggregate, never constructed or persisted outside its owning Organization.
 * A Membership referencing a department can only reference one that belongs
 * to the same Organization — an invariant that only holds because Department
 * lives inside the Organization aggregate boundary.
 */
final class Department
{
    private function __construct(
        private readonly DepartmentId $id,
        private string $name,
        private DepartmentStatus $status,
    ) {
    }

    public static function create(DepartmentId $id, string $name): self
    {
        return new self($id, $name, DepartmentStatus::Active);
    }

    public static function reconstitute(DepartmentId $id, string $name, DepartmentStatus $status): self
    {
        return new self($id, $name, $status);
    }

    public function id(): DepartmentId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): DepartmentStatus
    {
        return $this->status;
    }

    public function transitionTo(DepartmentStatus $target): void
    {
        $allowed = match ($this->status) {
            DepartmentStatus::Active => [DepartmentStatus::Inactive],
            DepartmentStatus::Inactive => [DepartmentStatus::Active],
        };

        if (!in_array($target, $allowed, true)) {
            throw new DomainException(sprintf('Transition from %s to %s is not allowed.', $this->status->value, $target->value));
        }

        $this->status = $target;
    }
}
